<?php

use App\Http\Controllers\IpController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
Route::prefix('/wallet')->group(function () {
    Route::get('/getBalance', [WalletController::class, 'getBalance']);
    Route::post('/wallet', [WalletController::class, 'createWallet']);
    Route::post('/deposit', [WalletController::class, 'deposit']);
    Route::post('/withdraw', [WalletController::class, 'withdraw']);
    Route::get('/by-user', [WalletController::class, 'getWalletByUserId']);
});
Route::prefix('/transaction')->group(function () {
    Route::post('/transfer', [TransactionController::class, 'transfer']);
    Route::get('/history', [TransactionController::class, 'history']);
    Route::post('/pay', [TransactionController::class, 'pay']);
 
});

Route::post('/test', function(Request $request) {
    // dd(DB::connection('remote_db')->getConfig()); // This line is commented out and was likely used for debugging database connection details.

    // $userId = $request->header('x-jwt-claim-user_id'); // Retrieve the user_id from the request headers.
    // return response()->json(['user_id' => $userId]);
    $headers = $request->headers->all();
    $header = $request->header('x-consumer-id');
    // Return the headers as a JSON response
    return response()->json($headers);
    // return "here";

    
});
Route::any('/debug-post', function (Request $request) {
    return response()->json([
        'method' => $request->method(),
        'request_method_raw' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'headers' => $request->headers->all(),
        'body' => $request->getContent(),
    ]);
});



Route::post('/ip-address', [IpController::class, 'store']);



