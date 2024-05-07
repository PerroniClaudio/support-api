<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UpdateEmail extends Mailable
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

    public $updateTypes;
    // public $updateTypes = config('app.update_types');
    // public $updateTypes = [
    //   "assign" => "Assegnazione", 
    //   "status" => "Stato", 
    //   "sla" => "SLA", 
    //   "closing" => "Chiusura", 
    //   "note" => "Nota", 
    //   "blame" => "Colpa", 
    //   "group_assign" => "Assegnazione gruppo"
    // ];

    public $previewText; // Testo visualizzato nella preview dell'email
    
    /**
     * Create a new message instance.
     */
    public function __construct(public Ticket $ticket, public $company, public $ticketType, public $category, public $link, public $update, public $user)
    {
        //
        $this->updateTypes = config('app.update_types');

        $this->previewText = $this->updateTypes[$this->update->type] . " ticket " . $this->ticket->id . " - " . $this->update->content;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Update ticket',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.statusupdate',
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
