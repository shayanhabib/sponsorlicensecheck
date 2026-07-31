<?php
namespace App\Providers;
use Illuminate\Support\Facades\Gate;use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider{public function boot():void{Gate::define('admin',fn($user)=>in_array($user->email,explode(',',(string)env('ADMIN_EMAILS','admin@example.com')),true));}}
