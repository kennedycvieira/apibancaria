<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Balance;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        // Conta 1 - Com múltiplas moedas
        $account1 = Account::create([
            'account_number' => '0001',
            'holder_name' => 'João Silva',
            'status' => 'active',
        ]);

        Balance::create([
            'account_id' => $account1->id,
            'currency' => 'BRL',
            'amount' => 5000.00,
        ]);

        Balance::create([
            'account_id' => $account1->id,
            'currency' => 'USD',
            'amount' => 1000.00,
        ]);

        Balance::create([
            'account_id' => $account1->id,
            'currency' => 'EUR',
            'amount' => 500.00,
        ]);

        // Conta 2 - Apenas BRL
        $account2 = Account::create([
            'account_number' => '0002',
            'holder_name' => 'Maria Santos',
            'status' => 'active',
        ]);

        Balance::create([
            'account_id' => $account2->id,
            'currency' => 'BRL',
            'amount' => 10000.00,
        ]);

        // Conta 3 - Sem saldo
        Account::create([
            'account_number' => '0003',
            'holder_name' => 'Pedro Oliveira',
            'status' => 'active',
        ]);

        // Conta 4 - Inativa
        Account::create([
            'account_number' => '0004',
            'holder_name' => 'Ana Costa',
            'status' => 'inactive',
        ]);
    }
}