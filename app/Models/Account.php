<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'account_number',
        'holder_name',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function balances(): HasMany
    {
        return $this->hasMany(Balance::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Verifica se a conta está ativa
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Obtém o saldo de uma moeda específica
     */
    public function getBalance(string $currency): float
    {
        $balance = $this->balances()->where('currency', $currency)->first();
        return $balance ? (float) $balance->amount : 0.0;
    }

    /**
     * Obtém todos os saldos da conta
     */
    public function getAllBalances(): array
    {
        return $this->balances()
            ->where('amount', '>', 0)
            ->get()
            ->map(fn($balance) => [
                'currency' => $balance->currency,
                'amount' => (float) $balance->amount
            ])
            ->toArray();
    }
}