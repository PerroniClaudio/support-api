<?php

namespace App\Http\Controllers;

use App\Mail\TicketMessageMail;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class TicketMessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($ticket_id, Request $request)
    {
        //

        $ticket = Ticket::where('id', $ticket_id)->get()->first();

        if(!$ticket) {
            return response([
                'message' => 'Ticket not found'
            ], 404);
        }

        $tickemessages = TicketMessage::where('ticket_id', $ticket_id)->with(['user'])->get();

        return response([
            'ticket_messages' => $tickemessages,
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
    public function store($id, Request $request)
    {
        //

        $user = $request->user();

        $fields = $request->validate([
            'message' => 'required|string',
        ]);

        $ticket_message = TicketMessage::create([
            'message' => $fields['message'],
            'ticket_id' => $id,
            'user_id' => $user->id,
        ]);

        $ticket = Ticket::where('id', 1)->with(['ticketType' => function ($query) {
            $query->with('category');
        }])->first();

        if($user['is_admin'] == 1) {
            $ticket_message->is_read = 1;
            $ticket_message->save();
        } else {
            Mail::to('support@ifortech.com')->send(new TicketMessageMail($ticket, $user, $ticket_message));
        }

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
