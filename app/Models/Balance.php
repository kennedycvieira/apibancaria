<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Balance extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'currency',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Adiciona valor ao saldo
     */
    public function addAmount(float $amount): void
    {
        $this->amount += $amount;
        $this->save();
    }

    /**
     * Subtrai valor do saldo
     */
    public function subtractAmount(float $amount): void
    {
        $this->amount -= $amount;
        $this->save();
    }

    /**
     * Verifica se há saldo suficiente
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->amount >= $amount;
    }
}