<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Contracts\DatabaseAdapterInterface;
use App\Services\Adapters\MySQLDatabaseAdapter;
use App\Services\Compression\CompressionServiceInterface;
use App\Services\Compression\GzipCompressionService;
use App\Services\Compression\DecompressionServiceInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DatabaseAdapterInterface::class,MySQLDatabaseAdapter::class);
        $this->app->bind(CompressionServiceInterface::class,GzipCompressionService::class);
        $this->app->bind( DecompressionServiceInterface::class,GzipCompressionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
