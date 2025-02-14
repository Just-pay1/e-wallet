<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
        {
            Schema::create('transcations', function (Blueprint $table) {
                $table->uuid()->primary();
                $table->unsignedBigInteger('debit_from');
                $table->foreign('debit_from')->references('id')->on('wallets'); 
                $table->unsignedBigInteger('credit_to');
                $table->foreign('credit_to')->references('id')->on('wallets'); 
                $table->decimal('amount',10,2);
                $table->string('type');
                $table->string('description');
                $table->timestamps();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transcations');
    }
};
