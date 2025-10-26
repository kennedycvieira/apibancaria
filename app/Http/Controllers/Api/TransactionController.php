<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class TransactionController extends Controller
{
    private TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Realiza um depósito
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deposit(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'account_number' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
                'currency' => 'required|string|size:3',
            ]);

            $result = $this->transactionService->deposit(
                $validated['account_number'],
                $validated['amount'],
                strtoupper($validated['currency'])
            );

            return response()->json($result, 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Realiza um saque
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function withdraw(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'account_number' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
                'currency' => 'required|string|size:3',
            ]);

            $result = $this->transactionService->withdraw(
                $validated['account_number'],
                $validated['amount'],
                strtoupper($validated['currency'])
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
