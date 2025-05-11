<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use App\Services\WalletService;
class TransactionController extends Controller
{
    protected $walletService;
    protected $TransactionService;
    public function __construct(WalletService $walletService, TransactionService $transactionService)
    {
        $this->walletService = $walletService;
        $this->TransactionService = $transactionService;
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
            // typeofService
            $validate = $request->validate([
                'bill_id' => 'required|string|min:1',
                'source' => 'required|string|in:billing,reference'
            ]);
            $bill_id = $request->bill_id;
            if ($request->source == "billing")
            {
                $billingServiceUrl = env('BILLING_SERVICE_URL');
                
                $response = Http::get("{$billingServiceUrl}/api/bills//bill-details/{$bill_id}");

            }elseif($request->source == "source"){

                $referenceServiceUrl = env('REFERENCE_SERVICE_URL');
                
                $response = Http::get("{$referenceServiceUrl}/api/bills//bill-details/{$bill_id}");
            }

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
            
            $transactionResponse = $this->TransactionService->pay($billData, $user_id);
            if ($transactionResponse['success'] == false)
            {
                return response()->json(['error'=> $transactionResponse['message']], 400);
            }

            // send http request to billing service to change status

            return response()->json([
                "message" => "Your transaction was completed successfully.",
                "merchant_transaction" => $transactionResponse['merchant_transaction'],
                "fee_transaction" => $transactionResponse['fee_transaction']
            ], 200);
            

     
        } catch (\Exception $e) {
            return response()->json(['error' => 'Exception occurred while contacting billing service', 'details' => $e->getMessage()], 500);
        }
    }
}
