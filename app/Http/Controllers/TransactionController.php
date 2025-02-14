<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
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
}
