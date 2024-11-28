<?php

namespace App\Jobs;

use App\Mail\OpenMassiveTicketEmail;
use App\Mail\OpenTicketEmail;
use App\Models\Group;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendOpenMassiveTicketEmail implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ticketsInfo;
    protected $brand_url;


    /**
     * Create a new job instance.
     */
    public function __construct($ticketsInfo, $brand_url) {
        $this->ticketsInfo = $ticketsInfo;
        $this->brand_url = $brand_url;
    }

    /**
     * Execute the job.
     */
    public function handle(): void {
      $sampleTicket = Ticket::find($this->ticketsInfo[0]['id']);

      $ticketUser = $sampleTicket->user;
      $company = $sampleTicket->company;
      $ticketType =  $sampleTicket->ticketType;
      $category = $ticketType->category;
      $group = Group::where('id', $sampleTicket->group_id)->first();
      $groupEmail = $group ? $group->email : null;

      $supportMail = env('MAIL_TO_ADDRESS');
      // Inviarla anche a tutti i membri del gruppo?
      // In ogni caso invia la mail al supporto ed al gruppo di appartenenza.
      Mail::to($supportMail)->send(new OpenMassiveTicketEmail($this->ticketsInfo, $company, $ticketType, $category, $this->brand_url, "admin"));
      if($groupEmail){
        Mail::to($groupEmail)->send(new OpenMassiveTicketEmail($this->ticketsInfo, $company, $ticketType, $category, $this->brand_url, "admin"));
      }
      // Altrimenti si potrebbe inviare una mail al supporto per avvisare che il gruppo non ha un indirizzo email associato. Utile solo per i gruppi preesistenti.
 
      // Se l'utente che ha creato il ticket non è admin invia la mail anche a lui.
      if(!$ticketUser['is_admin']){
        if($ticketUser['email']){
          Mail::to($ticketUser['email'])->send(new OpenMassiveTicketEmail($this->ticketsInfo, $company, $ticketType, $category, $this->brand_url, "user"));
        }
      } 

      $referer = $sampleTicket->referer();
      $refererIT = $sampleTicket->refererIT();

      // Se il referente in sede è impostato ed è diverso dall'utente che ha aperto il ticket, gli invia la mail.
      if($referer && $referer->id !== $ticketUser->id && $referer->email){
        Mail::to($referer->email)->send(new OpenMassiveTicketEmail($this->ticketsInfo, $company, $ticketType, $category, $this->brand_url, "referer"));
      }

      // Se il referente IT è impostato ed è diverso dall'utente e dalreferente in sede, gli invia la mail.
      if($refererIT && ($referer ? $refererIT->id !== $referer->id : true) && $refererIT->id !== $ticketUser->id && $refererIT->email){
        Mail::to($refererIT->email)->send(new OpenMassiveTicketEmail($this->ticketsInfo, $company, $ticketType, $category, $this->brand_url, "referer_it"));
      }
      
    }
}