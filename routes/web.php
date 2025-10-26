<?php

use Illuminate\Support\Facades\Route;
use App\Services\BancoCentralService;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-banco-central', function () {
    try {
        $service = new BancoCentralService();
        //$rate = $service->getPtaxRate('AUD', '15-10-2025');
        $currencies = $service->listaMoedas();
        //return deve ser um json com as informações de rate e currencies
        return response()->json([$currencies]);
    } catch (Exception $e) {    
        return response()->json(['error' => $e->getMessage()], 500);
    }
});
