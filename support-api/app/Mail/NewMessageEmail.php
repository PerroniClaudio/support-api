<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewMessageEmail extends Mailable
{
    use Queueable, SerializesModels;

    // "support", $this->ticket, $this->message
    public $previewText;
    public $ticketType;

    /**
     * Create a new message instance.
     */
    // public function __construct(public $destination, public Ticket $ticket, public $message, public $link, public $brand_url, public $url)
    public function __construct(public $mailType, public Ticket $ticket, public $message, public $link, public $brand_url, public $url, public User $sender)
    {
        //
        $this->ticketType = TicketType::find($this->ticket->type_id);
        $this->previewText =  ($sender->is_admin ? "Supporto" : ($sender->name . ' ' . $sender->surname ?? '')) . ' - ' . $this->message;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nuovo messaggio ticket ' . $this->ticket->id . ' - ' . $this->ticketType->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.newmessage',
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
