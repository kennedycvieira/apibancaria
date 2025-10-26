<?php

namespace Tests\Unit\Services;

use App\Services\BancoCentralService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BancoCentralServiceTest extends TestCase
{
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getMockBuilder(BancoCentralService::class)
            ->onlyMethods(['listaMoedas'])
            ->getMock();

        $this->service->method('listaMoedas')->willReturn(['USD', 'BRL']);

        Cache::flush();
    }

    public function test_get_ptax_rate_for_brl_returns_one()
    {
        $rate = $this->service->getPtaxRate('BRL');

        $this->assertEquals(1.0, $rate['buy']);
        $this->assertEquals(1.0, $rate['sell']);
    }

    public function test_get_ptax_rate_returns_valid_rates()
    {
        Http::fake([
            '*' => Http::response([
                'value' => [
                    [
                        'cotacaoCompra' => 5.2489,
                        'cotacaoVenda' => 5.2495,
                    ]
                ]
            ], 200)
        ]);

        $rate = $this->service->getPtaxRate('USD');

        $this->assertEquals(5.2489, $rate['buy']);
        $this->assertEquals(5.2495, $rate['sell']);
    }

    public function test_get_ptax_rate_uses_cache()
    {
        Http::fake([
            '*' => Http::response([
                'value' => [
                    [
                        'cotacaoCompra' => 5.2489,
                        'cotacaoVenda' => 5.2495,
                    ]
                ]
            ], 200)
        ]);

        // Primeira chamada
        $rate1 = $this->service->getPtaxRate('USD');
        
        // Segunda chamada deve usar cache
        $rate2 = $this->service->getPtaxRate('USD');

        $this->assertEquals($rate1, $rate2);
        Http::assertSentCount(1); // Apenas uma requisição HTTP
    }

    public function test_get_ptax_rate_throws_exception_when_no_data()
    {
        Http::fake([
            '*' => Http::sequence()
                ->push(['value' => []], 200)
                ->push(['value' => []], 200)
                ->push(['value' => []], 200)
                ->push(['value' => []], 200)
                ->push(['value' => []], 200)
                ->push(['value' => []], 200)
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Não foi possível obter cotação PTAX');

        $this->service->getPtaxRate('USD');
    }

    public function test_convert_to_brl_with_brl_returns_same_amount()
    {
        $result = $this->service->convertToBrl(100, 'BRL');
        $this->assertEquals(100, $result);
    }

    public function test_convert_to_brl_with_foreign_currency()
    {
        Http::fake([
            '*' => Http::response([
                'value' => [
                    [
                        'cotacaoCompra' => 5.0,
                        'cotacaoVenda' => 5.1,
                    ]
                ]
            ], 200)
        ]);

        $result = $this->service->convertToBrl(100, 'USD');
        $this->assertEquals(500.0, $result);
    }

    public function test_convert_from_brl_with_brl_returns_same_amount()
    {
        $result = $this->service->convertFromBrl(100, 'BRL');
        $this->assertEquals(100, $result);
    }

    public function test_convert_from_brl_to_foreign_currency()
    {
        Http::fake([
            '*' => Http::response([
                'value' => [
                    [
                        'cotacaoCompra' => 5.0,
                        'cotacaoVenda' => 5.1,
                    ]
                ]
            ], 200)
        ]);

        $result = $this->service->convertFromBrl(510, 'USD');
        $this->assertEquals(100.0, $result);
    }
}
