<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;

class TicketMessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($ticket_id, Request $request)
    {
        //

        $ticket = Ticket::where('id', $ticket_id)->with(['messages'])->get()->first();

        if(!$ticket) {
            return response([
                'message' => 'Ticket not found'
            ], 404);
        }

        return response([
            'ticket_messages' => $ticket->messages,
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

        $ticket_message = TicketMessage::create([
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
    public function show(TicketMessage $ticketMesage)
    {
        //Not allowed 

        return response([
            'message' => 'Not allowed',
        ], 404);


    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TicketMessage $ticketMesage)
    {
        //

        return response([
            'message' => 'Not allowed',
        ], 404);

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TicketMessage $ticketMesage)
    {
        //

        $fields = $request->validate([
            'is_read' => 'required|boolean',
        ]);

        $ticket_message = TicketMessage::where('id', $ticketMesage->id)->first();

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
    public function destroy(TicketMessage $ticketMesage)
    {
        //

        $ticket_message = TicketMessage::where('id', $ticketMesage->id)->where('user_id', auth()->id())->first();

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
