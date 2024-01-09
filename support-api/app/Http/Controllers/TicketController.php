<?php

namespace App\Http\Controllers;

ini_set ('display_errors', 1);
ini_set ('display_startup_errors', 1);
error_reporting (E_ALL);

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

        // $tickets = Cache::remember($cacheKey, now()->addMinutes(60), function () use ($user) {
        //     if ($user["is_company_admin"] != 1) {
        //         return $user->company->tickets;
        //     } else {
        //         // return Ticket::where('user_id', $user->id)->get();
        //         return $user->tickets;
        //     }
        // });
        
        if ($user["is_company_admin"] == 1) {
            $tickets =  $user->company->tickets;
        } else {
            // return Ticket::where('user_id', $user->id)->get();
            $tickets =  $user->tickets;
        }

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

        return response([
            'ticket' => $ticket,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id, Request $request) {

        $user = $request->user();
        $cacheKey = 'user_' . $user->id . '_tickets_show:' . $id;
        cache()->forget($cacheKey);

        $ticket = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($user, $id) {
            $item = Ticket::where('id', $id)->where('user_id', $user->id)->with(['ticketType' => function ($query) {
                $query->with('category');
            }, 'company', 'user', 'files'])->first();

            return [
                'ticket' => $item,
                'from' => time(),
            ];
        });

        return response($ticket, 200);
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

        $ticket->update([
            'status' => $request->status,
        ]);

        TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => "Stato del ticket modificato in " . $request->status,
            'type' => 'status',
        ]);

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

        TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => $request->message,
            'type' => 'note',
        ]);

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

        TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => "Priorità del ticket modificata da " . $old_priority . " a " . $fields['priority'] . ". SLA aggiornata di conseguenza.",
            'type' => 'sla',
        ]);

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

        TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => $fields['message'],
            'type' => 'closing',
        ]);

        // Controllare se si deve inviare la mail
        if ($request->sendMail == true) {
            // Invio mail al cliente
            // sendMail($dafeultMail, $fields['message']);
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

        $ticket->update([
            'group_id' => $request->group_id,
        ]);

        $group = Group::where('id', $request->group_id)->first();

        TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => "Ticket assegnato al gruppo " . $group->name,
            'type' => 'group_assign',
        ]);

        // Ticket va messo in attesa se si cambia ill gruppo. Comportamento da confermare.
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

        TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => "Ticket assegnato all'utente " . $adminUser->name . " " . $adminUser->surname,
            'type' => 'assign',
        ]);

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
            now()->addMinutes(5)
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
            $groupTickets = $group->tickets;
            $tickets = array_merge($tickets, $groupTickets->toArray());
        }

        return response([
            'tickets' => $tickets,
        ], 200);
    }
}
