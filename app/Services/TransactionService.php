<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    /**
     * Pay a bill by creating two transactions: one for the merchant and one for the main wallet (fees).
     *
     * @param array $billData
     * @param string $userId
     * @return array
     */
    public function pay(array $billData, string $userId, string $source, string $billId)
    {
        DB::beginTransaction();
        try {
            // Get wallets
            $userWallet = Wallet::where('user_id', $userId)->firstOrFail();
            $merchantWallet = Wallet::where('user_id', $billData['merchant_id'])->firstOrFail();
            $mainWallet = Wallet::where('id', env('JUST_PAY_WALLET_ID'))->firstOrFail(); 

            // Amounts
            $netAmount = $billData['amount'];
            $fees = $billData['fee'];

            // Check user balance
            if ($userWallet->balance < ($netAmount + $fees)) {
                return ['success' => false, 'message' => 'Insufficient funds'];
            }

            // Deduct from user
            $userWallet->decrement('balance', $netAmount + $fees);

            // Credit merchant
            $merchantWallet->increment('balance', $netAmount);

            // Credit main wallet (fees)
            $mainWallet->increment('balance', $fees);

            // Create transactions
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
            // return($billData);
            if($source == "billing"){
                
                $billingServiceUrl = env('BILLING_SERVICE_URL');
                $response = Http::post("{$billingServiceUrl}/api/bills/update-bill-status", [
                    'bill_id' => $billId,   
                    'user_id' => $userId,
                    'paid_amount' => $netAmount,
                ]);

                if ($response->failed()) {
                    return ['success' => false, 'message' => 'Failed to update bill status'];
                }elseif($response->status() == 500){
                    return ['success' => false, 'message' => $response['error']];
                }
            }elseif($source == "reference"){
                $referenceServiceUrl = env('REFERENCE_SERVICE_URL');
                $response = Http::post("{$referenceServiceUrl}/api/billing/pay", [
                    'Reference_Number' => $billId,
                    'user_id' => $userId,
                ]);
                if ($response->failed()) {
                    return ['success' => false, 'message' => 'Failed to update bill status'];
                }elseif($response->status() == 500){
                    return ['success' => false, 'message' => $response['error']];
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
