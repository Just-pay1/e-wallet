<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {

    
        $logoUrl = url($this->logo);
        return [
            'display' => $this->display,
            'id' => $this->id,
            'amount' => $this->amount,
            'type' => $this->type,
            'description' => $this->description,
            'date' => $this->created_at->format('d M Y'),
            'time' => $this->created_at->format('h:i A'),
            'logo' => $logoUrl,
        ];
    }
} 