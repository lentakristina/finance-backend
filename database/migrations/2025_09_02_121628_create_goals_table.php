<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::create('goals', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->decimal('target_amount', 15, 2);
        $table->decimal('current_amount', 15, 2)->default(0);
        $table->unsignedBigInteger('category_id')->nullable();
        $table->integer('priority')->nullable(); 
        $table->decimal('allocation_pct', 5, 2)->nullable(); 
        $table->timestamps();

        $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
    });

}

public function down()
    {
        Schema::dropIfExists('goals');
    }

};