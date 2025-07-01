<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Transaction;

class ChangeTypeTransSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $billTypes = [
            TransactionType::ELECTRIC_BILL->value,
            TransactionType::REFERENCE_BILL->value,
            TransactionType::WATER_BILL->value,
            TransactionType::MOBILE_BILL->value,
            TransactionType::INTERNET_BILL->value,
            TransactionType::SEND->value,
            TransactionType::FEE->value,
            
        ];
        $transactions = Transaction::all();
        foreach ($transactions as $transaction) {
            $transaction->type = $billTypes[array_rand($billTypes)];
            $transaction->save();
        }
    }
}
