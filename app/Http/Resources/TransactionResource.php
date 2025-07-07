<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $merchantTransaction = $this['merchant_transaction'];
        $feeTransaction = $this['fee_transaction'];
        if($merchantTransaction->credit_to === $feeTransaction->debit_from){
      
            return [
                // 'merchant_transaction' => new SingleTransactionResource($merchantTransaction),
                // 'fee_transaction' => new SingleTransactionResource($feeTransaction),
                'id' => $merchantTransaction->id,
                'amount' => $merchantTransaction->amount,
                'fee' => 0,
                'total' => $merchantTransaction->amount,
                'date' => $merchantTransaction->created_at->toDateString(),
                'time' => $merchantTransaction->created_at->toTimeString(),
            ];
        }
       
        return [
            // 'merchant_transaction' => new SingleTransactionResource($merchantTransaction),
            // 'fee_transaction' => new SingleTransactionResource($feeTransaction),
            'id' => $merchantTransaction->id,
            'amount' => $merchantTransaction->amount,
            'fee' => $feeTransaction->amount,
            'total' => $merchantTransaction->amount + $feeTransaction->amount,
            'date' => $merchantTransaction->created_at->toDateString(),
            'time' => $merchantTransaction->created_at->toTimeString(),
        ];
    }
}

// class SingleTransactionResource extends JsonResource
// {
//     public function toArray(Request $request): array
//     {
//         return parent::toArray($request);
//     }
// }
