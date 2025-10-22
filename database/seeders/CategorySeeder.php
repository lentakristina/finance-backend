<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Salary', 'type' => 'income', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Freelance', 'type' => 'income', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Food', 'type' => 'expense', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Transport', 'type' => 'expense', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Entertainment', 'type' => 'expense', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Shopping', 'type' => 'expense', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Health', 'type' => 'expense', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Investment', 'type' => 'expense', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Saving', 'type' => 'saving', 'created_at' => now(), 'updated_at' => now()],
        ];

         foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                ['type' => $category['type']]
            );
        }
    }
}
