<?php

namespace App\Services;

use App\Models\Wallet;

class WalletService
{
    /**
     * Check if a user has a wallet.
     *
     * @param string $userId
     * @return bool
     */
    public function userHasWallet(string $userId): bool
    {
        return Wallet::where('user_id', $userId)->exists();
    }

    /**
     * Get the wallet ID for a given user ID.
     *
     * @param string $userId
     * @return string|null
     */
    public function getWalletIdByUserId(string $userId): ?string
    {
        $wallet = Wallet::where('user_id', $userId)->first();
        return $wallet ? $wallet->id : null;
    }

}
