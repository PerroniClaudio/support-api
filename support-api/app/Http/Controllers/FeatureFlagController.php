<?php

namespace App\Http\Controllers;

use \Laravel\Pennant\Feature;
use App\Features\TicketFeatures;
use App\Features\HardwareFeatures;
use App\Features\PropertyFeatures;

class FeatureFlagController extends Controller {
    public function flushFeatureFlags() {
        Feature::purge();

        return response()->json([
            'success' => true,
            'message' => 'Feature flags cache cleared successfully'
        ]);
    }

    public function getFeatures() {
        $user = auth()->user();
        $currentTenant = config('app.tenant');

        // Formato gerarchico semplice
        $features = [
            "tickets" => $this->getEnabledFeaturesForScope($currentTenant, 'ticket', TicketFeatures::getFeatures()),
            "hardware" => $this->getEnabledFeaturesForScope($currentTenant, 'hardware', HardwareFeatures::getFeatures()),
            "properties" => $this->getEnabledFeaturesForScope($currentTenant, 'property', PropertyFeatures::getFeatures()),

            // Feature legacy mantenute per compatibilità (solo ticket_types per ora)
            "ticket_types" => Feature::for($currentTenant)->active('ticket.types'),

            // Feature statiche (sempre abilitate)
            "users_management" => true,
            "companies_management" => true,
            "groups_management" => true,
            "suppliers_management" => true,
            "brand_management" => true,
            "reports" => true,
            "documentation" => true
        ];

        return response()->json([
            "success" => true,
            "message" => "Feature flags retrieved successfully",
            "features" => $features,
            "user_role" => $user?->role ?? "admin",
            "permissions" => [
                "view_tickets",
                "create_tickets",
                "manage_hardware",
                "manage_users",
                "manage_companies",
                "view_reports"
            ],
            "last_updated" => now()->toISOString()
        ]);
    }

    /**
     * Ottiene le feature abilitate per uno scope specifico
     */
    private function getEnabledFeaturesForScope(string $tenant, string $scope, array $availableFeatures): array {
        $enabledFeatures = [];

        foreach ($availableFeatures as $feature) {
            if (Feature::for($tenant)->active("{$scope}.{$feature}")) {
                $enabledFeatures[] = $feature;
            }
        }

        return $enabledFeatures;
    }
}
