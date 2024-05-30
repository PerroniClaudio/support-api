<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\Office;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OpenTicketEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $previewText;
    public $form;
    public $user;

    /**
     * Create a new message instance.
     */
    public function __construct(public Ticket $ticket, public $company, public $ticketType, public $category, public $link, public $brand_url, public $mailType)
    {   
        // Utente che ha aperto il ticket
        $this->user = User::find($this->ticket->user_id);
        TicketMessage::find($this->ticket->messages->first());
        $this->previewText = $company->name . ' - ' . ($this->user->is_admin ? "Supporto" : ($this->user->name . ' ' . $this->user->surname ?? '')) . ' - ' . $this->ticket->description . ' - ';

        $firstMessage = $ticket->messages[0]->message;
        $data = json_decode($firstMessage, true);
        unset($data['description']);
        if(isset($data['office'])){
            $office = Office::find($data['office']);
            $data["Sede"] = $office->name . " - " . $office->city . ", " . $office->address . " " . $office->number;
            unset($data['office']);
        }
        if(isset($data['referer_it'])){
            $refererIT = User::find($data['referer_it']);
            $data["Referente IT"] = $refererIT->name . ' ' . $refererIT->surname ?? '';
            unset($data['referer_it']);
        }
        if(isset($data['referer'])){
            $referer = User::find($data['referer']);
            $data["Referente"] = $referer->name . ' ' . $referer->surname ?? '';
            unset($data['referer']);
        }

        $formText = '';
        foreach($data as $key => $value) {
            $formText .= htmlspecialchars($key . ': ' . $value, ENT_QUOTES, 'UTF-8', false) . '<br>';
        }
        $this->form = $formText;
        // $this->form = $data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Apertura ' . ($this->category->is_problem ? 'Incident' : 'Request') . ' n° ' . $this->ticket->id . ' - ' . $this->ticketType->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.openticket',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
