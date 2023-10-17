<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketMesage;
use Illuminate\Http\Request;

class TicketMesageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        $fields = $request->validate([
            'ticket_id' => 'required|integer',
        ]);

        $ticket = Ticket::where('ticket_id', $fields['ticket_id'])->get();

        if(!$ticket) {
            return response([
                'message' => 'Ticket not found'
            ], 404);
        }

        $ticket_messages = TicketMesage::where('ticket_id', $fields['ticket_id'])->get();

        return response([
            'ticket_messages' => $ticket_messages,
        ], 200);

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //

        return response([
            'message' => 'Please use /api/store to create a new message',
        ], 404);

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //

        $fields = $request->validate([
            'message' => 'required|string',
            'ticket_id' => 'required|integer',
        ]);

        $ticket_message = TicketMesage::create([
            'message' => $fields['message'],
            'ticket_id' => $fields['ticket_id'],
            'user_id' => auth()->id(),
        ]);

        return response([
            'ticket_message' => $ticket_message,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(TicketMesage $ticketMesage)
    {
        //Not allowed 

        return response([
            'message' => 'Not allowed',
        ], 404);


    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TicketMesage $ticketMesage)
    {
        //

        return response([
            'message' => 'Not allowed',
        ], 404);

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TicketMesage $ticketMesage)
    {
        //

        $fields = $request->validate([
            'is_read' => 'required|boolean',
        ]);

        $ticket_message = TicketMesage::where('id', $ticketMesage->id)->first();

        if(!$ticket_message) {
            return response([
                'message' => 'Ticket message not found'
            ], 404);
        }

        $ticket_message->is_read = $fields['is_read'];

        $ticket_message->save();

        return response([
            'ticket_message' => $ticket_message,
        ], 200);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TicketMesage $ticketMesage)
    {
        //

        $ticket_message = TicketMesage::where('id', $ticketMesage->id)->where('user_id', auth()->id())->first();

        if(!$ticket_message) {
            return response([
                'message' => 'Ticket message not found'
            ], 404);
        }

        $ticket_message->delete();

        return response([
            'message' => 'Ticket message deleted'
        ], 200);
    }
}
