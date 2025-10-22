<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('goal_id');
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            // foreign key -> transactions table
            $table->foreign('transaction_id')
                  ->references('id')->on('transactions')
                  ->onDelete('cascade');

            // foreign key -> goals table
            $table->foreign('goal_id')
                  ->references('id')->on('goals')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_logs');
    }
};
