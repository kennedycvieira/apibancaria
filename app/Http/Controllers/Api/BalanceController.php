<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class BalanceController extends Controller
{
    private TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Consulta o saldo da conta
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'account_number' => 'required|string',
                'currency' => 'nullable|string|size:3',
            ]);

            $currency = isset($validated['currency']) 
                ? strtoupper($validated['currency']) 
                : null;

            $result = $this->transactionService->getBalance(
                $validated['account_number'],
                $currency
            );

            return response()->json($result, 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
