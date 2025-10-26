<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('currency', 3); // ISO 4217 (USD, EUR, BRL, etc)
            $table->decimal('amount', 20, 2)->default(0);
            $table->timestamps();
            
            // Uma conta pode ter apenas um saldo por moeda
            $table->unique(['account_id', 'currency']);
            
            // Index para buscas rápidas
            $table->index(['account_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balances');
    }
};