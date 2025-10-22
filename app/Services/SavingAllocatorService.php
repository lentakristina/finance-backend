<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\SavingsLog;
use Illuminate\Support\Facades\DB;

class SavingAllocatorService
{
    /**
     * Allocate a saving transaction into goals.
     *
     * @param \App\Models\Transaction $transaction
     */
    public function allocate($transaction)
    {
        DB::transaction(function () use ($transaction) {
            $amount = (float) $transaction->amount;
            if ($amount <= 0) return;

            // load goals (order by priority ascending)
            $goals = Goal::orderBy('priority', 'asc')->get();

            if ($goals->isEmpty()) return;

            $totalPct = (float) $goals->sum('allocation_pct');

            // 1) Weighted allocation if at least one allocation_pct > 0
            if ($totalPct > 0) {
                // normalize if totalPct not 100 (we'll use given ratios)
                foreach ($goals as $goal) {
                    if ($amount <= 0) break;

                    $pct = (float) $goal->allocation_pct;
                    if ($pct <= 0) continue;

                    $portion = round($transaction->amount * ($pct / 100), 2);
                    if ($portion <= 0) continue;

                    // cap by needed amount (can't exceed target)
                    $needed = (float) $goal->target_amount - (float) $goal->current_amount;
                    if ($needed <= 0) continue;

                    $allocated = min($portion, $needed);

                    // create log
                    SavingsLog::create([
                        'transaction_id' => $transaction->id,
                        'goal_id'        => $goal->id,
                        'amount'         => $allocated,
                    ]);

                    // update goal
                    $goal->increment('current_amount', $allocated);

                    $amount -= $allocated;
                }

                // if leftover amount still exists (due to capping), allocate by priority
                if ($amount > 0) {
                    foreach ($goals as $goal) {
                        if ($amount <= 0) break;
                        $needed = (float) $goal->target_amount - (float) $goal->current_amount;
                        if ($needed <= 0) continue;

                        $allocate = min($amount, $needed);

                        SavingsLog::create([
                            'transaction_id' => $transaction->id,
                            'goal_id'        => $goal->id,
                            'amount'         => $allocate,
                        ]);
                        $goal->increment('current_amount', $allocate);
                        $amount -= $allocate;
                    }
                }
            } else {
                // 2) Priority allocation (fill first goal by priority until full, then next)
                foreach ($goals as $goal) {
                    if ($amount <= 0) break;

                    $needed = (float) $goal->target_amount - (float) $goal->current_amount;
                    if ($needed <= 0) continue;

                    $allocate = min($amount, $needed);

                    SavingsLog::create([
                        'transaction_id' => $transaction->id,
                        'goal_id'        => $goal->id,
                        'amount'         => $allocate,
                    ]);

                    $goal->increment('current_amount', $allocate);

                    $amount -= $allocate;
                }
            }

            // done — DB::transaction ensures atomicity
        });
    }
}
