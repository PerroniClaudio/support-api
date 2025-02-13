<?php

namespace App\Http\Controllers;

use App\Models\TicketReportExport;
use Illuminate\Http\Request;
use App\Jobs\GenerateGenericReport;
use App\Jobs\GenerateReport;
use App\Jobs\GenerateUserReport;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketStatusUpdate;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;

class TicketReportExportController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        //
    }

    /**
     * Lista per company singola
     */

    public function company(Company $company) {
        $reports = TicketReportExport::where('company_id', $company->id)->where(
            'is_generated',
            true
        )
            ->orderBy('created_at', 'DESC')
            ->get();

        return response([
            'reports' => $reports,
        ], 200);
    }

    public function generic() {
        $reports = TicketReportExport::where('optional_parameters', '!=', "[]")
            ->where('is_generated', true)
            ->orderBy('created_at', 'DESC')
            ->get();

        $reports->each(function ($report) {
            $optionalParameters = json_decode($report->optional_parameters);
            if (isset($optionalParameters->specific_types)) {
                $specificTypes = $optionalParameters->specific_types;
                $ticketTypes = TicketType::whereIn('id', $specificTypes)->get();
                $report->specific_types = $ticketTypes;
            }

            if ($report->company_id != 1) {
                $report->company = Company::find($report->company_id);
            } else {
                $report->company = [
                    'name' => 'Non specificata'
                ];
            }
        });

        return response([
            'reports' => $reports,
        ], 200);
    }

    public function user(Request $request) {
        $user = $request->user();

        $reports = TicketReportExport::where('company_id', $user->company_id)
            ->where('is_user_generated', true)
            ->orderBy('created_at', 'DESC')
            ->get();

        return response([
            'reports' => $reports,
        ], 200);
    }

    public function download(TicketReportExport $ticketReportExport) {

        $url = $this->generatedSignedUrlForFile($ticketReportExport->file_path);


        return response([
            'url' => $url,
            'filename' => $ticketReportExport->file_name
        ], 200);
    }

    private function generatedSignedUrlForFile($path) {

        /**
         * @disregard P1009 Undefined type
         */

        $url = Storage::disk('gcs')->temporaryUrl(
            $path,
            now()->addMinutes(65)
        );

        return $url;
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
    public function show(TicketReportExport $ticketReportExport) {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TicketReportExport $ticketReportExport) {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TicketReportExport $ticketReportExport) {
        //
    }

    /**
     * Export the specified resource from storage.
     */

    public function export(Request $request) {

        $name = time() . '_' . $request->company_id . '_tickets.xlsx';

        $company = Company::find($request->company_id);
        // $file =  Excel::store(new TicketsExport($company, $request->start_date, $request->end_date), 'exports/' . $request->company_id . '/' . $name, 'gcs');


        $report = TicketReportExport::create([
            'company_id' => $company->id,
            'file_name' => $name,
            'file_path' => 'exports/' . $request->company_id . '/' . $name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'optional_parameters' => json_encode($request->optional_parameters)
        ]);

        dispatch(new GenerateReport($report));


        return response()->json(['file' => $name]);
    }

    private function getColorShades($number = 1, $random = false, $fromDarker = true, $fromLighter = false, $shadeColor = "red") {

        if ($shadeColor == "red") {
            $colorShadesBank = [
                '#5c1310',
                '#741815',
                '#8b1d19',
                '#a2221d',
                '#b92621',
                '#d02b25',
                '#e73029',
                '#e9453e',
                '#ec5954',
                '#ee6e69',
                '#f1837f',
                '#f39894',
                '#f5aca9',
                '#f8c1bf',
                '#fad6d4',
                '#fad6d4',
            ];
        } else {
            $colorShadesBank = [
                '#00090e',
                '#01121c',
                '#011c29',
                '#022537',
                '#032e45',
                '#033753',
                '#044061',
                '#044a6e',
                '#05537c',
                '#055c8a',
                '#1e6c96',
                '#377da1',
                '#508dad',
                '#699db9',
                '#82aec5',
                '#9bbed0'
            ];
        }



        if ($random) {
            // shuffle($colorShadesBank);

            $colorShades = [];
            $groups = array_chunk($colorShadesBank, 4);

            for ($i = 0; $i < $number; $i++) {
                $colorShades[] = $groups[$i % count($groups)][rand(0, 3)];
            }

            return $colorShades;
        }

        if ($fromLighter) {
            $colorShadesBank = array_reverse($colorShadesBank);
        }

        while ($number > count($colorShadesBank)) {
            $colorShadesBank = array_merge($colorShadesBank, $colorShadesBank);
        }

        return array_slice($colorShadesBank, 0, $number);
    }

    public function exportpdf(Ticket $ticket) {

        $name = time() . '_' . $ticket->id . '_tickets.xlsx';
        //? Webform

        $webform_data = json_decode($ticket->messages()->first()->message);

        $office = $ticket->company->offices()->where('id', $webform_data->office)->first();
        $webform_data->office = $office ? $office->name : null;

        if (isset($webform_data->referer)) {
            $referer = User::find($webform_data->referer);
            $webform_data->referer = $referer ? $referer->name . " " . $referer->surname : null;
        }

        if (isset($webform_data->referer_it)) {
            $referer_it = User::find($webform_data->referer_it);
            $webform_data->referer_it = $referer_it ? $referer_it->name . " " . $referer_it->surname : null;
        }

        //? Avanzamento

        $avanzamento = [
            "attesa" => 0,
            "assegnato" => 0,
            "in_corso" => 0,
        ];

        foreach ($ticket->statusUpdates as $update) {
            if ($update->type == 'status') {

                if (strpos($update->content, 'In attesa') !== false) {
                    $avanzamento["attesa"]++;
                }
                if (strpos($update->content, 'Assegnato') !== false) {
                    $avanzamento["assegnato"]++;
                }
                if (strpos($update->content, 'In corso') !== false) {
                    $avanzamento["in_corso"]++;
                }
            }
        }

        //? Chiusura

        $closingMessage = "";

        $closingUpdates = TicketStatusUpdate::where('ticket_id', $ticket->id)->where('type', 'closing')->get();
        $closingUpdate = $closingUpdates->last();

        if ($closingUpdate) {
            $closingMessage = $closingUpdate->content;
        }

        $data = [
            'title' => $name,
            'ticket' => $ticket,
            'webform_data' => $webform_data,
            'status_updates' => $avanzamento,
            'closing_messages' => $closingMessage,

        ];

        Pdf::setOptions([
            'dpi' => 150,
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true // ✅ Abilita il caricamento di immagini da URL esterni
        ]);

        $pdf = Pdf::loadView('pdf.export', $data);

        // return $pdf->stream();
        return $pdf->download($name);
    }

    public function exportBatch(Request $request) {
        $user = $request->user();
        if ($user["is_admin"] != 1 && $user["is_company_admin"] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        if ($user["is_admin"] == 1) {
            $cacheKey = 'admin_batch_report_' . $request->company_id . '_' . $request->from . '_' . $request->to;
        } else {
            $cacheKey = 'user_batch_report_' . $request->company_id . '_' . $request->from . '_' . $request->to;
        }

        $company = Company::find($request->company_id);
        $tickets_data = Cache::get($cacheKey);

        $tickets_by_day = [];
        $ticket_graph_data = [];
        $closed_tickets_per_day = [];
        $different_categories_with_count = [];
        $different_type_with_count = [];
        $ticket_by_weekday = [];
        $ticket_by_priority = [];
        $tickets_by_user = [];
        $reduced_tickets = [];

        $sla_data = [
            'less_than_30_minutes' => 0,
            'less_than_1_hour' => 0,
            'less_than_2_hours' => 0,
            'more_than_2_hours' => 0,
        ];

        $dates_are_more_than_one_month_apart = \Carbon\Carbon::createFromFormat('Y-m-d', $request->from)->diffInMonths(\Carbon\Carbon::createFromFormat('Y-m-d', $request->to)) > 0;
        $tickets_by_month = [];

        $closed_tickets_count = 0;
        $other_tickets_count = 0;

        foreach ($tickets_data as $ticket) {
            $date = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket['data']['created_at'])->format('Y-m-d');
            if (!isset($tickets_by_day[$date])) {
                $tickets_by_day[$date] = [];
            }
            $tickets_by_day[$date][] = $ticket;

            // Categoria e tipo

            if ($ticket['data']['ticketType']['category']['is_problem'] == 1) {

                // Incident 

                if (!isset($different_categories_with_count['incident'][$ticket['data']['ticketType']['category']['name']])) {
                    $different_categories_with_count['incident'][$ticket['data']['ticketType']['category']['name']] = 0;
                }

                $different_categories_with_count['incident'][$ticket['data']['ticketType']['category']['name']]++;

                if (!isset($different_type_with_count['incident'][$ticket['data']['ticketType']['name']])) {
                    $different_type_with_count['incident'][$ticket['data']['ticketType']['name']] = 0;
                }

                $different_type_with_count['incident'][$ticket['data']['ticketType']['name']]++;
            } else {

                // Request 


                if (!isset($different_categories_with_count['request'][$ticket['data']['ticketType']['category']['name']])) {
                    $different_categories_with_count['request'][$ticket['data']['ticketType']['category']['name']] = 0;
                }

                $different_categories_with_count['request'][$ticket['data']['ticketType']['category']['name']]++;

                if (!isset($different_type_with_count['request'][$ticket['data']['ticketType']['name']])) {
                    $different_type_with_count['request'][$ticket['data']['ticketType']['name']] = 0;
                }

                $different_type_with_count['request'][$ticket['data']['ticketType']['name']]++;
            }

            // Giorno della settimana

            $weekday = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket['data']['created_at'])->locale('it')->isoFormat('dddd');

            if (!isset($ticket_by_weekday[$weekday])) {
                $ticket_by_weekday[$weekday] = 0;
            }

            $ticket_by_weekday[$weekday]++;

            // Se chiuso o meno

            if ($ticket['data']['status'] == 5) {
                $closed_tickets_count++;
            } else {
                $other_tickets_count++;
            }

            // Per mese

            if ($dates_are_more_than_one_month_apart) {

                $month = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket['data']['created_at'])->format('Y-m');

                if (!isset($tickets_by_month[$month])) {
                    $tickets_by_month[$month] = [
                        'incident' => 0,
                        'request' => 0
                    ];
                }

                if ($ticket['data']['ticketType']['category']['is_problem'] == 1) {
                    $tickets_by_month[$month]['incident']++;
                } else {
                    $tickets_by_month[$month]['request']++;
                }
            }

            // Per priorità

            if (!isset($ticket_by_priority[$ticket['data']['priority']])) {
                $ticket_by_priority[$ticket['data']['priority']] = 0;
            }

            $ticket_by_priority[$ticket['data']['priority']]++;

            // Per utente



            if ($ticket['data']['user']['is_admin'] == 1) {

                if (!isset($tickets_by_user['Support'])) {
                    $tickets_by_user['Support'] = 0;
                }

                $tickets_by_user['Support']++;
            } else {

                if (!isset($tickets_by_user[$ticket['data']['user_id']])) {
                    $tickets_by_user[$ticket['data']['user_id']] = 0;
                }

                $tickets_by_user[$ticket['data']['user_id']]++;
            }


            // Presa in carica

            if ($ticket['data']['status_updates'] != null) {
                $elapsed_minutes = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket['data']['created_at'])->diffInMinutes(\Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket['data']['status_updates'][0]['created_at']));
            } else {
                // $elapsed_minutes = $ticket['data']['sla_take'];
                $elapsed_minutes = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket['data']['created_at'])->diffInMinutes(\Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket['data']['updated_at']));
            }

            if ($elapsed_minutes < 30) {
                $sla_data['less_than_30_minutes']++;
            } else if ($elapsed_minutes < 60) {
                $sla_data['less_than_1_hour']++;
            } else if ($elapsed_minutes < 120) {
                $sla_data['less_than_2_hours']++;
            } else {
                $sla_data['more_than_2_hours']++;
            }

            // Ticket ridotto

            $reduced_ticket = [
                "id" => $ticket['data']['id'],
                "incident_request" => $ticket['data']['ticketType']['category']['is_problem'] == 1 ? "Incident" : "Request",
                "category" => $ticket['data']['ticketType']['category']['name'],
                "type" => $ticket['data']['ticketType']['name'],
                "opened_by" => $ticket['data']['user']['is_admin'] == 1 ? "Supporto" : $ticket['data']['user']['name'] . " " . $ticket['data']['user']['surname'],
                "opened_at" => \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket['data']['created_at'])->format('d/m/Y H:i'),
                "webform_data" => $ticket['webform_data'],
                "status_updates" => $ticket['status_updates'],
                "description" => $ticket['data']['description'],
                "closing_message" => $ticket['closing_message'],
                'should_show_more' => false,
                'ticket_frontend_url' => env('FRONTEND_URL') . '/support/user/ticket/' . $ticket['data']['id'],
            ];

            if (count($ticket['data']['messages']) > 3) {

                $ticket['data']['messages']->forget(0);
                $ticket['data']['messages'] = $ticket['data']['messages']->take(3);

                foreach ($ticket['data']['messages'] as $key => $message) {
                    $reduced_ticket['messages'][] = [
                        "id" => $message['id'],
                        "user" => $message['user']['is_admin'] == 1 ? "Supporto - Update" : $message['user']['name'] . " " . $message['user']['surname'],
                        "message" => $message['message'],
                        "created_at" => \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $message['created_at'])->format('d/m/Y H:i')
                    ];
                }
                $reduced_ticket['should_show_more'] = true;
            } else {
                foreach ($ticket['data']['messages'] as $key => $message) {
                    $reduced_ticket['messages'][] = [
                        "id" => $message['id'],
                        "user" => $message['user']['is_admin'] == 1 ? "Supporto - Update" : $message['user']['name'] . " " . $message['user']['surname'],
                        "message" => $message['message'],
                        "created_at" => \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $message['created_at'])->format('d/m/Y H:i')
                    ];
                }
            }


            $reduced_tickets[] = $reduced_ticket;
        }




        $total_incidents = 0;
        $total_requests = 0;

        for ($date = \Carbon\Carbon::createFromFormat('Y-m-d', $request->from); $date <= \Carbon\Carbon::createFromFormat('Y-m-d', $request->to); $date->addDay()) {
            if (isset($tickets_by_day[$date->format('Y-m-d')])) {

                $incidents = 0;
                $requests = 0;

                foreach ($tickets_by_day[$date->format('Y-m-d')] as $ticket) {
                    if ($ticket['data']['ticketType']['category']['is_problem'] == 1) {
                        $incidents++;
                        $total_incidents++;
                    } else {
                        $requests++;
                        $total_requests++;
                    }
                }

                $ticket_graph_data[$date->format('Y-m-d')] = [
                    'incidents' => $incidents,
                    'requests' => $requests
                ];

                $closed_tickets_per_day[$date->format('Y-m-d')] = $incidents + $requests;
            }
        }

        /** Grafici */

        $charts_base_url = "https://quickchart.io/chart?c=";
        $base_incident_color = "#ff6f6a";
        $base_request_color = "#9bbed0";

        // 1 - Numero di Ticket Chiusi per Categoria

        $ticket_by_category_data = [
            "type" => "bar",
            "data" => [
                "labels" => [
                    "Request",
                    "Incident"
                ],
                "datasets" => [[
                    "label" => "Numero di Ticket",
                    "data" => [
                        $total_requests,
                        $total_incidents
                    ],
                    "backgroundColor" => [$base_request_color, $base_incident_color]
                ]]
            ],
            "options" => [
                "title" => ["display" => true, "text" => "Ticket Chiusi per Categoria"],
                "legend" => ["display" => false]
            ]
        ];
        $ticket_by_category_url = $charts_base_url . urlencode(json_encode($ticket_by_category_data));


        // 2 - Ticket chiusi nel tempo 

        $ticket_closed_time_data = [
            "type" => "line",
            "data" => [
                "labels" => array_keys($ticket_graph_data),
                "datasets" => [

                    [
                        "label" => "Incidents",
                        "data" => array_values(array_column($ticket_graph_data, 'incidents')),
                        "borderColor" => $base_incident_color,
                        "fill" => false
                    ],
                    [
                        "label" => "Requests",
                        "data" => array_values(array_column($ticket_graph_data, 'requests')),
                        "borderColor" => $base_request_color,
                        "fill" => false
                    ]
                ]
            ],
            "options" => [
                "title" => [
                    "display" => true,
                    "text" => "Ticket Chiusi nel tempo"
                ],
                "legend" => [
                    "display" => true,

                ]
            ]
        ];
        $ticket_closed_time_url = $charts_base_url . urlencode(json_encode($ticket_closed_time_data));

        // 3 - Grafico a barre categoria di ticket 

        $different_categories_with_count['incident'] = collect($different_categories_with_count['incident'])
            ->sortByDesc(function ($count) {
                return $count;
            })
            ->take(5)
            ->toArray();

        $different_categories_with_count['request'] = collect($different_categories_with_count['request'])
            ->sortByDesc(function ($count) {
                return $count;
            })
            ->take(5)
            ->toArray();

        $ticket_by_category_incident_bar_data = [
            "type" => "horizontalBar",
            "data" => [
                "labels" => array_map(function ($label) {
                    return strlen($label) > 20 ? substr($label, 0, 17) . '...' : $label;
                }, array_keys($different_categories_with_count['incident'])),
                "datasets" => [[
                    "data" => [
                        ...array_values($different_categories_with_count['incident']),
                    ],
                    "backgroundColor" => $this->getColorShades(count(array_keys($different_categories_with_count['incident'])), true)
                ]]
            ],
            "options" => [
                "title" => ["display" => true, "text" => "Incident per Categoria"],
                "legend" => ["display" => false],

            ]
        ];
        $ticket_by_category_incident_bar_url = $charts_base_url . urlencode(json_encode($ticket_by_category_incident_bar_data));

        $ticket_by_category_request_bar_data = [
            "type" => "horizontalBar",
            "data" => [
                "labels" => array_map(function ($label) {
                    return strlen($label) > 20 ? substr($label, 0, 17) . '...' : $label;
                }, array_keys($different_categories_with_count['request'])),
                "datasets" => [[
                    "data" => [
                        ...array_values($different_categories_with_count['request']),
                    ],
                    "backgroundColor" => $this->getColorShades(count(array_keys($different_categories_with_count['request'])), true, true, false, "blue")
                ]]
            ],
            "options" => [
                "title" => ["display" => true, "text" => "Request più frequenti per Categoria"],
                "legend" => ["display" => false],

            ]
        ];
        $ticket_by_category_request_bar_url = $charts_base_url . urlencode(json_encode($ticket_by_category_request_bar_data));

        // 4 - Grafico tipo di ticket

        $different_type_with_count['incident'] = collect($different_type_with_count['incident'])
            ->sortByDesc(function ($count) {
                return $count;
            })
            ->take(5)
            ->toArray();

        $different_type_with_count['request'] = collect($different_type_with_count['request'])
            ->sortByDesc(function ($count) {
                return $count;
            })
            ->take(5)
            ->toArray();


        $ticket_by_type_incident_bar_data = [
            "type" => "horizontalBar",
            "data" => [
                "labels" => array_map(function ($label) {
                    return strlen($label) > 20 ? substr($label, 0, 17) . '...' : $label;
                }, array_keys($different_type_with_count['incident'])),
                "datasets" => [[
                    "data" => [
                        ...array_values($different_type_with_count['incident']),
                    ],
                    "backgroundColor" => $this->getColorShades(count(array_keys($different_type_with_count['incident'])), true)
                ]]
            ],
            "options" => [
                "title" => ["display" => true, "text" => "Incident più frequenti per Tipo"],
                "legend" => ["display" => false],

            ]
        ];
        $ticket_by_type_incident_bar_url = $charts_base_url . urlencode(json_encode($ticket_by_type_incident_bar_data));

        $ticket_by_type_request_bar_data = [
            "type" => "horizontalBar",
            "data" => [
                "labels" => array_map(function ($label) {
                    return strlen($label) > 20 ? substr($label, 0, 17) . '...' : $label;
                }, array_keys($different_type_with_count['request'])),
                "datasets" => [[
                    "data" => [
                        ...array_values($different_type_with_count['request']),
                    ],
                    "backgroundColor" => $this->getColorShades(count(array_keys($different_type_with_count['request'])), true, true, false, "blue")
                ]]
            ],
            "options" => [
                "title" => ["display" => true, "text" => "Request più frequenti per Tipo"],
                "legend" => ["display" => false]
            ]
        ];
        $ticket_by_type_request_bar_url = $charts_base_url . urlencode(json_encode($ticket_by_type_request_bar_data));

        // 5 - Provenienza ticket (il dato ancora non c'è, grafico finto)

        $ticket_by_source_data = [
            "type" => "horizontalBar",
            "data" => [
                "labels" => ["Email", "Telefono", "Tecnico onsite", "Piattaforma", "Automatico"],
                "datasets" => [[
                    "label" => "Numero di Ticket",
                    "data" => [12, 19, 3, 5, 7],
                    "backgroundColor" => $this->getColorShades(5, true)
                ]]
            ],
            "options" => [
                "title" => ["display" => true, "text" => "Ticket per Provenienza"],
                "legend" => ["display" => false]
            ]
        ];
        $ticket_by_source_url = $charts_base_url . urlencode(json_encode($ticket_by_source_data));

        // 6 - Ticket per giorno della settimana

        $daysOfWeek = ['lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato', 'domenica'];
        $ticket_by_weekday = array_merge(array_flip($daysOfWeek), $ticket_by_weekday);

        $ticket_by_weekday_data = [
            "type" => "bar",
            "data" => [
                "labels" => array_keys($ticket_by_weekday),
                "datasets" => [[
                    "label" => "Numero di Ticket",
                    "data" => array_values($ticket_by_weekday),
                    "backgroundColor" => $this->getColorShades(7, false, false, true)
                ]]
            ],
            "options" => [
                "title" => ["display" => true, "text" => "Ticket per Giorno della Settimana"],
                "legend" => ["display" => false]
            ]
        ];

        $ticket_by_weekday_url = $charts_base_url . urlencode(json_encode($ticket_by_weekday_data));

        // 7 - Ticket per mese

        if ($dates_are_more_than_one_month_apart) {

            $ticket_by_month_data = [
                "type" => "bar",
                "data" => [
                    "labels" => array_keys($tickets_by_month),
                    "datasets" => [
                        [
                            "label" => "Incidents",
                            "data" => array_values(array_column($tickets_by_month, 'incident')),
                            "backgroundColor" => $base_incident_color
                        ],
                        [
                            "label" => "Requests",
                            "data" => array_values(array_column($tickets_by_month, 'request')),
                            "backgroundColor" => $base_request_color
                        ]
                    ]
                ],
                "options" => [
                    "title" => [
                        "display" => true,
                        "text" => "Ticket per Mese"
                    ],
                    "legend" => [
                        "display" => true,
                    ],
                    "scales" => [
                        "xAxes" => [[
                            "stacked" => true
                        ]],
                        "yAxes" => [[
                            "stacked" => true
                        ]]
                    ],
                    "plugins" => [
                        "datalabels" => [
                            "display" => true,
                            "color" => "white",
                            "font" => [
                                "size" => 8
                            ]
                        ]
                    ]
                ]
            ];

            $ticket_per_month_url = $charts_base_url . urlencode(json_encode($ticket_by_month_data));
        } else {
            $ticket_per_month_url = "";
        }

        // 8 - Barre per priorità dei ticket

        $ticket_by_priority = [
            "Critica" => $ticket_by_priority['critical'] ?? 0,
            "Alta" => $ticket_by_priority['high'] ?? 0,
            "Media" => $ticket_by_priority['medium'] ?? 0,
            "Bassa" => $ticket_by_priority['low'] ?? 0,
        ];

        $ticket_by_priority_bar_data = [
            "type" => "horizontalBar",
            "data" => [
                "labels" => array_keys($ticket_by_priority),
                "datasets" => [[
                    "label" => "Numero di Ticket",
                    "data" => array_values($ticket_by_priority),
                    "backgroundColor" => $this->getColorShades(4, false, true, false)
                ]]
            ],
            "options" => [
                "title" => ["display" => true, "text" => "Ticket per Priorità"],
                "legend" => ["display" => false]
            ]
        ];

        $ticket_by_priority_url = $charts_base_url . urlencode(json_encode($ticket_by_priority_bar_data));

        // 9 - Ticket per utente

        $tickets_by_user_data = [
            "type" => "bar",
            "data" => [
                "labels" => array_map(function ($label) {
                    return strlen($label) > 20 ? substr($label, 0, 17) . '...' : $label;
                }, array_map(function ($user_id) use (&$userCache) {

                    if ($user_id == 'Support') {
                        return 'Supporto';
                    }

                    if (!isset($userCache[$user_id])) {
                        $user = User::find($user_id);
                        $userCache[$user_id] = $user->name . ' ' . $user->surname;
                    }
                    return $userCache[$user_id];
                }, array_keys($tickets_by_user))),
                "datasets" => [[
                    "label" => "Numero di Ticket",
                    "data" => array_values($tickets_by_user),
                    "backgroundColor" => $this->getColorShades(count(array_keys($tickets_by_user)), true)
                ]]
            ],
            "options" => [
                "title" => ["display" => true, "text" => "Ticket per Utente"],
                "legend" => ["display" => false]
            ]
        ];

        $tickets_by_user_url = $charts_base_url . urlencode(json_encode($tickets_by_user_data));

        // 10 - SLA

        $tickets_sla_data = [
            "type" => "doughnut",
            "data" => [
                "labels" => [
                    "Meno di 30 minuti",
                    "Meno di 1 ora",
                    "Meno di 2 ore",
                    "Più di 2 ore"
                ],
                "datasets" => [[
                    "data" => [
                        $sla_data['less_than_30_minutes'],
                        $sla_data['less_than_1_hour'],
                        $sla_data['less_than_2_hours'],
                        $sla_data['more_than_2_hours']
                    ],
                    "backgroundColor" => $this->getColorShades(4, true, true, false)
                ]]
            ],
            "options" => [
                "title" => ["display" => true, "text" => "SLA"],
                "legend" => [
                    "display" => true,
                    "position" => "bottom",
                    "labels" => [
                        "boxWidth" => 20,
                        "padding" => 20,
                        "usePointStyle" => true
                    ]
                ],
                "plugins" => [
                    "datalabels" => [
                        "color" => "white",
                        "font" => [
                            "weight" => "bold"
                        ]
                    ]
                ]
            ]
        ];

        $tickets_sla_url = $charts_base_url . urlencode(json_encode($tickets_sla_data));

        $data = [
            'tickets' => $reduced_tickets,
            'title' => "Esportazione tickets",
            'date_from' => \Carbon\Carbon::createFromFormat('Y-m-d', $request->from),
            'date_to' => \Carbon\Carbon::createFromFormat('Y-m-d', $request->to),
            'company' => $company,
            'request_number' => $total_requests,
            'incident_number' => $total_incidents,
            'closed_tickets_count' => $closed_tickets_count,
            'other_tickets_count' => $other_tickets_count,
            'ticket_graph_data' => $ticket_graph_data,
            'ticket_by_category_url' => $ticket_by_category_url,
            'ticket_closed_time_url' => $ticket_closed_time_url,
            'ticket_by_category_incident_bar_url' => $ticket_by_category_incident_bar_url,
            'ticket_by_category_request_bar_url' => $ticket_by_category_request_bar_url,
            'ticket_by_type_incident_bar_url' => $ticket_by_type_incident_bar_url,
            'ticket_by_type_request_bar_url' => $ticket_by_type_request_bar_url,
            'ticket_by_source_url' => $ticket_by_source_url,
            'ticket_by_weekday_url' => $ticket_by_weekday_url,
            'dates_are_more_than_one_month_apart' => $dates_are_more_than_one_month_apart,
            'ticket_per_month_url' => $ticket_per_month_url,
            'ticket_by_priority_url' => $ticket_by_priority_url,
            'tickets_by_user_url' => $tickets_by_user_url,
            'tickets_sla_url' => $tickets_sla_url,

        ];


        Pdf::setOptions([
            'dpi' => 150,
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true
        ]);
        $pdf = Pdf::loadView('pdf.exportbatch', $data);
        return $pdf->download("Esportazione tickets");
    }

    public function genericExport(Request $request) {
        $name = time() . '_generic_export.xlsx';
        $file_path = $request->company_id ? 'exports/' . $request->company_id . '/exports/ifortech/' . $name : '';


        $report = TicketReportExport::create([
            'company_id' => $request->company_id ? $request->company_id : 0,
            'file_name' => $name,
            'file_path' => $file_path,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'optional_parameters' => json_encode($request->optional_parameters)
        ]);

        dispatch(new GenerateGenericReport($report));

        return response()->json(['file' => $name]);
    }

    public function userExport(Request $request) {

        $user = $request->user();

        $name_file = str_replace("-", "_", $request->start_date) . "_" . str_replace("-", "_", $request->end_date) . str_replace("-", "_", $request->type);
        $name = time() . '_'  . $name_file . '.xlsx';

        $report = TicketReportExport::create([
            'company_id' => $user->company_id,
            'file_name' => $name,
            'file_path' => 'exports/' . $user->company_id . '/' . $name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'optional_parameters' => json_encode(["type" => $request->type]),
            'is_user_generated' => true
        ]);

        dispatch(new GenerateUserReport($report));

        return response()->json(['file' => $name]);
    }
}
