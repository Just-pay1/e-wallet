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
    
    public function pay($request)
    {

        $user_id = $request->header('X-User-ID');
        if (!$user_id) {
            return ['success' => false, 'message' => 'Unauthorized', 'status_code' => 401];
        }

        // --- Signature, Timestamp, and Nonce Validation ---
        $secretKey = config('services.transaction_secret_key');
        $signature = $request->input('signature');
        $timestamp = $request->input('timestamp');
        $nonce = $request->input('nonce');
        $bill_id = $request->input('bill_id');

        if (!$signature || !$timestamp || !$nonce || !$bill_id) {
            return ['success' => false, 'message' => 'Missing signature, timestamp, nonce, or bill_id', 'status_code' => 400];
        }

        $expectedSignature = $this->generateSignature($bill_id, $timestamp, $nonce, $secretKey);

        // if (!hash_equals($expectedSignature, $signature)) {
        //     return ['success' => false, 'message' => 'Invalid signature', 'status_code' => 500];
        // }

        // $reqTime = strtotime($timestamp);
        // if (!$reqTime || abs(time() - $reqTime) > 300) {
        //     return ['success' => false, 'message' => 'Timestamp out of range', 'status_code' => 500];
        // }

        // if ($this->isNonceUsed($nonce)) {
        //     return ['success' => false, 'message' => 'Nonce already used', 'status_code' => 500];
        // }
        $this->markNonceUsed($nonce);
        // --- End Signature, Timestamp, and Nonce Validation ---



        $validated = $request->validated();
        $bill_id = $validated['bill_id'];
        $source = $validated['source'];

        if ($source === 'billing') {
            $billingServiceUrl = config('services.BILLING_SERVICE_URL');
            $billPromise = Http::async()->get("{$billingServiceUrl}/api/bills/bill-details?bill_id={$bill_id}");
        } else {
            $referenceServiceUrl = config('services.REFERENCE_SERVICE_URL');
            
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
        
        $fraudPromise = null;
        $fraudServiceUrl = config('services.FRAUD_DETECTION_SERVICE_URL');

        if ($fraudServiceUrl) {
            $fraudPromise = Http::timeout(30)->async()->post("{$fraudServiceUrl}/predict", [[
                "trans_date_trans_time" => Carbon::now()->format('Y-m-d H:i:s'),
                "cc_num" => strval($userWallet->id),
                "category" => $request->category,
                "amt" => floatval($billData['amount']),
                "lat" => $request->lat,
                "long" => $request->long,
                "merch_lat" => floatval($billData['merchant']['latitude']),
                "merch_long" => floatval($billData['merchant']['longitude'])
            ]]);
        }

        $mainWallet = Wallet::where('id', config('services.JUST_PAY_WALLET_ID'))->firstOrFail();
        
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
                'debit_from' => $userWallet->id,
                'credit_to' => $merchantWallet->id,
                'amount' => $netAmount,
                'type' => 'charge',
                'description' => 'Payment to merchant',
            ]);

            $feeTransaction = Transaction::create([
                'debit_from' => $userWallet->id,
                'credit_to' => $mainWallet->id,
                'amount' => $fees,
                'type' => 'fee',
                'description' => 'Payment fees',
            ]);

            if ($fraudPromise) {
                try {
                    $fraudResponse = $fraudPromise->wait();
                    if ($fraudResponse->failed()) {
                        Log::error('Fraud detection service failed', ['response' => $fraudResponse->body()]);
                        DB::rollBack();
                        return ['success' => false, 'message' => 'Transaction blocked by fraud detection service.', 'status_code' => 500];
                    } elseif (isset($fraudResponse->json()['status']) && $fraudResponse->json()['status'] !== 'success') {
                        DB::rollBack();
                        return ['success' => false, 'message' => 'Transaction blocked by fraud detection service.', 'status_code' => 500];
                    } elseif (isset($fraudResponse->json()['fraud_probability']) && $fraudResponse->json()['fraud_probability'] > 50) {
                        DB::rollBack();
                        return ['success' => false, 'message' => 'Transaction blocked by fraud detection service.', 'status_code' => 500];
                    }
                } catch (\Exception $e) {
                    Log::error('Could not contact fraud detection service', ['details' => $e->getMessage()]);
                }
            }
            
            if ($source === 'billing') {
                $billingServiceUrl = config('services.BILLING_SERVICE_URL');
                $response = Http::post("{$billingServiceUrl}/api/bills/update-bill-status", [
                    'bill_id' => $bill_id,
                    'user_id' => $user_id,
                    'paid_amount' => $netAmount,
                ]);

                if ($response->failed()) {
                    DB::rollBack();
                    return ['success' => false, 'message' => 'Failed to update bill status', 'status_code' => 500];
                }
            } else {
                $referenceServiceUrl = config('services.REFERENCE_SERVICE_URL');
                $response = Http::post("{$referenceServiceUrl}/api/billing/pay", [
                    'Reference_Number' => $bill_id,
                    'user_id' => $user_id,
                ]);
                if ($response->failed()) {
                    DB::rollBack();
                    return ['success' => false, 'message' => 'Failed to update bill status', 'status_code' => 500];
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
            return ['success' => false, 'message' => $e->getMessage(), 'status_code' => 500];
        }
    }

    public function sendMoney($request)
    {
        // --- Signature, Timestamp, and Nonce Validation ---
        // $secretKey = env('TRANSACTION_SECRET_KEY');
        $secretKey = config('services.transaction_secret_key');
        $signature = $request->input('signature');
        $timestamp = $request->input('timestamp');
        $nonce = $request->input('nonce');
        $receiver_id = $request->input('receiver_id');

        if (!$signature || !$timestamp || !$nonce || !$receiver_id) {
            return ['success' => false, 'message' => 'Missing signature, timestamp, nonce, or receiver_id', 'status_code' => 400];
        }

        $expectedSignature = $this->generateSignature($receiver_id, $timestamp, $nonce, $secretKey);
        if (!hash_equals($expectedSignature, $signature)) {
            return ['success' => false, 'message' => 'Invalid signature', 'status_code' => 400];
        }

        $reqTime = strtotime($timestamp);
        if (!$reqTime || abs(time() - $reqTime) > 300) {
            return ['success' => false, 'message' => 'Timestamp out of range', 'status_code' => 400];
        }

        if ($this->isNonceUsed($nonce)) {
            return ['success' => false, 'message' => 'Nonce already used', 'status_code' => 400];
        }
        $this->markNonceUsed($nonce);
        // --- End Signature, Timestamp, and Nonce Validation ---
        $user_id = $request->header('X-User-ID');
        if (!$user_id) {
            return ['success' => false, 'message' => 'Unauthorized', 'status_code' => 401];
        }
        $justpay_wallet_id = config('services.JUST_PAY_WALLET_ID');
        if (!$justpay_wallet_id) {
            return ['success' => false, 'message' => 'JustPay wallet not found', 'status_code' => 404];
        }
        $fees_wallet = Wallet::where('id', $justpay_wallet_id)->first();

        $data = $request->validated();
        $sender_wallet = Wallet::where('user_id', $user_id)->first();
        $receiver_wallet = Wallet::where('user_id', $data['receiver_id'])->first();
        if (!$sender_wallet || !$receiver_wallet) {
            return ['success' => false, 'message' => 'Sender or receiver not found', 'status_code' => 404];
        }
        $fees = $this->calFees($data['amount']);
        if ($sender_wallet->balance <( $data['amount'] + $fees)) {
            return ['success' => false, 'message' => 'Insufficient balance', 'status_code' => 400];
        }
        DB::beginTransaction();
        try {
            $sender_wallet->decrement('balance', $data['amount'] + $fees);
            $receiver_wallet->increment('balance', $data['amount']);
            $fees_wallet->increment('balance', $fees);
            $sender_transaction = Transaction::create([
                'debit_from' => $sender_wallet->id,
                'credit_to' => $receiver_wallet->id,
                'amount' => $data['amount'],
                'type' => 'send',
                'description' => 'Send money to ' . $receiver_wallet->user_id,
            ]);
            $fees_transaction = Transaction::create([
                'debit_from' => $sender_wallet->id,
                'credit_to' => $fees_wallet->id,
                'amount' => $fees,
                'type' => 'fee',
                'description' => 'Send money fees',
            ]);
            DB::commit();
            return [
                'success' => true,
                'sender_transaction' => $sender_transaction,
                'fees_transaction' => $fees_transaction,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function generateSignature($x_factor, $timestamp, $nonce, $secretKey): string
    {
        $stringToSign = "{$x_factor}|{$timestamp}|{$nonce}";
        return hash_hmac('sha256', $stringToSign, $secretKey);
    }

    private function isNonceUsed($nonce)
    {
        return cache()->has('nonce_' . $nonce);
    }

    private function markNonceUsed($nonce)
    {
        cache()->put('nonce_' . $nonce, true, 300); // 5 minutes
    }

    public function calFees(float $amount)
    {
        if ($amount < 1000) {
            $fees = 0.5;
        }
        elseif( $amount >= 1000){
            $count = floor($amount / 1000);
            $fees = $count * 1;
        }
        elseif( $amount >= 10000){
            $count = floor($amount / 10000);
            $fees = $count * 2;
        }
        elseif( $amount >= 100000){
            $fees = $amount * 0.001;
        }
        else{
            $fees = 0;
        }
        return $fees;
    }
}
