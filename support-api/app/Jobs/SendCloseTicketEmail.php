<?php

namespace App\Jobs;

use App\Mail\CloseTicketEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCloseTicketEmail implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ticket;
    protected $message;
    protected $brand_url;


    /**
     * Create a new job instance.
     */
    public function __construct($ticket, $message, $brand_url) {
        $this->ticket = $ticket;
        $this->message = $message;
        $this->brand_url = $brand_url;
    }

    /**
     * Execute the job.
     */
    public function handle(): void {

      // Se l'utente che ha creato il ticket non è admin gli invia la mail di chiusura
      if(!$this->ticket->user['is_admin']){
        $link = env('FRONTEND_URL') . '/support/user/ticket/' . $this->ticket->id;
        $mail = $this->ticket->user['email'];
        Mail::to($mail)->send(new CloseTicketEmail($this->ticket, $this->message, $link, $this->brand_url));
      }

    }
}
