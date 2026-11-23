<?php

namespace App\Providers;

use App\Models\MenuItem;
use App\Models\User;
use App\Services\CartService;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    public function boot(): void
    {
        // Never send mail without a sender: fall back to the contact e-mail, then no-reply@host.
        if (! config('mail.from.address')) {
            $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
            config(['mail.from.address' => rescue(fn () => setting('contact.email'), null, false) ?: 'no-reply@'.$host, 'mail.from.name' => config('mail.from.name') ?: 'HO CREATIVE']);
        }

        // Owner has every permission.
        Gate::before(fn (User $u) => ($u->is_staff && $u->hasRole('owner')) ? true : null);

        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()));
        RateLimiter::for('forms', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('pricing', fn (Request $r) => Limit::perMinute(120)->by($r->ip()));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perMinute(30)->by($r->user()?->id ?: $r->ip()));

        View::composer('layouts.store', function ($view) {
            try {
                $view->with('menu', MenuItem::where('is_active', true)->orderBy('sort')->get()->groupBy('location'));
                $view->with('cartCount', app(CartService::class)->count());
            } catch (\Throwable) {
                $view->with('menu', collect())->with('cartCount', 0);
            }
        });
    }
}
