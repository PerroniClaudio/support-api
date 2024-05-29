<?php

namespace App\Mail;

use App\Models\Ticket;
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

    /**
     * Create a new message instance.
     */
    public function __construct(public Ticket $ticket, public $company, public $ticketType, public $category, public $link, public $brand_url, public $mailType)
    {
        // Utente che ha aperto il ticket
        $user = User::find($this->ticket->user_id);
        $this->previewText = ($user->is_admin ? "Supporto" : ($user->name . ' ' . $user->surname ?? '')) . ' - ' . $this->ticket->description;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Apertura ticket ' . $this->ticket->id . ' ' . $this->ticketType->name,
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
