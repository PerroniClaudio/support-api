<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketStatusUpdate;
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
    public function index()
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

        if($request->file('file') != null) {
            $file = $request->file('file');
            $file_name = time() . '_' . $file->getClientOriginalName();
            $storeFile = $file->storeAs("test", $file_name, "gcs");  
        }

        $ticket = Ticket::create([
            'description' => $fields['description'],
            'type_id' => $fields['type_id'],
            'user_id' => $user->id,
            'status' => '0',
            'company_id' => $user->company_id,
            'file' =>  $request->file('file') != null  ? $file_name : null,
            'duration' => 0
        ]);

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


        $ticket = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($user, $id) {
            $item = Ticket::where('id', $id)->where('user_id', $user->id)->with(['ticketType','company', 'user'])->first();

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
    public function destroy(Ticket $ticket)
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
}
