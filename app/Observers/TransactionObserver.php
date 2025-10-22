<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Models\Goal;

class TransactionObserver
{
    public function created(Transaction $transaction)
    {
        $this->updateGoalAmount($transaction);
    }

    public function updated(Transaction $transaction)
    {
        // Update old and new goals if goal_id changes
        if ($transaction->isDirty('goal_id')) {
            $oldGoalId = $transaction->getOriginal('goal_id');
            if ($oldGoalId) {
                $this->recalculateGoal($oldGoalId);
            }
        }
        $this->updateGoalAmount($transaction);
    }

    public function deleted(Transaction $transaction)
    {
        $this->updateGoalAmount($transaction);
    }

    private function updateGoalAmount(Transaction $transaction)
    {
        if ($transaction->goal_id) {
            $this->recalculateGoal($transaction->goal_id);
        }
    }

    private function recalculateGoal($goalId)
    {
        $goal = Goal::find($goalId);
        if ($goal) {
            $goal->current_amount = Transaction::where('goal_id', $goalId)->sum('amount');
            $goal->saveQuietly(); 
        }
    }
}