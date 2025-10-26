<?php

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\Balance;
use App\Models\Transaction;
use App\Services\BancoCentralService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Mockery;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $service;
    private BancoCentralService $bcService;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->bcService = Mockery::mock(BancoCentralService::class);
        $this->service = new TransactionService($this->bcService);
        
        $this->account = Account::create([
            'account_number' => '0001',
            'holder_name' => 'Test User',
            'status' => 'active',
        ]);
    }

    public function test_deposit_creates_transaction_and_updates_balance()
    {
        $result = $this->service->deposit('0001', 100.00, 'USD');

        $this->assertTrue($result['success']);
        $this->assertEquals(100.00, $result['new_balance']['amount']);
        $this->assertEquals('USD', $result['new_balance']['currency']);
        
        $this->assertDatabaseHas('transactions', [
            'account_id' => $this->account->id,
            'type' => 'deposit',
            'amount' => 100.00,
            'currency' => 'USD',
        ]);

        $this->assertDatabaseHas('balances', [
            'account_id' => $this->account->id,
            'currency' => 'USD',
            'amount' => 100.00,
        ]);
    }

    public function test_deposit_adds_to_existing_balance()
    {
        Balance::create([
            'account_id' => $this->account->id,
            'currency' => 'USD',
            'amount' => 50.00,
        ]);

        $result = $this->service->deposit('0001', 100.00, 'USD');

        $this->assertEquals(150.00, $result['new_balance']['amount']);
    }

    public function test_deposit_throws_exception_for_invalid_amount()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('O valor do depósito deve ser maior que zero');

        $this->service->deposit('0001', 0, 'USD');
    }

    public function test_deposit_throws_exception_for_inactive_account()
    {
        $this->account->update(['status' => 'inactive']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Conta inativa ou bloqueada');

        $this->service->deposit('0001', 100.00, 'USD');
    }

    public function test_deposit_throws_exception_for_nonexistent_account()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Conta não encontrada');

        $this->service->deposit('9999', 100.00, 'USD');
    }

    public function test_withdraw_with_sufficient_balance_in_same_currency()
    {
        Balance::create([
            'account_id' => $this->account->id,
            'currency' => 'USD',
            'amount' => 200.00,
        ]);

        $result = $this->service->withdraw('0001', 100.00, 'USD');

        $this->assertTrue($result['success']);
        $this->assertFalse($result['conversion_applied']);
        
        $balance = Balance::where('account_id', $this->account->id)
            ->where('currency', 'USD')
            ->first();
        
        $this->assertEquals(100.00, $balance->amount);
    }

    public function test_withdraw_with_currency_conversion()
    {
        Balance::create([
            'account_id' => $this->account->id,
            'currency' => 'BRL',
            'amount' => 500.00,
        ]);

        $this->bcService->shouldReceive('convertToBrl')
            ->with(500.00, 'BRL')
            ->andReturn(500.00);

        $this->bcService->shouldReceive('convertFromBrl')
            ->with(500.00, 'USD')
            ->andReturn(100.00);

        $result = $this->service->withdraw('0001', 100.00, 'USD');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['conversion_applied']);
        $this->assertNotEmpty($result['conversion_details']);
    }

    public function test_withdraw_throws_exception_for_insufficient_balance()
    {
        Balance::create([
            'account_id' => $this->account->id,
            'currency' => 'USD',
            'amount' => 50.00,
        ]);

        $this->bcService->shouldReceive('convertToBrl')
            ->andReturn(250.00);

        $this->bcService->shouldReceive('convertFromBrl')
            ->andReturn(50.00);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Saldo insuficiente');

        $this->service->withdraw('0001', 200.00, 'USD');
    }

    public function test_withdraw_throws_exception_for_invalid_amount()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('O valor do saque deve ser maior que zero');

        $this->service->withdraw('0001', -10, 'USD');
    }

    public function test_get_balance_returns_all_balances_when_no_currency_specified()
    {
        Balance::create([
            'account_id' => $this->account->id,
            'currency' => 'USD',
            'amount' => 100.00,
        ]);

        Balance::create([
            'account_id' => $this->account->id,
            'currency' => 'EUR',
            'amount' => 200.00,
        ]);

        $result = $this->service->getBalance('0001');

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['balances']);
    }

    public function test_get_balance_returns_converted_balance_for_specific_currency()
    {
        Balance::create([
            'account_id' => $this->account->id,
            'currency' => 'USD',
            'amount' => 100.00,
        ]);

        Balance::create([
            'account_id' => $this->account->id,
            'currency' => 'BRL',
            'amount' => 500.00,
        ]);

        $this->bcService->shouldReceive('convertToBrl')
            ->with(100.00, 'USD')
            ->andReturn(500.00);

        $this->bcService->shouldReceive('convertFromBrl')
            ->with(500.00, 'EUR')
            ->andReturn(100.00);

        $this->bcService->shouldReceive('convertToBrl')
            ->with(500.00, 'BRL')
            ->andReturn(500.00);

        $this->bcService->shouldReceive('convertFromBrl')
            ->with(500.00, 'EUR')
            ->andReturn(100.00);

        $result = $this->service->getBalance('0001', 'EUR');

        $this->assertTrue($result['success']);
        $this->assertEquals('EUR', $result['currency']);
        $this->assertArrayHasKey('total_balance', $result);
    }

    public function test_get_balance_throws_exception_for_nonexistent_account()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Conta não encontrada');

        $this->service->getBalance('9999');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
