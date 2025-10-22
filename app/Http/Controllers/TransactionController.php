<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Goal;
use App\Models\SavingsLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    // ===========================
    // Get all user transactions
    // ===========================
    public function index()
    {
        try {
            $transactions = Transaction::with(['category', 'goal'])
                ->where('user_id', auth()->id())
                ->orderBy('date', 'desc')
                ->get();

            return response()->json($transactions);
        } catch (\Exception $e) {
            Log::error('Failed to fetch transactions', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to fetch transactions'], 500);
        }
    }

    // ===========================
    // Transaction details
    // ===========================
    public function show($id)
    {
        try {
            $transaction = Transaction::with(['category', 'goal'])
                ->where('user_id', auth()->id())
                ->findOrFail($id);

            return response()->json($transaction);
        } catch (\Exception $e) {
            Log::error('Transaction not found', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Transaction not found'], 404);
        }
    }

    // ===========================
    // Create Transaction 
    // ===========================
    public function store(Request $request)
    {
        $userId = auth()->id();
        if (!$userId) return response()->json(['message' => 'Unauthorized'], 401);

        try {
            $validated = $request->validate([
                'date' => 'required|date',
                'category_id' => 'required|exists:categories,id',
                'amount' => 'required|numeric|min:0.01',
                'note' => 'nullable|string|max:255',
                'goal_id' => 'nullable|exists:goals,id'
            ]);

            DB::beginTransaction();

            // Validate goal ownership if there is a goal_id
            if (!empty($validated['goal_id'])) {
                $goalExists = Goal::where('id', $validated['goal_id'])
                    ->where('user_id', $userId)
                    ->exists();
                
                if (!$goalExists) {
                    return response()->json(['message' => 'Goal not found or unauthorized'], 403);
                }
            }

            $transaction = Transaction::create([
                'user_id' => $userId,
                'date' => $validated['date'],
                'category_id' => $validated['category_id'],
                'amount' => $validated['amount'],
                'note' => $validated['note'] ?? null,
                'goal_id' => $validated['goal_id'] ?? null
            ]);

            $transaction->load('category');

            // Manage savings categories by purpose
            if ($transaction->goal_id && $transaction->category->type === 'saving') {
                $goal = Goal::lockForUpdate()->find($transaction->goal_id);
                
                if ($goal) {
                    $currentFromDB = Transaction::where('goal_id', $goal->id)
                        ->where('id', '!=', $transaction->id)
                        ->sum('amount');
                    
                    $available = $goal->target_amount - $currentFromDB;

                    Log::info('Goal validation', [
                        'goal_id' => $goal->id,
                        'goal_name' => $goal->name,
                        'target' => $goal->target_amount,
                        'current_from_db' => $currentFromDB,
                        'available' => $available,
                        'input_amount' => $transaction->amount
                    ]);

                    // Validation
                    if ($transaction->amount > $available) {
                        DB::rollBack();
                        return response()->json([
                            'message' => "Amount melebihi sisa target goal '{$goal->name}'. Maksimum: " . number_format($available, 0, ',', '.')
                        ], 422);
                    }

                    // Update goal with final value
                    $goal->current_amount = $currentFromDB + $transaction->amount;
                    $goal->save();

                    Log::info('Goal updated', [
                        'goal_id' => $goal->id,
                        'new_current_amount' => $currentFromDB,
                        'target' => $goal->target_amount,
                        'is_completed' => $currentFromDB >= $goal->target_amount
                    ]);

                    // Create savings log
                    SavingsLog::create([
                        'transaction_id' => $transaction->id,
                        'goal_id' => $goal->id,
                        //'user_id' => $userId,
                        'amount' => $transaction->amount,
                    ]);
                }
            }

            DB::commit();
            
            return response()->json($transaction->load(['category', 'goal']), 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Transaction creation failed: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create transaction', 'error' => $e->getMessage()], 500);
        }
    }

    // ===========================
    // Transaction update
    // ===========================
    public function update(Request $request, $id)
    {
        $userId = auth()->id();
        if (!$userId) return response()->json(['message' => 'Unauthorized'], 401);

        try {
            $transaction = Transaction::where('id', $id)
                ->where('user_id', $userId)
                ->with('category')
                ->firstOrFail();

            // Save old value
            $oldAmount = $transaction->amount;
            $oldGoalId = $transaction->goal_id;
            $oldCategoryType = $transaction->category->type;

            $validated = $request->validate([
                'date' => 'required|date',
                'category_id' => 'required|exists:categories,id',
                'amount' => 'required|numeric|min:0.01',
                'note' => 'nullable|string|max:255',
                'goal_id' => 'nullable|exists:goals,id'
            ]);

            // Validate goal ownership if there is a new goal_id
            if (!empty($validated['goal_id'])) {
                $goalExists = Goal::where('id', $validated['goal_id'])
                    ->where('user_id', $userId)
                    ->exists();
                
                if (!$goalExists) {
                    return response()->json(['message' => 'Goal not found or unauthorized'], 403);
                }
            }

            DB::beginTransaction();

            // STEP 1: Rollback old goal if saving
            if ($oldGoalId && $oldCategoryType === 'saving') {
                SavingsLog::where('transaction_id', $transaction->id)->delete();
            }

            // STEP 2: Transaction update
            $transaction->update([
                'date' => $validated['date'],
                'category_id' => (int)$validated['category_id'],
                'amount' => (float)$validated['amount'],
                'note' => $validated['note'] ?? null,
                'goal_id' => !empty($validated['goal_id']) ? (int)$validated['goal_id'] : null
            ]);

            $transaction->load(['category', 'goal']);

            // STEP 3: Update new goal if saving category
            $newGoalId = $transaction->goal_id;
            $newCategoryType = $transaction->category->type;

            if ($newGoalId && $newCategoryType === 'saving') {
                $goal = Goal::lockForUpdate()->find($newGoalId);
                if ($goal) {
                    // Recalculate from all transactions
                    $calculatedCurrent = Transaction::where('goal_id', $goal->id)
                        ->sum('amount');
                    
                    $available = $goal->target_amount - $calculatedCurrent;

                    // Validation must not exceed the target
                    if ($calculatedCurrent > $goal->target_amount) {
                        DB::rollBack();
                        return response()->json([
                            'message' => "Total transaksi melebihi target goal. Maksimum allowed: " . number_format($goal->target_amount - ($calculatedCurrent - $transaction->amount), 0, ',', '.')
                        ], 422);
                    }

                    // Update goal
                    $goal->current_amount = $calculatedCurrent;
                    $goal->save();

                    // Create a new savings log
                    SavingsLog::create([
                        'transaction_id' => $transaction->id,
                        'goal_id' => $goal->id,
                        //'user_id' => $userId,
                        'amount' => $transaction->amount,
                    ]);
                }
            }
            
            if ($oldGoalId && $oldGoalId !== $newGoalId) {
                $oldGoal = Goal::lockForUpdate()->find($oldGoalId);
                if ($oldGoal) {
                    $oldGoal->current_amount = Transaction::where('goal_id', $oldGoalId)->sum('amount');
                    $oldGoal->save();
                }
            }

            DB::commit();
            return response()->json($transaction);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Transaction update failed', ['error' => $e->getMessage(), 'transaction_id' => $id]);
            return response()->json(['message' => 'Failed to update transaction', 'error' => $e->getMessage()], 500);
        }
    }

    // ===========================
    // Delete transaksi
    // ===========================
    public function destroy($id)
    {
        $userId = auth()->id();
        if (!$userId) return response()->json(['message' => 'Unauthorized'], 401);

        try {
            // ✅ Load category 
            $transaction = Transaction::with('category')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->firstOrFail();

            DB::beginTransaction();

            // Rollback goal if saving type
            if ($transaction->goal_id && $transaction->category && $transaction->category->type === 'saving') {
                $goal = Goal::lockForUpdate()->find($transaction->goal_id);
                if ($goal) {
                    // Delete savings log 
                    SavingsLog::where('transaction_id', $transaction->id)->delete();
                    
                    // Recalculate current_amount
                    $goal->current_amount = Transaction::where('goal_id', $goal->id)
                        ->where('id', '!=', $transaction->id)
                        ->sum('amount');
                    
                    $goal->save();
                }
            }

            $transaction->delete();

            DB::commit();
            return response()->json(['message' => 'Transaction deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Transaction deletion failed', ['error' => $e->getMessage(), 'transaction_id' => $id]);
            return response()->json(['message' => 'Failed to delete transaction', 'error' => $e->getMessage()], 500);
        }
    }

    // ===========================
    // Summary of the last 3 months
    // ===========================
        public function summary()
    {
        $userId = auth()->id();
        if (!$userId) return response()->json(['error' => 'Unauthorized'], 401);

        try {
            $data = DB::table('transactions')
                ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
                ->select(
                    DB::raw("DATE_TRUNC('month', transactions.date) as month_date"),
                    DB::raw("TO_CHAR(DATE_TRUNC('month', transactions.date), 'Mon YYYY') as month"),
                    DB::raw("SUM(CASE WHEN categories.type = 'income' THEN transactions.amount ELSE 0 END) as income"),
                    DB::raw("SUM(CASE WHEN categories.type = 'expense' THEN transactions.amount ELSE 0 END) as expense"),
                    DB::raw("SUM(CASE WHEN categories.type = 'saving' THEN transactions.amount ELSE 0 END) as saving")
                )
                ->where('transactions.user_id', $userId)
                ->whereNotNull('transactions.date')
                ->where('transactions.date', '>=', now()->subMonths(3)->startOfMonth())
                ->groupBy(DB::raw("DATE_TRUNC('month', transactions.date)"))
                ->orderBy('month_date', 'asc')
                ->get();

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Summary fetch failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to fetch summary'], 500);
        }
    }


        // ===========================
        // This month's summary
        // ===========================
        public function summaryCurrent()
        {
            $userId = auth()->id();
            if (!$userId) return response()->json(['error' => 'Unauthorized'], 401);

            try {
                $result = DB::table('transactions')
                    ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
                    ->selectRaw("COALESCE(SUM(CASE WHEN categories.type = 'income' THEN transactions.amount ELSE 0 END), 0) as income")
                    ->selectRaw("COALESCE(SUM(CASE WHEN categories.type = 'income' THEN transactions.amount ELSE 0 END), 0) as income")
                    ->selectRaw("COALESCE(SUM(CASE WHEN categories.type IN ('expense', 'saving') THEN transactions.amount ELSE 0 END), 0) as expense")

                    ->where('transactions.user_id', $userId)
                    ->whereRaw("DATE_TRUNC('month', transactions.date) = DATE_TRUNC('month', CURRENT_DATE)")
                    ->first();

                return response()->json($result);
            } catch (\Exception $e) {
                Log::error('Current summary fetch failed', ['error' => $e->getMessage()]);
                return response()->json(['message' => 'Failed to fetch current summary'], 500);
            }
        }

        // ===========================
        // Growth insights & top categories
        // ===========================
        public function insight()
        {
            $userId = auth()->id();
            if (!$userId) return response()->json(['error' => 'Unauthorized'], 401);

            try {
                // Total EXPENSES this month
                $currentMonth = DB::table('transactions')
                    ->join('categories', 'transactions.category_id', '=', 'categories.id')
                    ->where('transactions.user_id', $userId)
                    ->where('categories.type', 'expense')
                    ->whereMonth('transactions.date', now()->month)
                    ->whereYear('transactions.date', now()->year)
                    ->selectRaw("COALESCE(SUM(transactions.amount), 0) as total")
                    ->first();

                // Total EXPENSES last month
                $lastMonth = DB::table('transactions')
                    ->join('categories', 'transactions.category_id', '=', 'categories.id')
                    ->where('transactions.user_id', $userId)
                    ->where('categories.type', 'expense')
                    ->whereMonth('transactions.date', now()->subMonth()->month)
                    ->whereYear('transactions.date', now()->subMonth()->year)
                    ->selectRaw("COALESCE(SUM(transactions.amount), 0) as total")
                    ->first();

                $growth = 0;

                if ($lastMonth && $lastMonth->total > 0) {
                    $growth = round((($currentMonth->total - $lastMonth->total) / $lastMonth->total) * 100);
                } elseif ($currentMonth->total > 0 && (!$lastMonth || $lastMonth->total == 0)) {
                    $growth = 100;
                }

                // Top category this month by amount
                $topCategoryThisMonth = DB::table('transactions')
                    ->join('categories', 'transactions.category_id', '=', 'categories.id')
                    ->where('transactions.user_id', $userId)
                    ->where('categories.type', 'expense')
                    ->whereMonth('transactions.date', now()->month)
                    ->whereYear('transactions.date', now()->year)
                    ->selectRaw('categories.name, SUM(transactions.amount) as total')
                    ->groupBy('categories.name')
                    ->orderByDesc('total')
                    ->first();

                // Top category last month by amount
                $topCategoryLastMonth = DB::table('transactions')
                    ->join('categories', 'transactions.category_id', '=', 'categories.id')
                    ->where('transactions.user_id', $userId)
                    ->where('categories.type', 'expense')
                    ->whereMonth('transactions.date', now()->subMonth()->month)
                    ->whereYear('transactions.date', now()->subMonth()->year)
                    ->selectRaw('categories.name, SUM(transactions.amount) as total')
                    ->groupBy('categories.name')
                    ->orderByDesc('total')
                    ->first();

                return response()->json([
                    'growth' => $growth,
                    'current_total' => $currentMonth->total ?? 0,
                    'last_total' => $lastMonth->total ?? 0,
                    'top_category_this_month' => [
                        'name' => $topCategoryThisMonth?->name,
                        'amount' => $topCategoryThisMonth?->total ?? 0,
                    ],
                    'top_category_last_month' => [
                        'name' => $topCategoryLastMonth?->name,
                        'amount' => $topCategoryLastMonth?->total ?? 0,
                    ],
                ]);
            } catch (\Exception $e) {
                Log::error('Insight fetch failed', ['error' => $e->getMessage()]);
                return response()->json(['message' => 'Failed to fetch insights'], 500);
            }
        }
}