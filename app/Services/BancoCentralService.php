<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Exception;

class BancoCentralService
{
    private const BASE_URL = 'https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata';
    private const CACHE_TTL = 3600; // 1 hora

   public function listaMoedas()
    {
      $url  = 'https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/Moedas?$top=100&$format=json';
      
      $response = Http::timeout(10)->get($url); 

      $data = json_decode($response->body(), true);
      $currencyList = $data['value']; // Remove o [0]
      $simbolos = array_column($currencyList, 'simbolo');
      return $simbolos;
    }
    
    public function verificaMoedaValida($currency)
    {
      $moedas = $this->listaMoedas();
      return in_array($currency, $moedas);
    }
    /**
     * Obtém a cotação PTAX de fechamento para uma moeda específica
     *
     * @param string $currency Código da moeda (ISO 4217)
     * @param string|null $date Data no formato Y-m-d (padrão: hoje)
     * @return array ['buy' => float, 'sell' => float]
     * @throws Exception
     */
    public function getPtaxRate(string $currency, ?string $date = null): array
    {
        if (!self::verificaMoedaValida($currency)) {
            throw new Exception("Moeda inválida: {$currency}");
        }

        // BRL não precisa de conversão
        if ($currency === 'BRL') {
            return ['buy' => 1.0, 'sell' => 1.0];
        }

        $date = $date ?? Carbon::now()->format('m-d-Y');
        $cacheKey = "ptax_{$currency}_{$date}";


        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($currency, $date) {
            try {
                $url = self::BASE_URL . "/CotacaoMoedaPeriodoFechamento(codigoMoeda=@codigoMoeda,dataInicialCotacao=@dataInicialCotacao,dataFinalCotacao=@dataFinalCotacao)";
                $url .=  "?@codigoMoeda='{$currency}'&@dataInicialCotacao='{$date}'&@dataFinalCotacao='{$date}'&\$format=json";
                $response = Http::timeout(10)->get($url);

                if (!$response->successful()) {
                    throw new Exception("Erro ao consultar API do Banco Central: " . $response->status());
                }

                $data = $response->json();

                if (empty($data['value'])) {
                    throw new Exception("Cotação não encontrada para {$currency} na data {$date}");
                }

                // Pega apenas a cotação de fechamento PTAX (último registro do dia)
                $closingRate = collect($data['value'])->last();

                return [
                    'buy' => (float) $closingRate['cotacaoCompra'],
                    'sell' => (float) $closingRate['cotacaoVenda'],
                ];
            } catch (Exception $e) {
                // Se falhar, tenta dia útil anterior (recursivo até 5 dias)
                return $this->tryPreviousBusinessDay($currency, $date, 0);
            }
        });
    }

    /**
     * Tenta obter cotação de dias anteriores (para fins de semana/feriados)
     */
    private function tryPreviousBusinessDay(string $currency, string $date, int $attempts): array
    {
        if ($attempts >= 5) {
            throw new Exception("Não foi possível obter cotação PTAX para {$currency}");
        }

        $previousDate = Carbon::createFromFormat('m-d-Y', $date)
            ->subDay()
            ->format('m-d-Y');

        try {
            $url = self::BASE_URL . "/CotacaoMoedaPeriodoFechamento(codigoMoeda=@codigoMoeda,dataInicialCotacao=@dataInicialCotacao,dataFinalCotacao=@dataFinalCotacao)";
            $url .=  "?@codigoMoeda='{$currency}'&@dataInicialCotacao='{$previousDate}'&@dataFinalCotacao='{$previousDate}'&\$format=json";
                

            $response = Http::timeout(10)->get($url);

            if (!$response->successful() || empty($response->json()['value'])) {
                return $this->tryPreviousBusinessDay($currency, $previousDate, $attempts + 1);
            }

            $data = $response->json();
            $closingRate = collect($data['value'])->last();

            return [
                'buy' => (float) $closingRate['cotacaoCompra'],
                'sell' => (float) $closingRate['cotacaoVenda'],
            ];
        } catch (Exception $e) {
            return $this->tryPreviousBusinessDay($currency, $previousDate, $attempts + 1);
        }
    }

    /**
     * Converte valor de uma moeda para BRL usando taxa de compra
     */
    public function convertToBrl(float $amount, string $fromCurrency): float
    {
        if ($fromCurrency === 'BRL') {
            return $amount;
        }

        $rates = $this->getPtaxRate($fromCurrency);
        return $amount * $rates['buy'];
    }

    /**
     * Converte valor de BRL para outra moeda usando taxa de venda
     */
    public function convertFromBrl(float $amount, string $toCurrency): float
    {
        if ($toCurrency === 'BRL') {
            return $amount;
        }

        $rates = $this->getPtaxRate($toCurrency);
        return $amount / $rates['sell'];
    }
}