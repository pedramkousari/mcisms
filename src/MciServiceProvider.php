<?php
namespace Pedramkousari\Mcisms;

use Illuminate\Support\ServiceProvider;

class MciServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->make(\Pedramkousari\Sms\SmsManager::class)->extend('mci', function (){
            return new MciDriver();
        });
    }
}
