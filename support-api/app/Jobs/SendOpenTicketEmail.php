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

      $ticketUser = $this->ticket->user;
      $company = $this->ticket->company;
      $ticketType =  $this->ticket->ticketType;
      $category = $ticketType->category;
      $adminLink = env('FRONTEND_URL') . '/support/admin/ticket/' . $this->ticket->id;
      $userlink = env('FRONTEND_URL') . '/support/user/ticket/' . $this->ticket->id;

      $supportMail = env('MAIL_TO_ADDRESS');
      // Inviarla anche a tutti i membri del gruppo?
      // In ogni caso invia la mail al supporto
      Mail::to($supportMail)->send(new OpenTicketEmail($this->ticket, $company, $ticketType, $category, $adminLink, $this->brand_url, "admin"));
      // Se l'utente che ha creato il ticket non è admin invia la mail anche a lui.
      if(!$ticketUser['is_admin']){
        if($ticketUser['email']){
          Mail::to($ticketUser['email'])->send(new OpenTicketEmail($this->ticket, $company, $ticketType, $category, $userlink, $this->brand_url, "user"));
        }
      } 

      $referer = $this->ticket->referer();
      $refererIT = $this->ticket->refererIT();

      // Se il referente in sede è impostato ed è diverso dall'utente che ha aperto il ticket, gli invia la mail.
      if($referer && $referer->id !== $ticketUser->id && $referer->email){
        Mail::to($referer->email)->send(new OpenTicketEmail($this->ticket, $company, $ticketType, $category, $userlink, $this->brand_url, "referer"));
      }

      // Se il referente IT è impostato ed è diverso dall'utente e dalreferente in sede, gli invia la mail.
      if($refererIT && ($referer ? $refererIT->id !== $referer->id : true) && $refererIT->id !== $ticketUser->id && $refererIT->email){
        Mail::to($refererIT->email)->send(new OpenTicketEmail($this->ticket, $company, $ticketType, $category, $userlink, $this->brand_url, "referer_it"));
      }
      
    }
}