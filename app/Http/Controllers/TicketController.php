<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache; // Otherwise no redis connection :)

class TicketController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Show only the tickets belonging to the authenticated user

        $user = auth()->user();
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
        //

        $user = auth()->user();

        $fields = $request->validate([
            'description' => 'required|string',
            'type' => 'required|string',
        ]);

        $ticket = Ticket::create([
            'description' => $fields['description'],
            'type' => $fields['type'],
            'user_id' => $user->id,
            'status' => '0',
            'company_id' => auth()->user()->company_id,
            'file' =>  $request->file('file') ? $request->file('file')->store('files') : null,
        ]);

        cache()->forget('user_' . $user->id . '_tickets');

        return response([
            'ticket' => $ticket,
        ], 201);

    }

    /**
     * Display the specified resource.
     */
    public function show(Ticket $ticket)
    {

        $user = auth()->user();
        $cacheKey = 'user_' . $user->id . '_tickets_show:' . $ticket->id;

        $tickets = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($user) {
            return Ticket::where('id', $ticket->id)->where('user_id', auth()->id())->first();
        });

        return response([
            'ticket' => $ticket,
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
    public function update(Request $request, Ticket $ticket)
    {
        //

        $user = auth()->user();
        $cacheKey = 'user_' . $user->id . '_tickets_show:' . $ticket->id;

        $fields = $request->validate([
            'duration' => 'required|string',
            'due_date' => 'required|date',
        ]);

        $ticket = Ticket::where('id', $ticket->id)->where('user_id', auth()->id())->first();

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

        $ticket = Ticket::where('id', $ticket->id)->where('user_id', auth()->id())->first();
        cache()->forget('user_' . $user->id . '_tickets');

        $ticket->update([
            'status' => '5',
        ]);
    }
}
