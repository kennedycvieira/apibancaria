<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\BalanceController;
use App\Models\Account;
use App\Models\Balance;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControllersTest extends TestCase
{
    use RefreshDatabase;
    public function test_account_controller_exists()
    {
        $this->assertTrue(class_exists(AccountController::class));
    }
    public function test_transaction_controller_exists()
    {
        $this->assertTrue(class_exists(TransactionController::class));
    }
    public function test_balance_controller_exists()
    {
        $this->assertTrue(class_exists(BalanceController::class));
    }

    public function test_account_is_active_returns_false_for_inactive_account()
    {
        $account = Account::create([
            'account_number' => '0002',
            'holder_name' => 'Inactive User',
            'status' => 'inactive',
        ]);

        $this->assertFalse($account->isActive());
    }
    public function test_account_has_balances_relationship()
    { 
        $account = Account::create([
            'account_number' => '0003',
            'holder_name' => 'Balance User',
            'status' => 'active',
        ]);

        Balance::create([
            'account_id' => $account->id,
            'currency' => 'EUR',
            'amount' => 200.00,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $account->balances);
        $this->assertCount(1, $account->balances);
    }
    public function test_account_has_transactions_relationship()
    {
        $account = Account::create([
            'account_number' => '0004',
            'holder_name' => 'Transaction User',
            'status' => 'active',
        ]);

        Transaction::create([
            'account_id' => $account->id,
            'type' => 'withdrawal',
            'currency' => 'EUR',
            'amount' => 50.00,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $account->transactions);
        $this->assertCount(1, $account->transactions);
    }
    public function test_account_is_active_returns_true_for_active_account()
    {
        $account = Account::create([
            'account_number' => '0001',
            'holder_name' => 'Active User',
            'status' => 'active',
        ]);

        $this->assertTrue($account->isActive());
    }

    public function test_account_controller_index()
    {
        Account::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/accounts');

        $response->assertStatus(200)
                 ->assertJsonCount(3);
    }

    public function test_balance_controller_show_with_currency()
    {
        $account = Account::factory()->create();
        Balance::factory()->create(['account_id' => $account->id, 'currency' => 'USD', 'amount' => 1000]);

        $response = $this->getJson("/api/v1/balance?account_number={$account->account_number}&currency=usd");

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    public function test_transaction_controller_deposit()
    {
        $account = Account::factory()->create();

        $deposit = [
            'account_number' => $account->account_number,
            'amount' => 100,
            'currency' => 'USD'
        ];

        $response = $this->postJson('/api/v1/deposit', $deposit);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    public function test_transaction_controller_withdraw()
    {
        $account = Account::factory()->create();
        Balance::factory()->create(['account_id' => $account->id, 'currency' => 'USD', 'amount' => 1000]);

        $withdrawal = [
            'account_number' => $account->account_number,
            'amount' => 100,
            'currency' => 'USD'
        ];

        $response = $this->postJson('/api/v1/withdraw', $withdrawal);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    public function test_balance_show_fails_for_invalid_account()
    {
        $response = $this->getJson('/api/v1/balance?account_number=INVALID');

        $response->assertStatus(400)
                 ->assertJson(['success' => false]);
    }

    public function test_deposit_fails_for_invalid_account()
    {
        $deposit = [
            'account_number' => 'INVALID',
            'amount' => 100,
            'currency' => 'USD'
        ];

        $response = $this->postJson('/api/v1/deposit', $deposit);

        $response->assertStatus(400)
                 ->assertJson(['success' => false]);
    }

    public function test_withdraw_fails_for_invalid_account()
    {
        $withdrawal = [
            'account_number' => 'INVALID',
            'amount' => 100,
            'currency' => 'USD'
        ];

        $response = $this->postJson('/api/v1/withdraw', $withdrawal);

        $response->assertStatus(400)
                 ->assertJson(['success' => false]);
    }

    public function test_withdraw_fails_for_insufficient_funds()
    {
        $account = Account::factory()->create();
        Balance::factory()->create(['account_id' => $account->id, 'currency' => 'USD', 'amount' => 50]);

        $withdrawal = [
            'account_number' => $account->account_number,
            'amount' => 100,
            'currency' => 'USD'
        ];

        $response = $this->postJson('/api/v1/withdraw', $withdrawal);

        $response->assertStatus(400)
                 ->assertJson(['success' => false]);
    }

    public function test_balance_controller_show_without_currency()
    {
        $account = Account::factory()->create();
        Balance::factory()->create(['account_id' => $account->id, 'currency' => 'USD', 'amount' => 1000]);

        $response = $this->getJson("/api/v1/balance?account_number={$account->account_number}");

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }
}



