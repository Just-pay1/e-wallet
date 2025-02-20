<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Hidehalo\Nanoid\Client;
class Wallet extends Model
{
    protected $guarded = [];
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($wallet) {
            $client = new Client();
            $wallet->id = $client->formattedId("0123456789", 10); // 10-digit numeric ID
        });
    }
}
