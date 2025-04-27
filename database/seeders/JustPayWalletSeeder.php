<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Wallet;

class JustPayWalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only create if not exists
        if (!Wallet::where('user_id', 1)->exists()) {
            Wallet::create([
                'user_id' => 1,
                'balance' => 50,
                'currency' => 'EGP',
            ]);
        }
    }
} 