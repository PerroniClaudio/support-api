<?php

namespace App\Http\Controllers;

ini_set ('display_errors', 1);
ini_set ('display_startup_errors', 1);
error_reporting (E_ALL);

use App\Jobs\SendOpenTicketEmail;
use App\Jobs\SendCloseTicketEmail;
use App\Jobs\SendUpdateEmail;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketStatusUpdate;
use App\Models\TicketFile;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache; // Otherwise no redis connection :)
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;


class TicketController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request) {
        // Show only the tickets belonging to the authenticated user

        $user = $request->user();
        $cacheKey = 'user_' . $user->id . '_tickets';

        $tickets = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($user) {
            if ($user["is_company_admin"] == 1) {
                return  $user->company->tickets;
            } else {
                // return Ticket::where('user_id', $user->id)->get();
                // $tickets =  $user->tickets;
                return $user->tickets->merge($user->refererTickets());
                
            }
        });
        
        // if ($user["is_company_admin"] == 1) {
        //     $tickets =  $user->company->tickets;
        // } else {
        //     // return Ticket::where('user_id', $user->id)->get();
        //     // $tickets =  $user->tickets;
        //     $tickets = $user->tickets->merge($user->refererTickets());
            
        // }

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

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => json_encode($request['messageData']),
            'is_read' => 0
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $fields['description'],
            'is_read' => 0
        ]);

        $brand_url = $ticket->brandUrl();
        dispatch(new SendOpenTicketEmail($ticket, $brand_url));

        return response([
            'ticket' => $ticket,
        ], 201);
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

        $ticket = Ticket::where('id', $id)->with(['ticketType' => function ($query) {
            $query->with('category');
        }, 'company', 'user', 'files'])->first();

        if($ticket == null){
            return response([
                'message' => 'Ticket not found',
            ], 404);
        }

        $groupIdExists = false;

        foreach ($user->groups as $group) {
            if ($group->id == $ticket["group_id"]) {
                $groupIdExists = true;
                break;
            }
        }

        // Può avere il ticket solo se admin e del gruppo associato, company admin e della stessa azienda del ticket, se è e della stessa azienda del ticket ed il creatore del ticket o se è il referente interno (non necessariamente company_admin).
        $authorized = false;
        if (
            ($user["is_admin"] == 1 && $groupIdExists) ||
            ($ticket->company_id == $user->company_id && $user["is_company_admin"] == 1) ||
            ($ticket->company_id == $user->company_id && $ticket->user_id == $user->id) ||
            (($ticket->referer() ? $ticket->referer()->id == $user->id : false))
        ) {
            $authorized = true;
        }
        
        if (!$authorized) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
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

    public function updateTicketBlame(Ticket $ticket, Request $request) {
        $fields = $request->validate([
            'is_user_error' => 'required|boolean',
        ]);

        if ($request->user()["is_admin"] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        // $old_value = $ticket['is_user_error'] ? 'Cliente' : 'Supporto';

        $ticket->update([
            'is_user_error' => $fields['is_user_error']
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

        $ticket->update([
            'status' => 5, // Si può impostare l'array di stati e prendere l'indice di "Chiuso" da lì
        ]);

        $update = TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => $fields['message'],
            'type' => 'closing',
            'show_to_user' => $request->sendMail,
        ]);

        dispatch(new SendUpdateEmail($update));

        // Controllare se si deve inviare la mail
        if ($request->sendMail == true) {
            // Invio mail al cliente
            // sendMail($dafeultMail, $fields['message']);
            $brand_url = $ticket->brandUrl();
            dispatch(new SendCloseTicketEmail($ticket, $fields['message'], $brand_url));
        }

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

        if($group == null){
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
        if($ticket->admin_user_id && !$group->users()->where('user_id', $ticket->admin_user_id)->first()){
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
            if($ticket->status != $index_status_nuovo && $ticket->status != $index_status_chiuso){
                // $old_status = $ticketStages[$ticket->status];
                $ticket->update(['status' => $index_status_nuovo]);
                $new_status = $ticketStages[$ticket->status];

                $update = TicketStatusUpdate::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $request->user()->id,
                    'content' => 'Modifica automatica: Stato del ticket modificato in "' . $new_status . '"',
                    'type' => 'status',
                ]);
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
            'content' => "Ticket assegnato all'utente " . $adminUser->name . " " . $adminUser->surname,
            'type' => 'assign',
        ]);

        dispatch(new SendUpdateEmail($update));

        return response([
            'ticket' => $ticket,
        ], 200);
    }

    public function files(Ticket $ticket, Request $request) {

        $files = TicketFile::where('ticket_id', $ticket->id)->get();

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

    public function generatedSignedUrlForFile($id) {

        $ticketFile = TicketFile::where('id', $id)->first();

        $url = Storage::disk('gcs')->temporaryUrl(
            $ticketFile->path,
            now()->addMinutes(65)
        );

        return response([
            'url' => $url,
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

        $groups = $user->groups;

        $tickets = [];
        foreach ($groups as $group) {
            $groupTickets = $group->ticketsWithUser;
            $tickets = array_merge($tickets, $groupTickets->toArray());
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
}
