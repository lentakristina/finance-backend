<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        
        if (!$user) {
            $user = User::create([
                'name' => 'Demo User',
                'email' => 'demo@example.com',
                'password' => bcrypt('password'),
            ]);
        }

        $transactions = [];

        for ($i = 1; $i <= 20; $i++) {
            $transactions[] = [
                'user_id' => $user->id,  
                'category_id' => rand(1, 9),
                'amount' => rand(50000, 5000000),
                'date' => Carbon::now()->subDays(rand(0, 30))->format('Y-m-d'),
                'note' => 'Dummy transaksi #' . $i,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('transactions')->insert($transactions);
    }
}