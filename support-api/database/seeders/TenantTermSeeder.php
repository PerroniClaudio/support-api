<?php

namespace Database\Seeders;

use App\Models\TenantTerm;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TenantTermSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Termini per Domustart
        $domustartTerms = [
            [
                'key' => 'app_name',
                'value' => 'DomuStart',
                'description' => 'Nome dell\'applicazione per il tenant Domustart',
                'category' => 'branding'
            ],
            [
                'key' => 'welcome_message',
                'value' => 'Benvenuto in DomuStart - La tua piattaforma di gestione immobiliare',
                'description' => 'Messaggio di benvenuto per Domustart',
                'category' => 'ui'
            ],
            [
                'key' => 'support_email',
                'value' => 'supporto@domustart.it',
                'description' => 'Email di supporto per Domustart',
                'category' => 'contact'
            ],
            [
                'key' => 'ticket_types',
                'value' => [
                    'property_management' => 'Gestione Immobili',
                    'tenant_support' => 'Supporto Inquilini',
                    'maintenance' => 'Manutenzione',
                    'billing' => 'Fatturazione'
                ],
                'description' => 'Tipi di ticket specifici per Domustart',
                'category' => 'tickets'
            ],
            [
                'key' => 'dashboard_title',
                'value' => 'Dashboard Immobiliare',
                'description' => 'Titolo della dashboard per Domustart',
                'category' => 'ui'
            ]
        ];

        foreach ($domustartTerms as $term) {
            TenantTerm::setTermForTenant(
                'domustart',
                $term['key'],
                $term['value'],
                $term['description'],
                $term['category']
            );
        }

        // Termini per Spreetzit (esempio)
        $spreetzitTerms = [
            [
                'key' => 'app_name',
                'value' => 'Spreetzit',
                'description' => 'Nome dell\'applicazione per il tenant Spreetzit',
                'category' => 'branding'
            ],
            [
                'key' => 'welcome_message',
                'value' => 'Benvenuto in Spreetzit - La tua piattaforma di supporto tecnico',
                'description' => 'Messaggio di benvenuto per Spreetzit',
                'category' => 'ui'
            ],
            [
                'key' => 'support_email',
                'value' => 'support@spreetzit.com',
                'description' => 'Email di supporto per Spreetzit',
                'category' => 'contact'
            ],
            [
                'key' => 'ticket_types',
                'value' => [
                    'technical_support' => 'Supporto Tecnico',
                    'hardware_issue' => 'Problemi Hardware',
                    'software_bug' => 'Bug Software',
                    'feature_request' => 'Richiesta Funzionalità'
                ],
                'description' => 'Tipi di ticket specifici per Spreetzit',
                'category' => 'tickets'
            ],
            [
                'key' => 'dashboard_title',
                'value' => 'Dashboard Tecnica',
                'description' => 'Titolo della dashboard per Spreetzit',
                'category' => 'ui'
            ]
        ];

        foreach ($spreetzitTerms as $term) {
            TenantTerm::setTermForTenant(
                'spreetzit',
                $term['key'],
                $term['value'],
                $term['description'],
                $term['category']
            );
        }
    }
}
