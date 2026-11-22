<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\User;
use App\Services\ProgressService;
use App\Support\Locales;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProgressService::class);
    }

    public function boot(): void
    {
        Paginator::defaultView('partials.pagination');

        Gate::define('admin', fn (User $user) => $user->isAdmin());
        Gate::define('manage-course', fn (User $user, Course $course) => $user->canManageCourse($course));

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);
        RateLimiter::for('forms', fn (Request $request) => Limit::perMinute(6)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('verify', fn (Request $request) => Limit::perMinute(15)->by($request->ip()));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(600)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute(5)->by($request->user()?->id ?: $request->ip()));

        View::composer('*', function ($view) {
            $view->with([
                'siteName' => Settings::siteName(),
                'dir' => Locales::dir(),
            ]);
        });
    }
}
