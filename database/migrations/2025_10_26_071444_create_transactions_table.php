<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['deposit', 'withdrawal']);
            $table->string('currency', 3);
            $table->decimal('amount', 20, 2);
            $table->decimal('ptax_rate', 10, 6)->nullable(); // Taxa PTAX utilizada
            $table->json('conversion_details')->nullable(); // Detalhes de conversões realizadas
            $table->text('description')->nullable();
            $table->timestamps();
            
            // Indexes para relatórios e consultas
            $table->index(['account_id', 'created_at']);
            $table->index(['account_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};