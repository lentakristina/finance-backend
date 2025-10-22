<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\SavingsLog;

class SavingsLogSeeder extends Seeder
{
    public function run(): void
    {
        // take 1 savings transaction
        $transaction = Transaction::whereHas('category', function ($q) {
            $q->where('type', 'saving');
        })->first();

        // take all goals
        $goals = Goal::all();

        if ($transaction && $goals->count() > 0) {
            $amount = $transaction->amount;
            
        foreach ($goals as $goal) {
            if ($amount <= 0) break;

            $needed = $goal->target_amount - $goal->current_amount;
            if ($needed <= 0) continue; // the goal has been achieved
            $allocate = min($amount, $needed);

            SavingsLog::create([
                'transaction_id' => $transaction->id,
                'goal_id'        => $goal->id,
                'amount'         => $allocate,
            ]);

            // update goal from logs
            $goal->increment('current_amount', $allocate);

            $amount -= $allocate;
        }

        }
    }
}
