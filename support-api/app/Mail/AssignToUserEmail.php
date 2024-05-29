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

class AssignToUserEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $stages = [
      "Nuovo", 
      "Assegnato", 
      "In corso", 
      "In attesa", 
      "Risolto", 
      "Chiuso"
    ];

    public $previewText;
    
    /**
     * Create a new message instance.
     */
    public function __construct(public Ticket $ticket, public $company, public $ticketType, public $category, public $link, public $update, public $user)
    {
        //
        $this->previewText = $this->ticket->description;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Assegnazione ticket ' . $this->ticket->id . ' - ' . $this->ticketType->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.assigntouser',
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
