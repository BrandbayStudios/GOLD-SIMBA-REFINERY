<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Setting;
use App\Services\Payments\GatewaySettings;
use App\Services\Sms\AfricasTalkingSmsDriver;
use App\Services\Sms\LogSmsDriver;
use App\Services\Sms\SmsDriver;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // SMS: Africa's Talking when switched on in Admin → Settings (or via .env), otherwise log only.
        $this->app->bind(SmsDriver::class, function ($app) {
            $fromSettings = AfricasTalkingSmsDriver::fromSettings($app->make(GatewaySettings::class));
            if ($fromSettings) {
                return $fromSettings;
            }
            $cfg = config('services.africastalking');
            if (config('services.sms.driver') === 'africastalking' && ! empty($cfg['api_key'])) {
                return new AfricasTalkingSmsDriver($cfg['username'], $cfg['api_key'], $cfg['sender_id'] ?: null);
            }

            return new LogSmsDriver;
        });
    }

    public function boot(): void
    {
        Paginator::useTailwind();

        View::composer('*', function ($view) {
            static $shared;
            if ($shared === null) {
                try {
                    $shared = [
                        'navCategories' => Schema::hasTable('categories') ? Category::active()->get(['id', 'name', 'slug', 'icon']) : collect(),
                        'supportPhone' => Setting::get('support_phone'),
                        'supportWhatsapp' => Setting::get('support_whatsapp'),
                        'supportEmail' => Setting::get('support_email'),
                    ];
                } catch (Throwable) {
                    $shared = ['navCategories' => collect(), 'supportPhone' => '', 'supportWhatsapp' => '', 'supportEmail' => ''];
                }
            }
            $view->with($shared);
        });
    }
}
