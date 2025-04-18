<?php

use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('wallet')->group(function () {
    Route::get('/getBalance', [WalletController::class, 'getBalance']);
    Route::post('/create', [WalletController::class, 'createWallet']);
    Route::post('/withdraw', [WalletController::class, 'withdraw']);

  
});
Route::prefix('transaction')->group(function () {
    Route::post('/', [TransactionController::class, 'transfer']);
    Route::post('/transfer', [TransactionController::class, 'transfer']); 
});

Route::get('/test', function() {
    dd(DB::connection('remote_db')->getConfig());

});
Route::post('test-post', function () {
    return response()->json(['message' => 'POST request received']);
});