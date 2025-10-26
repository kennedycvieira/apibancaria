<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Balance;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Exception;

class TransactionService
{
    private BancoCentralService $bcService;

    public function __construct(BancoCentralService $bcService)
    {
        $this->bcService = $bcService;
    }

    /**
     * Realiza um depósito
     */
    public function deposit(string $accountNumber, float $amount, string $currency): array
    {
        if ($amount <= 0) {
            throw new Exception('O valor do depósito deve ser maior que zero');
        }

        return DB::transaction(function () use ($accountNumber, $amount, $currency) {
            $account = $this->getActiveAccount($accountNumber);

            // Busca ou cria o saldo para a moeda
            $balance = Balance::firstOrCreate(
                [
                    'account_id' => $account->id,
                    'currency' => $currency,
                ],
                ['amount' => 0]
            );

            $balance->addAmount($amount);

            // Registra a transação
            $transaction = Transaction::create([
                'account_id' => $account->id,
                'type' => Transaction::TYPE_DEPOSIT,
                'currency' => $currency,
                'amount' => $amount,
                'description' => "Depósito de {$amount} {$currency}",
            ]);

            return [
                'success' => true,
                'message' => 'Depósito realizado com sucesso',
                'transaction' => [
                    'id' => $transaction->id,
                    'type' => 'deposit',
                    'account_number' => $accountNumber,
                    'currency' => $currency,
                    'amount' => $amount,
                    'date_time' => $transaction->created_at->format('Y-m-d H:i:s'),
                ],
                'new_balance' => [
                    'currency' => $currency,
                    'amount' => (float) $balance->amount,
                ],
                'bcinfo'
            ];
        });
    }

    /**
     * Realiza um saque
     */
    public function withdraw(string $accountNumber, float $amount, string $currency): array
    {
        if ($amount <= 0) {
            throw new Exception('O valor do saque deve ser maior que zero');
        }

        return DB::transaction(function () use ($accountNumber, $amount, $currency) {
            $account = $this->getActiveAccount($accountNumber);

            // Verifica saldo disponível na moeda solicitada
            $requestedBalance = $account->getBalance($currency);
            $conversionDetails = [];

            if ($requestedBalance >= $amount) {
                // Tem saldo suficiente na moeda solicitada
                $balance = Balance::where('account_id', $account->id)
                    ->where('currency', $currency)
                    ->first();
                
                $balance->subtractAmount($amount);
            } else {
                // Precisa converter de outras moedas
                $conversionDetails = $this->performCurrencyConversion(
                    $account,
                    $amount,
                    $currency,
                    $requestedBalance
                );
            }

            // Registra a transação
            $transaction = Transaction::create([
                'account_id' => $account->id,
                'type' => Transaction::TYPE_WITHDRAWAL,
                'currency' => $currency,
                'amount' => $amount,
                'conversion_details' => empty($conversionDetails) ? null : $conversionDetails,
                'description' => "Saque de {$amount} {$currency}",
            ]);

            return [
                'success' => true,
                'message' => 'Saque realizado com sucesso',
                'transaction' => [
                    'id' => $transaction->id,
                    'type' => 'withdrawal',
                    'account_number' => $accountNumber,
                    'currency' => $currency,
                    'amount' => $amount,
                    'date_time' => $transaction->created_at->format('Y-m-d H:i:s'),
                ],
                'conversion_applied' => !empty($conversionDetails),
                'conversion_details' => $conversionDetails,
                'remaining_balances' => $account->getAllBalances(),
            ];
        });
    }

    /**
     * Realiza conversão de moedas para saque
     */
    private function performCurrencyConversion(
        Account $account,
        float $requiredAmount,
        string $targetCurrency,
        float $availableInTarget
    ): array {
        $conversions = [];
        $remainingAmount = $requiredAmount - $availableInTarget;

        // Usa o saldo disponível na moeda alvo primeiro
        if ($availableInTarget > 0) {
            $balance = Balance::where('account_id', $account->id)
                ->where('currency', $targetCurrency)
                ->first();
            $balance->subtractAmount($availableInTarget);

            $conversions[] = [
                'from_currency' => $targetCurrency,
                'from_amount' => $availableInTarget,
                'to_currency' => $targetCurrency,
                'to_amount' => $availableInTarget,
                'rate' => 1.0,
            ];
        }

        // Busca saldos de outras moedas
        $otherBalances = Balance::where('account_id', $account->id)
            ->where('currency', '!=', $targetCurrency)
            ->where('amount', '>', 0)
            ->get();

        foreach ($otherBalances as $balance) {
            if ($remainingAmount <= 0.01) {
                break;
            }

            // Converte para BRL primeiro (taxa de compra)
            $brlAmount = $this->bcService->convertToBrl(
                (float) $balance->amount,
                $balance->currency
            );

            // Converte de BRL para moeda alvo (taxa de venda)
            $convertedAmount = $this->bcService->convertFromBrl(
                $brlAmount,
                $targetCurrency
            );

            $amountToUse = min($convertedAmount, $remainingAmount);
            $originalAmountUsed = ($amountToUse / $convertedAmount) * (float) $balance->amount;

            $conversions[] = [
                'from_currency' => $balance->currency,
                'from_amount' => round($originalAmountUsed, 2),
                'intermediate_brl' => round($brlAmount * ($originalAmountUsed / (float) $balance->amount), 2),
                'to_currency' => $targetCurrency,
                'to_amount' => round($amountToUse, 2),
            ];

            $balance->subtractAmount($originalAmountUsed);
            $remainingAmount -= $amountToUse;
        }

        if ($remainingAmount > 0.01) {
            throw new Exception('Saldo insuficiente para realizar o saque');
        }

        return $conversions;
    }

    /**
     * Obtém o saldo da conta
     */
    public function getBalance(string $accountNumber, ?string $currency = null): array
    {
        $account = $this->getActiveAccount($accountNumber);

        if ($currency) {
            // Saldo em uma moeda específica (convertendo tudo)
            return $this->getConvertedBalance($account, $currency);
        }

        // Saldo em todas as moedas
        return [
            'success' => true,
            'account_number' => $accountNumber,
            'balances' => $account->getAllBalances(),
        ];
    }

    /**
     * Obtém saldo convertido para uma moeda específica
     */
    private function getConvertedBalance(Account $account, string $targetCurrency): array
    {
        $balances = Balance::where('account_id', $account->id)
            ->where('amount', '>', 0)
            ->get();

        $totalInTarget = 0;
        $details = [];

        foreach ($balances as $balance) {
            if ($balance->currency === $targetCurrency) {
                $converted = (float) $balance->amount;
            } else {
                // Converte para BRL e depois para moeda alvo
                $brlAmount = $this->bcService->convertToBrl(
                    (float) $balance->amount,
                    $balance->currency
                );
                $converted = $this->bcService->convertFromBrl($brlAmount, $targetCurrency);
            }

            $totalInTarget += $converted;

            $details[] = [
                'currency' => $balance->currency,
                'amount' => (float) $balance->amount,
                'converted_to_' . strtolower($targetCurrency) => round($converted, 2),
            ];
        }

        return [
            'success' => true,
            'account_number' => $account->account_number,
            'currency' => $targetCurrency,
            'total_balance' => round($totalInTarget, 2),
            'details' => $details,
        ];
    }

    /**
     * Obtém uma conta ativa
     */
    private function getActiveAccount(string $accountNumber): Account
    {
        $account = Account::where('account_number', $accountNumber)->first();

        if (!$account) {
            throw new Exception('Conta não encontrada');
        }

        if (!$account->isActive()) {
            throw new Exception('Conta inativa ou bloqueada');
        }

        return $account;
    }
}