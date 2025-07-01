<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayRequest;
use App\Http\Requests\SendMoneyRequest;
use App\Http\Resources\SendMoneyResource;
use App\Http\Resources\TransactionHistoryResource;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use App\Services\WalletService;
use Illuminate\Support\Facades\Log;

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
        if(!$user_id){
            return response()->json(['error' => 'User ID is required'], 400);
        }
        $wallet = Wallet::where('user_id', $user_id)->first();
        $transactions = Transaction::where(function ($query) use ($wallet) {
                $query->where('debit_from', $wallet->id)
                    ->orWhere('credit_to', $wallet->id);
            })
            ->where('type', '!=', 'fee')
            ->orderBy('created_at', 'desc')
            ->get();

        $transactions = $transactions->map(function ($transaction) use ($wallet) {
            $type = $transaction->type;
            $typeConfig = config('transaction_types.' . $type, [
                'logo' => '/icon/pay.svg',
                'display' => ucfirst(str_replace('_', ' ', $type)),
            ]);
            $transaction->logo = $typeConfig['logo'];
            $transaction->display = $typeConfig['display'];
            if ($type == 'send' && $transaction->credit_to == $wallet->id) {
       
                $transaction->logo = '/icon/receive.svg';
                $transaction->display = 'Receive Money';
                $transaction->description = 'You received money from ********' . substr($transaction->debit_from, -4). ' wallet';
            }elseif($type == 'send' && $transaction->debit_from == $wallet->id){
                $transaction->description = 'You sent money to ********' . substr($transaction->credit_to, -4). ' wallet';
            }
            return $transaction;
        });
            
        return response()->json([
            'transactions' => TransactionHistoryResource::collection($transactions),
            'success' => true,
            'message' => 'Transactions fetched successfully.',
        ], 200);
    }

    public function pay(PayRequest $request)
    {
        try {
            $transactionResponse = $this->TransactionService->pay($request);

            if ($transactionResponse['success'] === false) {
                return response()->json(['error' => $transactionResponse['message']], $transactionResponse['status_code'] ?? 400);
            }

            return response()->json([
                'model' => new TransactionResource($transactionResponse),
                'success' => true,
                'message' => 'Your transaction Has been completed successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('An unexpected error occurred in TransactionController@pay', ['details' => $e->getMessage()]);
            return response()->json(['error' => 'An unexpected error occurred.', 'details' => $e->getMessage()], 500);
        }
    }

    public function sendMoney(SendMoneyRequest $request)
    {
        $transactionResponse = $this->TransactionService->sendMoney($request);
        if ($transactionResponse['success'] === false) {
            return response()->json(['error' => $transactionResponse['message']], $transactionResponse['status_code'] ?? 400);
        }

        return response()->json([
            'model' => new SendMoneyResource($transactionResponse),
            'success' => true,
            'message' => 'Your transaction Has been completed successfully.',
        ], 200);
    }

    public function calFees(float $amount)
    {
        $result = $this->TransactionService->calFees($amount);
   
        return response()->json([
            'fees' => $result,
            'success' => true,
            'message' => 'Fees calculated successfully.',
        ], 200);
    }
}
