<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Hidehalo\Nanoid\Client;

class Transaction extends Model
{
    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (empty($transaction->id)) {
                $client = new Client();
                $transaction->id = $client->formattedId("0123456789", 10); // 10-digit numeric ID
            }
        });
    }
}
