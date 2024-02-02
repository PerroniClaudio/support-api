<?php

namespace App\Jobs;

use App\Mail\UpdateEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendUpdateEmail implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $update;


    /**
     * Create a new job instance.
     */
    public function __construct($update) {
        $this->update = $update;
    }

    /**
     * Execute the job.
     */
    public function handle(): void {

      $ticket = $this->update->ticket;

      // Se l'utente che ha creato il ticket non è admin invia la mail al supporto.
      if(!$ticket->user['is_admin']){
        // Tipo di update: status, note, sla, closing, group_assign, assign, 
        // Per ora invio solo le note
        // if($this->update["type"] == "note"){
          $user = $this->update->user;
          $company = $ticket->company;
          $ticketType =  $ticket->ticketType;
          $category = $ticketType->category;
          $link = env('FRONTEND_URL') . '/support/admin/ticket/' . $ticket->id;
          $mail = env('MAIL_TO_ADDRESS');
          // Inviarla anche a tutti i membri del gruppo?
          Mail::to($mail)->send(new UpdateEmail($ticket, $company, $ticketType, $category, $link, $this->update, $user));
        // }
      }

    }
}