<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavingsLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'goal_id',
        //'user_id',
        'amount'
    ];
    
    // Relationship to Goal
    public function goal()
    {
        return $this->belongsTo(Goal::class);
    }

    // Relationship to Transaction
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
