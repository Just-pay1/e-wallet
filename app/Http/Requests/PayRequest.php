<?php

namespace App\Http\Requests;


use Illuminate\Foundation\Http\FormRequest;

class PayRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'bill_id' => 'required|string|min:1',
            'source' => 'required|string|in:billing,reference',
            'category' => 'required|string|in:electric_bill,reference_bill,water_bill,mobile_bill,internet_bill,gas_bill',
            'lat' => 'required|numeric',
            'long' => 'required|numeric',
            'signature' => 'required|string',
            'timestamp' => 'required|string',
            'nonce' => 'required|string'
        ];
    }
} 