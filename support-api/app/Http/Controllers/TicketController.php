<?php

namespace App\Http\Controllers;

use App\Imports\TicketsImport;
use App\Jobs\SendOpenTicketEmail;
use App\Jobs\SendCloseTicketEmail;
use App\Jobs\SendUpdateEmail;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketStatusUpdate;
use App\Models\TicketFile;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Office;
use App\Models\Group;
use App\Models\Hardware;
use App\Models\HardwareAuditLog;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache; // Otherwise no redis connection :)
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;


class TicketController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request) {
        // Show only the tickets belonging to the authenticated user

        $user = $request->user();
        // Deve comprendere i ticket chiusi?
        $withClosed = $request->query('with-closed') == 'true' ? true : false;

        if ($withClosed) {
            $cacheKey = 'user_' . $user->id . '_tickets_with_closed';
        } else {
            $cacheKey = 'user_' . $user->id . '_tickets';
        }
        $tickets = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user, $withClosed) {
            if ($user["is_company_admin"] == 1) {

                if ($withClosed) {
                    $ticketsTemp = $user->company->tickets;
                } else {
                    $ticketsTemp = Ticket::where("status", "!=", 5)->where('company_id', $user->company->id)->with('user')->get();
                }

                foreach ($ticketsTemp as $ticket) {
                    $ticket->referer = $ticket->referer();
                    if ($ticket->referer) {
                        $ticket->referer->makeHidden(['email_verified_at', 'microsoft_token', 'created_at', 'updated_at', 'phone', 'city', 'zip_code', 'address']);
                    }
                    // Nascondere i dati utente se è stato aperto dal supporto
                    if ($ticket->user->is_admin) {
                        $ticket->user->id = 1;
                        $ticket->user->name = "Supporto";
                        $ticket->user->surname = "";
                        $ticket->user->email = "Supporto";
                    }
                    // Aggiunge la proprietà unread_admins_messages
                    // $ticket->append('unread_admins_messages');
                }
                return $ticketsTemp;
            } else {
                $ticketsTemp = $user->tickets->merge($user->refererTickets());
                foreach ($ticketsTemp as $ticket) {
                    $ticket->referer = $ticket->referer();
                    if ($ticket->referer) {
                        $ticket->referer->makeHidden(['email_verified_at', 'microsoft_token', 'created_at', 'updated_at', 'phone', 'city', 'zip_code', 'address']);
                    }
                    // Nascondere i dati utente se è stato aperto dal supporto
                    if ($ticket->user->is_admin) {
                        $ticket->user->id = 1;
                        $ticket->user->name = "Supporto";
                        $ticket->user->surname = "";
                        $ticket->user->email = "Supporto";
                    }
                    // $ticket->append('unread_admins_messages');
                }
                return $ticketsTemp;
            }
        });

        return response([
            'tickets' => $tickets,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create() {
        //

        return response([
            'message' => 'Please use /api/store to create a new ticket',
        ], 404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {

        $user = $request->user();

        $fields = $request->validate([
            'description' => 'required|string',
            'type_id' => 'required|int',
        ]);

        $ticketType = TicketType::find($fields['type_id']);
        $group = $ticketType->groups->first();
        $groupId = $group ? $group->id : null;

        $ticket = Ticket::create([
            'description' => $fields['description'],
            'type_id' => $fields['type_id'],
            'group_id' => $groupId,
            'user_id' => $user->id,
            'status' => '0',
            'company_id' => isset($request['company']) && $user["is_admin"] == 1 ? $request['company'] : $user->company_id,
            'file' => null,
            'duration' => 0,
            'sla_take' => $ticketType['default_sla_take'],
            'sla_solve' => $ticketType['default_sla_solve'],
            'priority' => $ticketType['default_priority'],
            'unread_mess_for_adm' => $user["is_admin"] == 1 ? 0 : 1,
            'unread_mess_for_usr' => $user["is_admin"] == 1 ? 1 : 0,
        ]);

        if ($request->file('file') != null) {
            $file = $request->file('file');
            $file_name = time() . '_' . $file->getClientOriginalName();
            $storeFile = $file->storeAs("tickets/" . $ticket->id . "/", $file_name, "gcs");
            $ticket->update([
                'file' => $file_name,
            ]);
        }

        cache()->forget('user_' . $user->id . '_tickets');
        cache()->forget('user_' . $user->id . '_tickets_with_closed');

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => json_encode($request['messageData']),
            // 'is_read' => 1
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $fields['description'],
            // 'is_read' => 0
        ]);

        // Associazioni ticket-hardware
        $hardwareFields = $ticketType->typeHardwareFormField();
        $addedHardware = [];
        foreach ($hardwareFields as $field) {
            if (isset($request['messageData'][$field->field_label])) {
                $hardwareIds = $request['messageData'][$field->field_label];
                foreach ($hardwareIds as $id) {
                    $hardware = Hardware::find($id);
                    if ($hardware) {
                        $ticket->hardware()->syncWithoutDetaching($id);
                        $addedHardware[] = $id;
                    }
                }
            }
        }
        HardwareAuditLog::create([
            'modified_by' => $user->id,
            'log_subject' => 'hardware_ticket',
            'log_type' => 'created',
            'new_data' => json_encode([
                'ticket_id' => $ticket->id,
                'hardware_ids' => $addedHardware,
            ]),
        ]);


        $brand_url = $ticket->brandUrl();

        // Debug: qualche elemento col name non viene trovato
        $firstMessage = $ticket->messages[0]->message;
        $data = json_decode($firstMessage, true);
        $ticketUser = $ticket->user;
        $company = $ticket->company;
        $ticketType =  $ticket->ticketType;
        $debugString = 'DEBUG: Ticket ID: ' . $ticket->id
            . ' - Ticket User: ' . ($ticketUser->name ?? 'No name')
            . (isset($data['office']) ? ' - Office set: ' . (Office::find($data['office'])->name ?? $data['office'])  : ' - Office not set')
            . (isset($data['referer_it']) ? ' - Referer IT set: ' . (User::find($data['referer_it'])->name ?? $data['referer_it']) : ' - Referer IT not set, ')
            . (isset($data['referer']) ? ($data['referer'] != '0' ? ' - Referer set: ' . (User::find($data['referer'])->name ?? $data['referer']) : ' - Referer set: ' . $data['referer']) : ' - Referer not set, ')
            . (' - Ticket Type name: ' . ($ticketType->name ?? 'No name'))
            . (' - Ticket company name: ' . ($company->name ?? 'No name'));
        Log::info($debugString);

        dispatch(new SendOpenTicketEmail($ticket, $brand_url));

        return response([
            'ticket' => $ticket,
        ], 201);
    }

    /**
     * Store newly created resources in storage, starting from a file.
     */
    public function storeMassive(Request $request) {
        $request->validate([
            'data' => 'required|string',
            'file' => 'required|file|mimes:xlsx,csv',
        ]);

        $user = $request->user();

        if ($user["is_admin"] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        $data = json_decode($request->data);

        $additionalData = []; // I tuoi dati aggiuntivi

        $additionalData['user'] = $user;
        $additionalData['formData'] = $data;

        try {
            Excel::import(new TicketsImport($additionalData), $request->file('file'));
            return response()->json(['success' => true, 'message' => 'Importazione completata con successo.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Errore durante l\'importazione.\\n\\n' . $e->getMessage()], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show($id, Request $request) {

        $user = $request->user();
        // $cacheKey = 'user_' . $user->id . '_tickets_show:' . $id;
        // cache()->forget($cacheKey);

        // $ticket = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($user, $id) {
        //     $item = Ticket::where('id', $id)->where('user_id', $user->id)->with(['ticketType' => function ($query) {
        //         $query->with('category');
        //     }, 'company', 'user', 'files'])->first();

        //     return [
        //         'ticket' => $item,
        //         'from' => time(),
        //     ];
        // });

        $ticket = Ticket::where('id', $id)->with([
            'ticketType' => function ($query) {
                $query->with('category');
            }, 
            'hardware' => function ($query) {
                $query->with('hardwareType');
            },
            'company', 'user', 'files'])->first();

        if ($ticket == null) {
            return response([
                'message' => 'Ticket not found',
            ], 404);
        }

        $ticket->user->makeHidden(["microsoft_token", "email_verified_at", "created_at", "updated_at", "phone", "city", "zip_code", "address"]);
        $ticket->company->makeHidden(["sla", "sla_take_low", "sla_take_medium", "sla_take_high", "sla_take_critical", "sla_solve_low", "sla_solve_medium", "sla_solve_high", "sla_solve_critical", "sla_prob_take_low", "sla_prob_take_medium", "sla_prob_take_high", "sla_prob_take_critical", "sla_prob_solve_low", "sla_prob_solve_medium", "sla_prob_solve_high", "sla_prob_solve_critical"]);

        // Se la richiesta è lato utente ed il ticket è stato aperto dal supporto, si deve nascondere il nome dell'utente che ha aperto il ticket
        if (!$user->is_admin && $ticket->user->is_admin) {
            $ticket->user->id = 1;
            $ticket->user->name = "Supporto";
            $ticket->user->surname = "";
            $ticket->user->email = "Supporto";
        }

        $groupIdExists = false;

        foreach ($user->groups as $group) {
            if ($group->id == $ticket["group_id"]) {
                $groupIdExists = true;
                break;
            }
        }

        // Può avere il ticket solo se: 
        // admin e del gruppo associato, 
        // company admin e della stessa azienda del ticket, 
        // della stessa azienda del ticket ed il creatore del ticket o se è il referente interno (non necessariamente company_admin).
        // titolare del dato dell'azienda del ticket.
        $authorized = false;
        if (
            ($user["is_admin"] == 1 && $groupIdExists) ||
            ($ticket->company_id == $user->company_id && $user["is_company_admin"] == 1) ||
            ($ticket->company_id == $user->company_id && $ticket->user_id == $user->id) ||
            (($ticket->referer() ? $ticket->referer()->id == $user->id : false)) ||
            ($ticket->company->data_owner_email == $user->email)
        ) {
            $authorized = true;
        }

        if (!$authorized) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        // Se l'utente è admin si devono impostare i messaggi degli utenti come letti, altrimenti si devono impostare i messaggi degli admin come letti.
        // Se si vuole mostrare quanti messaggi erano da leggere si potrebbe usare un async che posticipi l'azzeramento dei messaggi non letti, in modo da inviare le risposta prima della modifica.
        if ($user["is_admin"] == 1) {
            // $ticket->setUsersMessagesAsRead();
            // solo se l'admin è anche il gestore del ticket.
            if (isset($ticket->admin_user_id) && $ticket->admin_user_id == $user->id && $ticket->unread_mess_for_adm > 0) {
                $ticket->update(['unread_mess_for_adm' => 0]);
                cache()->forget('user_' . $user->id . '_tickets');
                cache()->forget('user_' . $user->id . '_tickets_with_closed');
            }
        } else if ($ticket->unread_mess_for_usr > 0) {
            // $ticket->setAdminsMessagesAsRead();
            $ticket->update(['unread_mess_for_usr' => 0]);
            cache()->forget('user_' . $user->id . '_tickets');
            cache()->forget('user_' . $user->id . '_tickets_with_closed');
        }

        return response([
            'ticket' => $ticket,
            'from' => time(),
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Ticket $ticket) {

        return response([
            'message' => 'Please use /api/update to update an existing ticket',
        ], 404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Ticket $ticket) {
        //

        $user = $request->user();
        // Ricrea la stringa della cacheKey per invalidarla, visto che c'è stata una modifica.
        $cacheKey = 'user_' . $user->id . '_tickets_show:' . $ticket->id;

        $fields = $request->validate([
            'duration' => 'required|string',
            'due_date' => 'required|date',
        ]);

        $ticket = Ticket::where('id', $ticket->id)->where('user_id', $user->id)->first();

        $ticket->update([
            'duration' => $fields['duration'],
            'due_date' => $fields['due_date'],
        ]);

        cache()->forget($cacheKey);

        return response([
            'ticket' => $ticket,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Ticket $ticket, Request $request) {
        //
        $user = $request->user();

        $ticket = Ticket::where('id', $ticket->id)->where('user_id', $user->id)->first();
        cache()->forget('user_' . $user->id . '_tickets');
        cache()->forget('user_' . $user->id . '_tickets_with_closed');

        $ticket->update([
            'status' => '5',
        ]);
    }

    public function updateStatus(Ticket $ticket, Request $request) {

        $request->validate([
            'status' => 'required|int',
        ]);
        $isAdminRequest = $request->user()["is_admin"] == 1;

        if (!$isAdminRequest) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        $ticket->fill([
            'status' => $request->status,
            'wait_end' => $request['wait_end'],
        ])->save();

        $ticketStages = config('app.ticket_stages');

        $update = TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => 'Stato del ticket modificato in "' . $ticketStages[$request->status] . '"',
            'type' => 'status',
        ]);

        dispatch(new SendUpdateEmail($update));

        // Invalida la cache per chi ha creato il ticket e per i referenti.
        $ticket->invalidateCache();

        return response([
            'ticket' => $ticket,
        ], 200);
    }

    public function addNote(Ticket $ticket, Request $request) {

        // $ticket->update([
        //     'status' => $request->status,
        // ]);
        $fields = $request->validate([
            'message' => 'required|string',
        ]);

        $update = TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => $request->message,
            'type' => 'note',
        ]);

        dispatch(new SendUpdateEmail($update));

        return response([
            'new-note' => $request->message,
        ], 200);
    }

    public function updateTicketPriority(Ticket $ticket, Request $request) {
        $fields = $request->validate([
            'priority' => 'required|string',
        ]);

        if ($request->user()["is_admin"] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        $priorities = ['low', 'medium', 'high', 'critical']; // Define the priorities array

        if (!in_array($fields['priority'], $priorities)) {
            return response([
                'message' => 'Invalid priority value.',
            ], 400);
        }

        $company = $ticket->company;
        $sla_take_key = "sla_take_" . $fields['priority'];
        $sla_solve_key = "sla_solve_" . $fields['priority'];
        $new_sla_take = $company[$sla_take_key];
        $new_sla_solve = $company[$sla_solve_key];

        if ($new_sla_take == null || $new_sla_solve == null) {
            return response([
                'message' => 'Company sla for ' . $fields['priority'] . ' priority must be set.',
            ], 400);
        }

        $old_priority = (isset($ticket['priority']) &&  strlen($ticket['priority']) > 0) ? $ticket['priority'] : "not set";

        $ticket->update([
            'priority' => $fields['priority'],
            'sla_take' => $new_sla_take,
            'sla_solve' => $new_sla_solve,
        ]);

        $update = TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => "Priorità del ticket modificata da " . $old_priority . " a " . $fields['priority'] . ". SLA aggiornata di conseguenza.",
            'type' => 'sla',
        ]);

        dispatch(new SendUpdateEmail($update));

        return response([
            'ticket' => $ticket,
        ], 200);
    }

    public function getTicketBlame(Ticket $ticket) {
        return response([
            'is_user_error' => $ticket['is_user_error'],
            'is_form_correct' => $ticket['is_form_correct'],
            'was_user_self_sufficient' => $ticket['was_user_self_sufficient'],
            'is_user_error_problem' => $ticket['is_user_error_problem'],
        ], 200);
    }

    public function updateTicketBlame(Ticket $ticket, Request $request) {
        $fields = $request->validate([
            'is_user_error' => 'required|boolean',
            'was_user_self_sufficient' => 'required|boolean',
            'is_form_correct' => 'required|boolean',
            'is_user_error_problem' => 'boolean',
        ]);

        if ($request->user()["is_admin"] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        // $old_value = $ticket['is_user_error'] ? 'Cliente' : 'Supporto';

        $ticket->update([
            'is_user_error' => $fields['is_user_error'],
            'was_user_self_sufficient' => $fields['was_user_self_sufficient'],
            'is_form_correct' => $fields['is_form_correct'],
            'is_user_error_problem' => $fields['is_user_error_problem'],
        ]);

        // $new_value = $fields['is_user_error'] ? 'Cliente' : 'Supporto';
        $new_value = $ticket['is_user_error'] ? 'Cliente' : 'Supporto';

        $update = TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => "Responsabilità del ticket assegnata a: " . $new_value,
            'type' => 'blame',
        ]);

        dispatch(new SendUpdateEmail($update));

        return response([
            'ticket' => $ticket,
        ], 200);
    }

    public function closeTicket(Ticket $ticket, Request $request) {

        $fields = $request->validate([
            'message' => 'required|string',
        ]);

        if (!$request->user()->is_admin) {
            return response([
                'message' => 'Only admins can close tickets.',
            ], 401);
        }

        $requestUser = $request->user();

        if (!$ticket->handler) {
            $ticket->update([
                'admin_user_id' => $requestUser->id,
            ]);

            $adminUser = User::where('id', $requestUser->id)->first();

            $update = TicketStatusUpdate::create([
                'ticket_id' => $ticket->id,
                'user_id' => $requestUser->id,
                'content' => "Modifica automatica: Ticket assegnato all'utente " . $requestUser->name . " " . ($requestUser->surname ?? ''),
                'type' => 'assign',
            ]);
        }

        $ticket->update([
            'status' => 5, // Si può impostare l'array di stati e prendere l'indice di "Chiuso" da lì
            'actual_processing_time' => $request->actualProcessingTime,
        ]);

        $update = TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $requestUser->id,
            'content' => $fields['message'],
            'type' => 'closing',
            'show_to_user' => $request->sendMail,
        ]);

        dispatch(new SendUpdateEmail($update));

        // Controllare se si deve inviare la mail (l'invio al data_owner e al cliente sono separati per dare maggiore scelta all'admin)
        if ($request->sendMail == true) {
            // Invio mail al cliente
            // sendMail($dafeultMail, $fields['message']);
            $brand_url = $ticket->brandUrl();
            dispatch(new SendCloseTicketEmail($ticket, $fields['message'], $brand_url));
        }

        // Controllare se si deve inviare la mail al data_owner (l'invio al data_owner e al cliente sono separati per dare maggiore scelta all'admin)
        if ($request->sendToDataOwner == true && (isset($ticket->company->data_owner_email) && filter_var($ticket->company->data_owner_email, FILTER_VALIDATE_EMAIL))) {
            // Invio mail al data_owner del cliente
            // sendMail($dafeultMail, $fields['message']);
            $brand_url = $ticket->brandUrl();
            dispatch(new SendCloseTicketEmail($ticket, $fields['message'], $brand_url, true));
        }

        // Invalida la cache per chi ha creato il ticket e per i referenti.
        $ticket->invalidateCache();

        return response([
            'ticket' => $ticket,
        ], 200);
    }



    public function assignToGroup(Ticket $ticket, Request $request) {

        $request->validate([
            'group_id' => 'required|int',
        ]);
        $user = $request->user();
        if ($user["is_admin"] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        $group = Group::where('id', $request->group_id)->first();

        if ($group == null) {
            return response([
                'message' => 'Group not found',
            ], 404);
        }

        $ticket->update([
            'group_id' => $request->group_id,
        ]);

        $update = TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => "Ticket assegnato al gruppo " . $group->name,
            'type' => 'group_assign',
        ]);

        dispatch(new SendUpdateEmail($update));

        // Va rimosso l'utente assegnato al ticket se non fa parte del gruppo
        if ($ticket->admin_user_id && !$group->users()->where('user_id', $ticket->admin_user_id)->first()) {
            $old_handler = User::find($ticket->admin_user_id);
            $ticket->update(['admin_user_id' => null]);

            $update = TicketStatusUpdate::create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'content' => "Modifica automatica: Ticket rimosso dall'utente " . $old_handler->name . ", perchè non è del gruppo " . $group->name,
                'type' => 'assign',
            ]);

            // Va modificato lo stato se viene rimosso l'utente assegnato al ticket. (solo se il ticket non è stato già chiuso)
            $ticketStages = config('app.ticket_stages');
            $index_status_nuovo = array_search("Nuovo", $ticketStages);
            $index_status_chiuso = array_search("Chiuso", $ticketStages);
            if ($ticket->status != $index_status_nuovo && $ticket->status != $index_status_chiuso) {
                // $old_status = $ticketStages[$ticket->status];
                $ticket->update(['status' => $index_status_nuovo]);
                $new_status = $ticketStages[$ticket->status];

                $update = TicketStatusUpdate::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $request->user()->id,
                    'content' => 'Modifica automatica: Stato del ticket modificato in "' . $new_status . '"',
                    'type' => 'status',
                ]);

                // Invalida la cache per chi ha creato il ticket e per i referenti.
                $ticket->invalidateCache();
            }
        }


        // Ticket va messo in attesa se si cambia il gruppo. Comportamento da confermare. --  Si è deciso di non metterlo in attesa.
        // Se deve ripartire da zero allora si può prendere la data della modifica come partenza, senza ulteriori cambi di stato.
        // $ticketStages = config('app.ticket_stages');

        // $index_in_attesa = array_search("In attesa", $ticketStages);
        // if ($ticket["status"] != $index_in_attesa){
        //     $ticket->update([
        //         'status' => $index_in_attesa
        //     ]);

        //     TicketStatusUpdate::create([
        //         'ticket_id' => $ticket->id,
        //         'user_id' => $request->user()->id,
        //         'content' => "Stato del ticket modificato in " . $index_in_attesa,
        //         'type' => 'status',
        //     ]);
        // }

        return response([
            'ticket' => $ticket,
        ], 200);
    }

    public function assignToAdminUser(Ticket $ticket, Request $request) {

        $request->validate([
            'admin_user_id' => 'required|int',
        ]);
        $isAdminRequest = $request->user()["is_admin"] == 1;

        if (!$isAdminRequest) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        $ticket->update([
            'admin_user_id' => $request->admin_user_id,
        ]);

        $adminUser = User::where('id', $request->admin_user_id)->first();

        $update = TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => "Ticket assegnato all'utente " . $adminUser->name . " " . ($adminUser->surname ?? ''),
            'type' => 'assign',
        ]);

        dispatch(new SendUpdateEmail($update));

        // Se lo stato è 'Nuovo' aggiornarlo in assegnato
        $ticketStages = config('app.ticket_stages');
        if ($ticketStages[$ticket->status] == 'Nuovo') {
            $index_status_assegnato = array_search('Assegnato', $ticketStages);
            $ticket->update(['status' => $index_status_assegnato]);
            $new_status = $ticketStages[$ticket->status];

            $update = TicketStatusUpdate::create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'content' => 'Modifica automatica: Stato del ticket modificato in "' . $new_status . '"',
                'type' => 'status',
            ]);

            // Invalida la cache per chi ha creato il ticket e per i referenti.
            $ticket->invalidateCache();
        }

        return response([
            'ticket' => $ticket,
        ], 200);
    }

    public function files(Ticket $ticket, Request $request) {
        $isAdminRequest = $request->user()["is_admin"] == 1;

        if ($isAdminRequest) {
            $files = TicketFile::where('ticket_id', $ticket->id)->get();
        } else {
            $files = TicketFile::where(['ticket_id' => $ticket->id, 'is_deleted' => false])->get();
        }

        return response([
            'files' => $files,
        ], 200);
    }

    public function storeFile($id, Request $request) {

        if ($request->file('file') != null) {
            $file = $request->file('file');
            $file_name = time() . '_' . $file->getClientOriginalName();
            $path = "tickets/" . $id . "/" . $file_name;
            $storeFile = $file->storeAs("tickets/" . $id . "/", $file_name, "gcs");
            $ticketFile = TicketFile::create([
                'ticket_id' => $id,
                'filename' => $file->getClientOriginalName(),
                'path' => $path,
                'extension' => $file->getClientOriginalExtension(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);

            return response([
                'ticketFile' => $ticketFile,
            ], 200);
        }
    }

    public function storeFiles($id, Request $request) {

        if ($request->hasFile('files')) {
            $files = $request->file('files');
            $storedFiles = [];
            $count = 0;
            if (is_array($files)) {
                foreach ($files as $file) {
                    $file_name = time() . '_' . $file->getClientOriginalName();
                    $path = "tickets/" . $id . "/" . $file_name;
                    $storeFile = $file->storeAs("tickets/" . $id . "/", $file_name, "gcs");
                    $ticketFile = TicketFile::create([
                        'ticket_id' => $id,
                        'filename' => $file->getClientOriginalName(),
                        'path' => $path,
                        'extension' => $file->getClientOriginalExtension(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);

                    $storedFiles[] = $ticketFile;
                    $count++;
                }
            } else {
                $file_name = time() . '_' . $files->getClientOriginalName();
                $path = "tickets/" . $id . "/" . $file_name;
                $storeFile = $files->storeAs("tickets/" . $id . "/", $file_name, "gcs");
                $ticketFile = TicketFile::create([
                    'ticket_id' => $id,
                    'filename' => $files->getClientOriginalName(),
                    'path' => $path,
                    'extension' => $files->getClientOriginalExtension(),
                    'mime_type' => $files->getMimeType(),
                    'size' => $files->getSize(),
                ]);

                $storedFiles[] = $ticketFile;
                $count++;
            }


            return response([
                'ticketFiles' => $storedFiles,
                'filesCount' => $count,
            ], 200);
        }

        return response([
            'message' => 'No files uploaded.',
        ], 400);
    }

    public function generatedSignedUrlForFile($id) {

        $ticketFile = TicketFile::where('id', $id)->first();

        /**
         * @disregard P1009 Undefined type
         */

        $url = Storage::disk('gcs')->temporaryUrl(
            $ticketFile->path,
            now()->addMinutes(65)
        );

        return response([
            'url' => $url,
        ], 200);
    }

    public function deleteFile($id, Request $request) {
        $user = $request->user();

        if ($user["is_admin"] != 1) {
            return response([
                'message' => 'Only admins can delete files.',
            ], 401);
        }

        $ticketFile = TicketFile::where('id', $id)->first();

        $success = $ticketFile->update([
            'is_deleted' => true,
        ]);

        if (!$success) {
            return response([
                'message' => 'Error deleting file.',
            ], 500);
        }

        return response([
            'message' => 'File deleted.',
        ], 200);
    }

    public function recoverFile($id, Request $request) {
        $user = $request->user();

        if ($user["is_admin"] != 1) {
            return response([
                'message' => 'Only admins can retcover files.',
            ], 401);
        }

        $ticketFile = TicketFile::where('id', $id)->first();

        $success = $ticketFile->update([
            'is_deleted' => false,
        ]);

        if (!$success) {
            return response([
                'message' => 'Error recovering file.',
            ], 500);
        }

        return response([
            'message' => 'File recovered.',
        ], 200);
    }


    /**
     * Show only the tickets belonging to the authenticated admin groups.
     */
    public function adminGroupsTickets(Request $request) {

        $user = $request->user();

        if ($user["is_admin"] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        /** LENTISSIMA!!!! */

        /*

        
        $tickets = [];
        foreach ($groups as $group) {
            $groupTickets = $group->ticketsWithUser;
            foreach ($groupTickets as $ticket) {
                $ticket->referer = $ticket->referer();
                if ($ticket->referer) {
                    $ticket->referer->makeHidden(['email_verified_at', 'microsoft_token', 'created_at', 'updated_at', 'phone', 'city', 'zip_code', 'address']);
                }
                // $ticket->append('unread_users_messages');
            }
            $tickets = array_merge($tickets, $groupTickets->toArray());
        }

        */

        $groups = $user->groups;

        $withClosed = $request->query('with-closed') == 'true' ? true : false;

        // $tickets = Ticket::where("status", "!=", 5)->whereIn('group_id', $groups->pluck('id'))->with('user')->get();
        if ($withClosed) {
            $tickets = Ticket::whereIn('group_id', $groups->pluck('id'))->with('user')->get();
        } else {
            $tickets = Ticket::where("status", "!=", 5)->whereIn('group_id', $groups->pluck('id'))->with('user')->get();
        }

        return response([
            'tickets' => $tickets,
        ], 200);
    }

    /**
     * Show closing messages of the ticket
     */
    public function closingMessages(Ticket $ticket, Request $request) {

        $user = $request->user();

        if ($user["is_admin"] != 1 && $ticket->company_id != $user->company_id) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $closingUpdates = TicketStatusUpdate::where('ticket_id', $ticket->id)->where('type', 'closing')->where('show_to_user', true)->get();

        return response([
            'closing_messages' => $closingUpdates,
        ], 200);
    }

    public function report(Ticket $ticket, Request $request) {

        $user = $request->user();
        if ($user["is_admin"] != 1 && ($user["is_company_admin"] != 1 || $ticket->company_id != $user->company_id)) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }
        //? Webform

        $webform_data = json_decode($ticket->messages()->first()->message);

        if (isset($webform_data->office)) {
            $office = $ticket->company->offices()->where('id', $webform_data->office)->first();
            $webform_data->office = $office ? $office->name : null;
        } else {
            $webform_data->office = null;
        }

        if (isset($webform_data->referer)) {
            $referer = User::find($webform_data->referer);
            $webform_data->referer = $referer ? $referer->name . " " . $referer->surname : null;
        }

        if (isset($webform_data->referer_it)) {
            $referer_it = User::find($webform_data->referer_it);
            $webform_data->referer_it = $referer_it ? $referer_it->name . " " . $referer_it->surname : null;
        }


        $ticket->ticketType->category = $ticket->ticketType->category->get();

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

        $ticket->ticket_type = $ticket->ticketType ?? null;

        // Nasconde i dati per gli admin se l'utente non è admin
        if ($user["is_admin"] != 1) {
            $ticket->setRelation('status_updates', null);
            $ticket->makeHidden(["admin_user_id", "group_id", "priority", "is_user_error", "actual_processing_time"]);
        }

        return response([
            'data' => $ticket,
            'webform_data' => $webform_data,
            'status_updates' => $avanzamento,
            'closing_messages' => $closingMessage,
        ], 200);
    }

    public function batchReport(Request $request) {

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

        if (Cache::has($cacheKey)) {
            $tickets_data = Cache::get($cacheKey);

            return response([
                'data' => $tickets_data,
            ], 200);
        }

        $tickets = Ticket::where("company_id", $request->company_id)->whereBetween('created_at', [$request->from, $request->to])->get();

        $tickets_data = [];

        foreach ($tickets as $ticket) {

            $webform_data = json_decode($ticket->messages()->first()->message);

            if (isset($webform_data->office)) {
                $office = $ticket->company->offices()->where('id', $webform_data->office)->first();
                $webform_data->office = $office ? $office->name : null;
            } else {
                $webform_data->office = null;
            }

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

            $ticket->ticket_type = $ticket->ticketType ?? null;

            // Nasconde i dati per gli admin se l'utente non è admin
            if ($user["is_admin"] != 1) {
                $ticket->setRelation('status_updates', null);
                $ticket->makeHidden(["admin_user_id", "group_id", "priority", "is_user_error", "actual_processing_time"]);
            }

            $tickets_data[] = [
                'data' => $ticket,
                'webform_data' => $webform_data,
                'status_updates' => $avanzamento,
                'closing_message' => $closingMessage,
            ];
        }

        $tickets_batch_data = Cache::remember($cacheKey, now()->addMinutes(60), function () use ($tickets_data) {
            return $tickets_data;
        });

        return response([
            'data' => $tickets_batch_data,
        ], 200);
    }

    public function search(Request $request) {

        $search = $request->query('q');

        $tickets = Ticket::query()->when($search, function (Builder $q, $value) {
            /** 
             * @disregard Intelephense non rileva il metodo whereIn
             */
            return $q->whereIn('id', Ticket::search($value)->keys());
        })->with(['messages', 'company'])->get();

        $tickets_messages = TicketMessage::query()->when($search, function (Builder $q, $value) {
            /** 
             * @disregard Intelephense non rileva il metodo whereIn
             */
            return $q->whereIn('id', TicketMessage::search($value)->keys());
        })->get();

        $ticket_ids_with_messages = $tickets_messages->pluck('ticket_id')->unique();
        $tickets_with_messages = Ticket::whereIn('id', $ticket_ids_with_messages)->with(['messages', 'company'])->get();
        $tickets = $tickets->merge($tickets_with_messages);


        $tickets = $tickets->map(function ($ticket) {

            $messages_map = $ticket->messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'message' => $message->message,
                ];
            });

            return [
                'id' => $ticket->id,
                'ticket_opened_by' => $ticket->user->name . " " . $ticket->user->surname,
                'company' => $ticket->company->name,
                'description' => $ticket->description,
                'messages' => $messages_map,
            ];
        });

        return response()->json($tickets);
    }

    // public function hardware(Request $request, Ticket $ticket) {
    //     $authUser = $request->user();

    //     $authorized = false;
    //     if (
    //         $authUser->is_admin ||
    //         ($ticket->company_id == $authUser->company_id && $authUser["is_company_admin"] == 1) ||
    //         ($ticket->company_id == $authUser->company_id && $ticket->user_id == $authUser->id) ||
    //         (($ticket->referer() ? $ticket->referer()->id == $authUser->id : false)) ||
    //         ($ticket->company->data_owner_email == $authUser->email)
    //     ) {
    //         $authorized = true;
    //     }

    //     if (!$authorized) {
    //         return response([
    //             'message' => 'Unauthorized',
    //         ], 401);
    //     }

    //     $hardware = $ticket->hardware;

    //     return response()->json($tickets);
    // }

}
