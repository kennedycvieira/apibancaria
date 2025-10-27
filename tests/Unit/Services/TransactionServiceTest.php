<?php

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\Balance;
use App\Services\BancoCentralService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_withdraw_with_currency_conversion()
    {

        $account = Account::factory()->create();
        Balance::factory()->create(['account_id' => $account->id, 'currency' => 'USD', 'amount' => 100]);
        Balance::factory()->create(['account_id' => $account->id, 'currency' => 'EUR', 'amount' => 100]);

        $bancoCentralServiceMock = Mockery::mock(BancoCentralService::class);
        $bancoCentralServiceMock->shouldReceive('convertToBrl')->with(100, 'EUR')->andReturn(600.0);
        $bancoCentralServiceMock->shouldReceive('convertFromBrl')->with(600.0, 'USD')->andReturn(120.0);

        $transactionService = new TransactionService($bancoCentralServiceMock);


        $result = $transactionService->withdraw($account->account_number, 150, 'USD');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['conversion_applied']);
        $this->assertCount(2, $result['conversion_details']);

        $remainingBalances = $result['remaining_balances'];
        $usdBalance = collect($remainingBalances)->firstWhere('currency', 'USD');
        $eurBalance = collect($remainingBalances)->firstWhere('currency', 'EUR');

        $this->assertEquals(0, $usdBalance['amount']);
        $this->assertLessThan(100, $eurBalance['amount']);
    }
    public function test_deposit_with_negative_amount()
    {

        $account = Account::factory()->create();
        $bancoCentralServiceMock = Mockery::mock(BancoCentralService::class);
        $transactionService = new TransactionService($bancoCentralServiceMock);


        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('O valor do depósito deve ser maior que zero');


        $transactionService->deposit($account->account_number, -50, 'USD');
    }
     public function test_account_is_active_returns_false_for_inactive_account()
    {

        $account = Account::create([
            'account_number' => '0002',
            'holder_name' => 'Inactive User',
            'status' => 'inactive',
        ]);
        $bancoCentralServiceMock = Mockery::mock(BancoCentralService::class);
        $transactionService = new TransactionService($bancoCentralServiceMock);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Conta inativa ou bloqueada');

        $transactionService->getBalance($account->account_number);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_converted_balance()
    {
        $account = Account::factory()->create();
        Balance::factory()->create(['account_id' => $account->id, 'currency' => 'USD', 'amount' => 100]);
        Balance::factory()->create(['account_id' => $account->id, 'currency' => 'EUR', 'amount' => 50]);

        $bancoCentralServiceMock = Mockery::mock(BancoCentralService::class);
        $bancoCentralServiceMock->shouldReceive('convertToBrl')->with(50.0, 'EUR')->andReturn(300.0);
        $bancoCentralServiceMock->shouldReceive('convertFromBrl')->with(300.0, 'USD')->andReturn(60.0);

        $transactionService = new TransactionService($bancoCentralServiceMock);

        $result = $transactionService->getBalance($account->account_number, 'USD');

        $this->assertTrue($result['success']);
        $this->assertEquals('USD', $result['currency']);
        $this->assertEquals(160.0, $result['total_balance']);
        $this->assertCount(2, $result['details']);
    }

    public function test_deposit_with_positive_amount()
    {
        $account = Account::factory()->create();

        $bancoCentralServiceMock = Mockery::mock(BancoCentralService::class);
        $transactionService = new TransactionService($bancoCentralServiceMock);

        $result = $transactionService->deposit($account->account_number, 100, 'USD');

        $this->assertTrue($result['success']);
        $this->assertEquals('Depósito realizado com sucesso', $result['message']);
        $this->assertEquals(100, $result['new_balance']['amount']);
    }

    public function test_withdraw_with_sufficient_balance()
    {
        $account = Account::factory()->create();
        Balance::factory()->create(['account_id' => $account->id, 'currency' => 'USD', 'amount' => 200]);

        $bancoCentralServiceMock = Mockery::mock(BancoCentralService::class);
        $transactionService = new TransactionService($bancoCentralServiceMock);

        $result = $transactionService->withdraw($account->account_number, 100, 'USD');

        $this->assertTrue($result['success']);
        $this->assertFalse($result['conversion_applied']);
        $this->assertEquals(100, $result['remaining_balances'][0]['amount']);
    }

    public function test_withdraw_with_insufficient_balance()
    {
        $account = Account::factory()->create();
        Balance::factory()->create(['account_id' => $account->id, 'currency' => 'USD', 'amount' => 50]);

        $bancoCentralServiceMock = Mockery::mock(BancoCentralService::class);
        $transactionService = new TransactionService($bancoCentralServiceMock);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Saldo insuficiente para realizar o saque');

        $transactionService->withdraw($account->account_number, 100, 'USD');
    }

    public function test_get_active_account_not_found()
    {
        $bancoCentralServiceMock = Mockery::mock(BancoCentralService::class);
        $transactionService = new TransactionService($bancoCentralServiceMock);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Conta não encontrada');

        $transactionService->getBalance('non-existent-account');
    }

    public function test_deposit_creates_new_balance()
    {
        $account = Account::factory()->create();

        $bancoCentralServiceMock = Mockery::mock(BancoCentralService::class);
        $transactionService = new TransactionService($bancoCentralServiceMock);

        $result = $transactionService->deposit($account->account_number, 100, 'EUR');

        $this->assertTrue($result['success']);
        $this->assertEquals(100, $result['new_balance']['amount']);
        $this->assertEquals('EUR', $result['new_balance']['currency']);
    }

    public function test_withdraw_with_negative_amount()
    {
        $account = Account::factory()->create();
        $bancoCentralServiceMock = Mockery::mock(BancoCentralService::class);
        $transactionService = new TransactionService($bancoCentralServiceMock);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('O valor do saque deve ser maior que zero');

        $transactionService->withdraw($account->account_number, -50, 'USD');
    }

    public function test_withdraw_with_zero_amount()
    {
        $account = Account::factory()->create();
        $bancoCentralServiceMock = Mockery::mock(BancoCentralService::class);
        $transactionService = new TransactionService($bancoCentralServiceMock);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('O valor do saque deve ser maior que zero');

        $transactionService->withdraw($account->account_number, 0, 'USD');
    }

    public function test_get_balance_without_currency()
    {
        $account = Account::factory()->create();
        Balance::factory()->create(['account_id' => $account->id, 'currency' => 'USD', 'amount' => 100]);
        Balance::factory()->create(['account_id' => $account->id, 'currency' => 'EUR', 'amount' => 50]);

        $bancoCentralServiceMock = Mockery::mock(BancoCentralService::class);
        $transactionService = new TransactionService($bancoCentralServiceMock);

        $result = $transactionService->getBalance($account->account_number);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['balances']);
    }

    public function test_withdraw_with_partial_balance_and_no_other_currencies()
    {
        $account = Account::factory()->create();
        Balance::factory()->create(['account_id' => $account->id, 'currency' => 'USD', 'amount' => 50]);

        $bancoCentralServiceMock = Mockery::mock(BancoCentralService::class);
        $transactionService = new TransactionService($bancoCentralServiceMock);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Saldo insuficiente para realizar o saque');

        $transactionService->withdraw($account->account_number, 100, 'USD');
    }

    public function test_get_converted_balance_with_no_balances()
    {
        $account = Account::factory()->create();

        $bancoCentralServiceMock = Mockery::mock(BancoCentralService::class);
        $transactionService = new TransactionService($bancoCentralServiceMock);

        $result = $transactionService->getBalance($account->account_number, 'USD');

        $this->assertTrue($result['success']);
        $this->assertEquals('USD', $result['currency']);
        $this->assertEquals(0, $result['total_balance']);
        $this->assertCount(0, $result['details']);
    }

    public function test_withdraw_with_conversion_and_remaining_amount_is_small()
    {
        $account = Account::factory()->create();
        Balance::factory()->create(['account_id' => $account->id, 'currency' => 'USD', 'amount' => 100]);
        Balance::factory()->create(['account_id' => $account->id, 'currency' => 'EUR', 'amount' => 0.01]);

        $bancoCentralServiceMock = Mockery::mock(BancoCentralService::class);
        $bancoCentralServiceMock->shouldReceive('convertToBrl')->with(0.01, 'EUR')->andReturn(0.06);
        $bancoCentralServiceMock->shouldReceive('convertFromBrl')->with(0.06, 'USD')->andReturn(0.01);

        $transactionService = new TransactionService($bancoCentralServiceMock);

        $result = $transactionService->withdraw($account->account_number, 100.01, 'USD');

        $this->assertTrue($result['success']);
    }
}