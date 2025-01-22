<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        LogViewer::auth(function ($request) {  
            return true;
            $user = $request->user();
            return $user && $user->is_admin;   
            // return $request->user()            
            // && in_array($request->user()->email, [                
            //     'john@example.com',            
            // ]);    
        });
    }
}
