<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Setting;
use App\Services\Payments\ManualGateway;
use App\Services\Payments\PaymentGateway;
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
        $this->app->bind(SmsDriver::class, fn () => match (config('services.sms.driver')) {
            'africastalking' => new AfricasTalkingSmsDriver,
            default => new LogSmsDriver,
        });
        $this->app->bind(PaymentGateway::class, ManualGateway::class);
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
