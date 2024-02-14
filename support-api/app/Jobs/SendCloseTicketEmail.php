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
      $userLink = env('FRONTEND_URL') . '/support/user/ticket/' . $this->ticket->id;
      $referer = $this->ticket->referer();
      $refererIT = $this->ticket->refererIT();
      
      // Inviare la mail di chiusura all'utente che l'ha aperto, se non è admin
      if(!$this->ticket->user['is_admin'] && $this->ticket->user->email){
        Mail::to($this->ticket->user->email)->send(new CloseTicketEmail($this->ticket, $this->message, $userLink, $this->brand_url));
      }
      
      // Inviare la mail di chiusura al referente IT
      if($refererIT && $refererIT->email){
        Mail::to($refererIT->email)->send(new CloseTicketEmail($this->ticket, $this->message, $userLink, $this->brand_url));
      } 

      // Inviare la mail di chiusura al referente in sede, se è diverso dal referente IT
      if($referer && ($referer->id !== ($refererIT->id ?? null)) && $referer->email){
        Mail::to($referer->email)->send(new CloseTicketEmail($this->ticket, $this->message, $userLink, $this->brand_url));
      } 
      
    }
}
