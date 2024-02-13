<?php

namespace App\Jobs;

use App\Mail\OpenTicketEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendOpenTicketEmail implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ticket;
    protected $brand_url;


    /**
     * Create a new job instance.
     */
    public function __construct($ticket, $brand_url) {
        $this->ticket = $ticket;
        $this->brand_url = $brand_url;
    }

    /**
     * Execute the job.
     */
    public function handle(): void {

      $company = $this->ticket->company;
      $ticketType =  $this->ticket->ticketType;
      $category = $ticketType->category;
      // Se l'utente che ha creato il ticket non è admin invia la mail al supporto
      if(!$this->ticket->user['is_admin']){
        $link = env('FRONTEND_URL') . '/support/admin/ticket/' . $this->ticket->id;
        $mail = env('MAIL_TO_ADDRESS');
        // Inviarla anche a tutti i membri del gruppo?
        Mail::to($mail)->send(new OpenTicketEmail($this->ticket, $company, $ticketType, $category, $link, $this->brand_url));
      } 
      
      // In ogni caso, se il referente è impostato, invia la mail al referente.
      $referer = $this->ticket->referer();
      if($referer && $referer->email){
        $link = env('FRONTEND_URL') . '/support/user/ticket/' . $this->ticket->id;
        Mail::to($referer->email)->send(new OpenTicketEmail($this->ticket, $company, $ticketType, $category, $link, $this->brand_url));
      }
      
    }
}