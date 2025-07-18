<?php

namespace App\Http\Controllers;

use App\Models\Dashboard;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        //
        $user = auth()->user();
        $dashboard = Dashboard::where('user_id', $user->id)->first();

        if (!$dashboard) {
            // Ottieni la configurazione predefinita in base al tenant
            $defaultConfig = $this->getDefaultConfigForTenant();

            $dashboard = Dashboard::create([
                'user_id' => $user->id,
                'configuration' => json_encode($defaultConfig),
                'enabled_widgets' => json_encode([]),
                'settings' => json_encode([]),
            ]);
        }

        // Ottieni la configurazione delle card
        $cardConfig = json_decode($dashboard->configuration, true);
        
        // Aggiungi i dati statistici per ogni card
        $cardConfig = $this->enrichCardsWithData($cardConfig);

        return response()->json($cardConfig);
    }
    
    /**
     * Ottiene la configurazione predefinita delle card in base al tenant corrente
     */
    private function getDefaultConfigForTenant() {
        // Ottieni il tenant corrente
        $tenant = $this->getCurrentTenant();
        
        // Configurazione per il tenant domustart
        if ($tenant === 'domustart') {
            return [
                'leftCards' => [
                    [
                        'id' => 'condomini-registrati',
                        'type' => 'companies-count',
                        'color' => 'bg-primary',
                        'content' => 'Condomini registrati'
                    ],
                    [
                        'id' => 'utenti-registrati',
                        'type' => 'users-count',
                        'color' => 'bg-secondary',
                        'content' => 'Utenti registrati'
                    ]
                ],
                'rightCards' => [
                    [
                        'id' => 'casi-aperti',
                        'type' => 'open-tickets',
                        'color' => 'bg-primary',
                        'content' => 'Casi aperti'
                    ],
                    [
                        'id' => 'ticket-redirect',
                        'type' => 'tickets-redirect',
                        'color' => 'bg-secondary',
                        'content' => 'Vai ai ticket'
                    ]
                ]
            ];
        }
        
        // Configurazione predefinita per altri tenant
        return [
            'leftCards' => [
                [
                    'id' => 'ticket-aperti',
                    'type' => 'open-tickets',
                    'color' => 'bg-primary',
                    'content' => 'Ticket aperti'
                ],
                [
                    'id' => 'ticket-in-corso',
                    'type' => 'in-progress-tickets',
                    'color' => 'bg-secondary',
                    'content' => 'Ticket in corso'
                ]
            ],
            'rightCards' => [
                [
                    'id' => 'ticket-in-attesa',
                    'type' => 'waiting-tickets',
                    'color' => 'bg-primary',
                    'content' => 'Ticket in attesa'
                ],
                [
                    'id' => 'ticket-redirect',
                    'type' => 'tickets-redirect',
                    'color' => 'bg-secondary',
                    'content' => 'Gestione ticket'
                ]
            ]
        ];
    }
    
    /**
     * Ottiene il nome del tenant corrente
     */
    private function getCurrentTenant() {
        // Qui puoi implementare la logica per ottenere il tenant corrente
        // Ad esempio, potresti ottenerlo da una variabile di ambiente, da un header, da un database, ecc.
        
        // Per ora, come esempio, controlliamo se esiste una variabile di ambiente TENANT
        $tenant = env('TENANT', '');
        
        // Se non esiste, possiamo controllare il dominio o altre informazioni
        if (empty($tenant)) {
            // Esempio: controlla il dominio
            $host = request()->getHost();
            if (strpos($host, 'domustart') !== false) {
                return 'domustart';
            }
            
            // Puoi aggiungere altri controlli qui
        }
        
        return $tenant;
    }

    /**
     * Aggiunge i dati statistici alle card
     */
    private function enrichCardsWithData($cardConfig) {
        // Ottieni i dati statistici
        $stats = $this->getStats();
        
        // Arricchisci le card di sinistra
        if (isset($cardConfig['leftCards'])) {
            foreach ($cardConfig['leftCards'] as &$card) {
                $card = $this->addStatsToCard($card, $stats);
            }
        }
        
        // Arricchisci le card di destra
        if (isset($cardConfig['rightCards'])) {
            foreach ($cardConfig['rightCards'] as &$card) {
                $card = $this->addStatsToCard($card, $stats);
            }
        }
        
        return $cardConfig;
    }
    
    /**
     * Aggiunge i dati statistici a una singola card
     */
    private function addStatsToCard($card, $stats) {
        switch ($card['type']) {
            case 'companies-count':
                $card['value'] = $stats['companies_count'];
                break;
            case 'users-count':
                $card['value'] = $stats['users_count'];
                break;
            case 'open-tickets':
                $card['value'] = $stats['open_tickets_count'];
                $card['action'] = [
                    'type' => 'link',
                    'url' => '/support/admin/newticket',
                    'label' => 'Nuovo ticket'
                ];
                break;
            case 'tickets-redirect':
                $card['action'] = [
                    'type' => 'link',
                    'url' => '/support/admin/tickets',
                    'label' => 'Visualizza ticket'
                ];
                break;
        }
        
        return $card;
    }
    
    /**
     * Ottiene le statistiche per la dashboard
     */
    private function getStats() {
        // Conta i condomini (aziende) registrati
        $companiesCount = Company::count();
        
        // Conta gli utenti registrati (condomini)
        $usersCount = User::where('is_admin', false)->count();
        
        // Conta i ticket aperti
        $openTicketsCount = Ticket::whereIn('status', ['open', 'in_progress', 'pending'])->count();
        
        return [
            'companies_count' => $companiesCount,
            'users_count' => $usersCount,
            'open_tickets_count' => $openTicketsCount
        ];
    }

    /**
     * Aggiorna la configurazione delle card
     */
    public function updateCardConfig(Request $request) {
        $user = auth()->user();
        $dashboard = Dashboard::where('user_id', $user->id)->first();
        
        if (!$dashboard) {
            return response()->json(['error' => 'Dashboard non trovata'], 404);
        }
        
        $dashboard->configuration = json_encode([
            'leftCards' => $request->leftCards,
            'rightCards' => $request->rightCards
        ]);
        
        $dashboard->save();
        
        // Restituisci la configurazione aggiornata con i dati statistici
        $cardConfig = json_decode($dashboard->configuration, true);
        $cardConfig = $this->enrichCardsWithData($cardConfig);
        
        return response()->json($cardConfig);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Dashboard $dashboard) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Dashboard $dashboard) {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Dashboard $dashboard) {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Dashboard $dashboard) {
        //
    }
}
