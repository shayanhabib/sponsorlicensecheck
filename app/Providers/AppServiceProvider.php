<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('admin', function ($user) {
            return in_array(
                $user->email,
                explode(',', env('ADMIN_EMAILS', 'admin@example.com')),
                true
            );
        });
    }
}