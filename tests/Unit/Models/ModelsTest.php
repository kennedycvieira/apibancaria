<?php

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\Balance;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_has_balances_relationship()
    {
        $account = Account::create([
            'account_number' => '0001',
            'holder_name' => 'Test User',
            'status' => 'active',
        ]);

        Balance::create([
            'account_id' => $account->id,
            'currency' => 'USD',
            'amount' => 100.00,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $account->balances);
        $this->assertCount(1, $account->balances);
    }

    public function test_account_has_transactions_relationship()
    {
        $account = Account::create([
            'account_number' => '0001',
            'holder_name' => 'Test User',
            'status' => 'active',
        ]);

        Transaction::create([
            'account_id' => $account->id,
            'type' => 'deposit',
            'currency' => 'USD',
            'amount' => 100.00,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $account->transactions);
        $this->assertCount(1, $account->transactions);
    }

    public function test_account_is_active_returns_true_for_active_account()
    {
        $account = Account::create([
            'account_number' => '0001',
            'holder_name' => 'Test User',
            'status' => 'active',
        ]);

        $this->assertTrue($account->isActive());
    }

    public function test_account_is_active_returns_false_for_inactive_account()
    {
        $account = Account::create([
            'account_number' => '0001',
            'holder_name' => 'Test User',
            'status' => 'inactive',
        ]);

        $this->assertFalse($account->isActive());
    }

    public function test_account_get_balance_returns_correct_amount()
    {
        $account = Account::create([
            'account_number' => '0001',
            'holder_name' => 'Test User',
            'status' => 'active',
        ]);

        Balance::create([
            'account_id' => $account->id,
            'currency' => 'USD',
            'amount' => 150.50,
        ]);

        $this->assertEquals(150.50, $account->getBalance('USD'));
    }

    public function test_account_get_balance_returns_zero_for_nonexistent_currency()
    {
        $account = Account::create([
            'account_number' => '0001',
            'holder_name' => 'Test User',
            'status' => 'active',
        ]);

        $this->assertEquals(0.0, $account->getBalance('EUR'));
    }

    public function test_account_get_all_balances_returns_only_positive_balances()
    {
        $account = Account::create([
            'account_number' => '0001',
            'holder_name' => 'Test User',
            'status' => 'active',
        ]);

        Balance::create([
            'account_id' => $account->id,
            'currency' => 'USD',
            'amount' => 100.00,
        ]);

        Balance::create([
            'account_id' => $account->id,
            'currency' => 'EUR',
            'amount' => 0.00,
        ]);

        $balances = $account->getAllBalances();
        
        $this->assertCount(1, $balances);
        $this->assertEquals('USD', $balances[0]['currency']);
    }

    public function test_balance_add_amount_increases_balance()
    {
        $account = Account::create([
            'account_number' => '0001',
            'holder_name' => 'Test User',
            'status' => 'active',
        ]);

        $balance = Balance::create([
            'account_id' => $account->id,
            'currency' => 'USD',
            'amount' => 100.00,
        ]);

        $balance->addAmount(50.00);

        $this->assertEquals(150.00, $balance->fresh()->amount);
    }

    public function test_balance_subtract_amount_decreases_balance()
    {
        $account = Account::create([
            'account_number' => '0001',
            'holder_name' => 'Test User',
            'status' => 'active',
        ]);

        $balance = Balance::create([
            'account_id' => $account->id,
            'currency' => 'USD',
            'amount' => 100.00,
        ]);

        $balance->subtractAmount(30.00);

        $this->assertEquals(70.00, $balance->fresh()->amount);
    }

    public function test_balance_has_sufficient_balance_returns_true()
    {
        $account = Account::create([
            'account_number' => '0001',
            'holder_name' => 'Test User',
            'status' => 'active',
        ]);

        $balance = Balance::create([
            'account_id' => $account->id,
            'currency' => 'USD',
            'amount' => 100.00,
        ]);

        $this->assertTrue($balance->hasSufficientBalance(50.00));
    }

    public function test_balance_has_sufficient_balance_returns_false()
    {
        $account = Account::create([
            'account_number' => '0001',
            'holder_name' => 'Test User',
            'status' => 'active',
        ]);

        $balance = Balance::create([
            'account_id' => $account->id,
            'currency' => 'USD',
            'amount' => 100.00,
        ]);

        $this->assertFalse($balance->hasSufficientBalance(150.00));
    }

    public function test_balance_belongs_to_account()
    {
        $account = Account::create([
            'account_number' => '0001',
            'holder_name' => 'Test User',
            'status' => 'active',
        ]);

        $balance = Balance::create([
            'account_id' => $account->id,
            'currency' => 'USD',
            'amount' => 100.00,
        ]);

        $this->assertInstanceOf(Account::class, $balance->account);
        $this->assertEquals($account->id, $balance->account->id);
    }

    public function test_transaction_belongs_to_account()
    {
        $account = Account::create([
            'account_number' => '0001',
            'holder_name' => 'Test User',
            'status' => 'active',
        ]);

        $transaction = Transaction::create([
            'account_id' => $account->id,
            'type' => 'deposit',
            'currency' => 'USD',
            'amount' => 100.00,
        ]);

        $this->assertInstanceOf(Account::class, $transaction->account);
        $this->assertEquals($account->id, $transaction->account->id);
    }

    public function test_transaction_casts_conversion_details_to_array()
    {
        $account = Account::create([
            'account_number' => '0001',
            'holder_name' => 'Test User',
            'status' => 'active',
        ]);

        $details = ['from' => 'USD', 'to' => 'EUR', 'rate' => 1.2];

        $transaction = Transaction::create([
            'account_id' => $account->id,
            'type' => 'withdrawal',
            'currency' => 'EUR',
            'amount' => 100.00,
            'conversion_details' => $details,
        ]);

        $this->assertIsArray($transaction->conversion_details);
        $this->assertEquals($details, $transaction->conversion_details);
    }
}
