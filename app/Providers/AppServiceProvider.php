<?php

namespace App\Providers;

use App\Services\Messaging\LogSmsSender;
use App\Services\Messaging\SmsSender;
use App\Services\Messaging\TermiiSmsSender;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsSender::class, fn () => match (config('services.sms.driver')) {
            'termii' => new TermiiSmsSender,
            default => new LogSmsSender,
        });
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
