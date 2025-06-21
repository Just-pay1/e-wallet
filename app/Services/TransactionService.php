<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Http\Client\Promise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class TransactionService
{
    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }
    
    public function pay(Request $request)
    {
        $user_id = $request->header('X-User-ID');
        if (!$user_id) {
            return ['success' => false, 'message' => 'Unauthorized', 'status_code' => 401];
        }

        $validator = Validator::make($request->all(), [
            'bill_id' => 'required|string|min:1',
            'source' => 'required|string|in:billing,reference',
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'message' => $validator->errors()->first(), 'status_code' => 422];
        }

        $validated = $validator->validated();
        $bill_id = $validated['bill_id'];
        $source = $validated['source'];

        if ($source === 'billing') {
            $billingServiceUrl = env('BILLING_SERVICE_URL');
            $billPromise = Http::async()->get("{$billingServiceUrl}/api/bills/bill-details?bill_id={$bill_id}");
        } else {
            $referenceServiceUrl = env('REFERENCE_SERVICE_URL');
            $billPromise = Http::async()->get("{$referenceServiceUrl}/api/billing/{$bill_id}");
        }
        
        $userWallet = Wallet::where('user_id', $user_id)->first();
        if (! $userWallet) {
            return ['success' => false, 'message' => 'User wallet not found', 'status_code' => 404];
        }

        $response = $billPromise->wait();

        if ($response->failed()) {
            return ['success' => false, 'message' => "Failed to fetch bill from {$source} service", 'status_code' => 502];
        }

        if ($response->status() >= 500) {
            return ['success' => false, 'message' => $response['error'] ?? 'Error from external service', 'status_code' => $response->status()];
        }

        $billData = $response->json()['data'];

        $merchantWallet = Wallet::where('user_id', $billData['merchant_id'])->first();
        if (! $merchantWallet) {
            return ['success' => false, 'message' => 'Merchant not found', 'status_code' => 404];
        }
        
        // $fraudPromise = null;
        // $fraudServiceUrl = env('FRAUD_SERVICE_URL');
        // if ($fraudServiceUrl) {
        //     $fraudPromise = Http::async()->post("{$fraudServiceUrl}/api/fraud/check", [
        //         "trans_date_trans_time" => Carbon::now()->format('Y-m-d H:i:s'),
        //         "cc_num" => $userWallet->id,
        //         "category" => "electronics",
        //         "amt" => $billData['amount'],
        //         "lat" => 40.7128,
        //         "long" => -74.0060,
        //         "merch_lat" => 40.7306,
        //         "merch_long" => -73.9352
        //     ]);
        // }

        $mainWallet = Wallet::where('id', env('JUST_PAY_WALLET_ID'))->firstOrFail();
        
        $netAmount = $billData['amount'];
        $fees = $billData['fee'];
        if ($userWallet->balance < ($netAmount + $fees)) {
            return ['success' => false, 'message' => 'Insufficient funds'];
        }
        DB::beginTransaction();
        try {
            $userWallet->decrement('balance', $netAmount + $fees);
            $merchantWallet->increment('balance', $netAmount);
            $mainWallet->increment('balance', $fees);

            $merchantTransaction = Transaction::create([
                'id' => Str::uuid(),
                'debit_from' => $userWallet->id,
                'credit_to' => $merchantWallet->id,
                'amount' => $netAmount,
                'type' => 'charge',
                'description' => 'Payment to merchant',
            ]);

            $feeTransaction = Transaction::create([
                'id' => Str::uuid(),
                'debit_from' => $userWallet->id,
                'credit_to' => $mainWallet->id,
                'amount' => $fees,
                'type' => 'fee',
                'description' => 'Payment fees',
            ]);

            // if ($fraudPromise) {
            //     try {
            //         $fraudResponse = $fraudPromise->wait();
            //         if ($fraudResponse->failed()) {
            //             Log::error('Fraud detection service failed', ['response' => $fraudResponse->body()]);
            //         } elseif (isset($fraudResponse->json()['status']) && $fraudResponse->json()['status'] === 'denied') {
            //             DB::rollBack();
            //             return ['success' => false, 'message' => 'Transaction blocked by fraud detection service.'];
            //         }
            //     } catch (\Exception $e) {
            //         Log::error('Could not contact fraud detection service', ['details' => $e->getMessage()]);
            //     }
            // }
            
            if ($source === 'billing') {
                $billingServiceUrl = env('BILLING_SERVICE_URL');
                $response = Http::post("{$billingServiceUrl}/api/bills/update-bill-status", [
                    'bill_id' => $bill_id,
                    'user_id' => $user_id,
                    'paid_amount' => $netAmount,
                ]);

                if ($response->failed()) {
                    DB::rollBack();
                    return ['success' => false, 'message' => 'Failed to update bill status'];
                }
            } else {
                $referenceServiceUrl = env('REFERENCE_SERVICE_URL');
                $response = Http::post("{$referenceServiceUrl}/api/billing/pay", [
                    'Reference_Number' => $bill_id,
                    'user_id' => $user_id,
                ]);
                if ($response->failed()) {
                    DB::rollBack();
                    return ['success' => false, 'message' => 'Failed to update bill status'];
                }
            }

            DB::commit();
            return [
                'success' => true,
                'merchant_transaction' => $merchantTransaction,
                'fee_transaction' => $feeTransaction,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
