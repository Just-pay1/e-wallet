<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use App\Services\WalletService;

class WalletController extends Controller
{
    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function createWallet(Request $request)
    {
        $user_id = $request->header('X-User-ID');
        $wallet = Wallet::create([
            'user_id' => $user_id,
            'balance' => 0.0,
            'currency' => 'EGP'
        ]);

        return response()->json([
            'message' => 'Wallet created successfully',
            'wallet' => $wallet,
        ], 200);

    }

    public function deposit(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1'
        ]);

        $user_id = $request->header('X-User-ID');
        $wallet = Wallet::where('user_id', $user_id)->firstOrFail();
        $wallet->balance += $request->amount;
        $wallet->save();
        
        return response()->json(['message' => 'Deposit successful', 'balance' => $wallet->balance]);

    }

    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1'
        ]);

        $user_id = $request->header('X-User-ID');
        $wallet = Wallet::where('user_id', $user_id)->firstOrFail();

        if (!$wallet || $wallet->balance < $request->amount) {
            return response()->json(['error' => 'Insufficient funds'], 400);
        }

        $wallet->decrement('balance', $request->amount);

        return response()->json(['message' => 'Withdrawal successful', 'balance' => $wallet->balance]);
    }
    public function getBalance(Request $request)
    {
        $user_id = $request->header('X-User-ID');
        $wallet = Wallet::where('user_id', $user_id)->first();
        if (!$wallet) {
            return response()->json(['message' => 'Wallet Not Found'], 404);
        }
        return response()->json(['Balance' => $wallet->balance], 200);
    }

    /**
     * Get the wallet by user ID from the request header.
     */
    public function getWalletByUserId(Request $request)
    {
        $user_id = $request->header('X-User-ID');
        $walletId = $this->walletService->getWalletIdByUserId($user_id);
        if (!$walletId) {
            return response()->json(['message' => 'Wallet Not Found'], 404);
        }
        return response()->json(['walletId' => $walletId], 200);
    }
}
