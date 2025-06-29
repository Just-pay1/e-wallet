<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SendMoneyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $senderTransaction = $this['sender_transaction'];
        $feesTransaction = $this['fees_transaction'];
        return [

            'id' => $senderTransaction->id,
            'amount' => $senderTransaction->amount,
            'fee' => $feesTransaction->amount,
            'total' => $senderTransaction->amount + $feesTransaction->amount,
            'date' => $senderTransaction->created_at->toDateString(),
            'time' => $senderTransaction->created_at->toTimeString(),
        ];
    }
}
