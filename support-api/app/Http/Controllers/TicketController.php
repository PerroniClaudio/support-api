<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketStatusUpdate;
use App\Models\TicketFile;
use App\Models\User;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache; // Otherwise no redis connection :)
use Illuminate\Support\Facades\Storage;


class TicketController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Show only the tickets belonging to the authenticated user

        $user = $request->user();
        $cacheKey = 'user_' . $user->id . '_tickets';

        $tickets = Cache::remember($cacheKey, now()->addMinutes(60), function () use ($user) {
            return Ticket::where('user_id', $user->id)->get();
        });
        
        return response([
            'tickets' => $tickets,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(){
        //

        return response([
            'message' => 'Please use /api/store to create a new ticket',
        ], 404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $user = $request->user();

        $fields = $request->validate([
            'description' => 'required|string',
            'type_id' => 'required|int',
        ]);

        
        $ticket = Ticket::create([
            'description' => $fields['description'],
            'type_id' => $fields['type_id'],
            'user_id' => $user->id,
            'status' => '0',
            'company_id' => isset($request['company']) && $user["is_admin"] == 1 ? $request['company'] : $user->company_id,
            'file' => null,
            'duration' => 0
        ]);

        if($request->file('file') != null) {
            $file = $request->file('file');
            $file_name = time() . '_' . $file->getClientOriginalName();
            $storeFile = $file->storeAs("tickets/" . $ticket->id . "/", $file_name, "gcs");  
            $ticket->update([
                'file' => $file_name,
            ]);
        }

        cache()->forget('user_' . $user->id . '_tickets');

        $ticketMessage = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => json_encode($request['messageData']),
            'is_read' => 0
        ]);

        $ticketMessage = TicketMessage::create([
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
    public function show($id, Request $request)
    {

        $user = $request->user();
        $cacheKey = 'user_' . $user->id . '_tickets_show:' . $id;
        cache()->forget($cacheKey);

        $ticket = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($user, $id) {
            $item = Ticket::where('id', $id)->where('user_id', $user->id) ->with(['ticketType' => function ($query) {
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
    public function update(Request $request, Ticket $ticket)
    {
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
    public function destroy(Ticket $ticket, Request $request)
    {
        //
        $user = $request->user();

        $ticket = Ticket::where('id', $ticket->id)->where('user_id', $user->id)->first();
        cache()->forget('user_' . $user->id . '_tickets');

        $ticket->update([
            'status' => '5',
        ]);
    }

    public function updateStatus(Ticket $ticket, Request $request) {

        $ticket->update([
            'status' => $request->status,
        ]);

        TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => "Stato del ticket modificato in " . $request->status,
        ]);

        return response([
            'ticket' => $ticket,
        ], 200);

    }

    public function assignToGroup(Ticket $ticket, Request $request) {

        $ticket->update([
            'group_id' => $request->group_id,
        ]);

        $group = Group::where('id', $request->group_id)->first();

        TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => "Ticket assegnato al gruppo " . $group->name,
        ]);

        return response([
            'ticket' => $ticket,
        ], 200);

    }

    public function assignToAdminUser(Ticket $ticket, Request $request) {

        $ticket->update([
            'admin_user_id' => $request->admin_user_id,
        ]);

        $adminUser = User::where('id', $request->admin_user_id)->first();

        TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => "Ticket assegnato all'utente " . $adminUser->name . " " . $adminUser->surname,
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

        if($request->file('file') != null) {
            $file = $request->file('file');
            $file_name = time() . '_' . $file->getClientOriginalName();
            $path = "tickets/" . $id . "/". $file_name;
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
     * Display a listing of the resource in the authenticated admin's group.
     */
    public function adminGroupsTickets(Request $request) {
        // Show only the tickets belonging to the authenticated admin groups
    
        $user = $request->user();
        $cacheKey = 'user_' . $user->id . '_tickets';
    
        $tickets = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($user) {
            if($user["is_admin"] == 1){
                // Get the group IDs associated with the user
                $groupIds = $user->groups->pluck('id');
    
                // Retrieve tickets where the group_id is in the groupIds array
                return Ticket::whereIn('group_id', $groupIds)->get();
            }
        });
    
    
        return response([
            'tickets' => $tickets,
        ], 200);
    }
}
