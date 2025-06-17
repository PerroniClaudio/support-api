<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;

use Laravel\Pennant\Feature;
use App\Features\TicketFeatures;

class AppServiceProvider extends ServiceProvider {
    /**
     * Register any application services.
     */
    public function register(): void {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {
        //

        $this->registerFeatures();

        // LogViewer::auth(function ($request) {
        //     $user = $request->user();
        //     return $user && $user->is_admin;
        // });
    }

    /**
     * Registra automaticamente tutte le feature flags
     */
    private function registerFeatures(): void {
        $this->registerFeaturesFromClass('ticket', TicketFeatures::class);
        // Qui potrai aggiungere altre classi come:
        // $this->registerFeaturesFromClass('hardware', HardwareFeatures::class);
        // $this->registerFeaturesFromClass('user', UserFeatures::class);
    }

    /**
     * Registra le feature flags da una classe specifica
     */
    private function registerFeaturesFromClass(string $prefix, string $featureClass): void {
        $features = $this->getFeatureMethodsFromClass($featureClass);

        foreach ($features as $featureName) {
            Feature::define("{$prefix}.{$featureName}", function () use ($featureClass, $featureName) {
                return app($featureClass)($featureName);
            });
        }
    }

    /**
     * Estrae i nomi delle feature da una classe analizzando i metodi privati
     */
    private function getFeatureMethodsFromClass(string $featureClass): array {
        // Usa il metodo statico getFeatures() se disponibile
        if (method_exists($featureClass, 'getFeatures')) {
            return $featureClass::getFeatures();
        }

        return [];
    }
}
