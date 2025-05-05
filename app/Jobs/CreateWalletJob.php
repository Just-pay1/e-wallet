<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
class CreateWalletJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;    
    public $walletData;
    /**
     * Create a new job instance.
     */
    public function __construct($walletData)
    {
        $this->walletData = $walletData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Received RabbitMQ message:', $this->walletData);
        Http::post('https://e-wallet.azurewebsites.net/api/wallet/create', $this->walletData);
    }
}
