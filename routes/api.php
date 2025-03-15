<?php

use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('wallet')->group(function () {
    Route::get('/', [WalletController::class, 'getBalance']);
    Route::post('/deposit', [WalletController::class, 'deposit']);
    Route::post('/withdraw', [WalletController::class, 'withdraw']);

  
});
Route::prefix('transaction')->group(function () {
    Route::post('/', [TransactionController::class, 'transfer']);
    Route::get('/history', [TransactionController::class, 'history']);
 
});

Route::get('test', function() {
    dd(DB::connection('remote_db')->getConfig());

});