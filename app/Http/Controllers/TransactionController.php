<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use App\Services\WalletService;
class TransactionController extends Controller
{
    protected $walletService;
    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }
    /**
     * transfer money from one wallet to another
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function transfer(Request $request)
    {
        $user_id = $request->header('X-User-ID');
        $request->validate([
            'receiver_id' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'type' => 'required|string|in:charge,refund',
            'description' => 'required|string'
        ]);

        $debit_from = Wallet::where('user_id', $user_id)->first();
        $credit_to = Wallet::where('user_id', $$request->receiver_id)->first();
        if (! $debit_from || ! $credit_to )
        {
            return response()->json(['error' => 'Invalid recipient'], 404);
        }

        DB::beginTransaction();
        try{
            $debit_from = $debit_from->decrement('balance', $request->amount);
            $credit_to = $credit_to->increment('balance', $request->amount);

            Transaction::create([
                'id' => Str::uuid(),
                'debit_from' => $debit_from,
                'credit_to' => $credit_to,
                'amount' => $request->amount,
                'type' => $request->type,
             
            ]);

            DB::commit();
            return response()->json(['message' => 'Transfer successful']);

        }catch(\Exception $e){
            DB::rollBack();
            return response()->json(['error' => 'Transaction failed'], 500);
        }

    }

    public function history(Request $request)
    {
        $user_id = $request->header('X-User-ID');
        $wallet = Wallet::where('user_id', $user_id)->first();
        $transactions = Transaction::where('debit_from', $wallet->id)
                        ->orwhere('credit_to', $wallet->id)
                        ->order('created_at', 'desc')
                        ->get();
            
        return response()->json($transactions);
    }

    public function pay(Request $request)
    {
        try {
            $user_id = $request->header('X-User-ID');
            $validate = $request->validate([
                'bill_id' => 'required|string|min:1',
            ]);
            $billId = $request->bill_id;
            $billingServiceUrl = env('BILLING_SERVICE_URL');
            $response = Http::get("{$billingServiceUrl}/bills/{$billId}");

            if ($response->failed()) {
                return response()->json(['error' => 'Failed to fetch bill from billing service'], 502);
            }

            $billData = $response->json();
            $existingUserWallet = $this->walletService->userHasWallet($user_id);
            $existingMerchantWallet = $this->walletService->userHasWallet($billData['merchant_id']);
            if(!$existingUserWallet || !$existingMerchantWallet){
                return response()->json(['error' => 'User or merchant wallet not found'], 404);
            }
            
            // fraud detection

            

     
        } catch (\Exception $e) {
            return response()->json(['error' => 'Exception occurred while contacting billing service', 'details' => $e->getMessage()], 500);
        }
    }
}
