<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider; 
use App\Models\Transaction;
use App\Observers\TransactionObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // binding service allocator
        $this->app->singleton(\App\Services\SavingAllocatorService::class, function ($app) {
            return new \App\Services\SavingAllocatorService();
        });
    }

    public function boot(): void
    {
        Transaction::observe(TransactionObserver::class);
    }
}
