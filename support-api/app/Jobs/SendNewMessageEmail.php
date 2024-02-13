<?php

namespace App\Jobs;

use App\Mail\NewMessageEmail;
use App\Mail\WelcomeEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendNewMessageEmail implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ticket;
    protected $user;
    protected $message;
    protected $brand_url;


    /**
     * Create a new job instance.
     */
    public function __construct($ticket, $user, $message, $brand_url) {
        $this->ticket = $ticket;
        $this->user = $user;
        $this->message = $message;
        $this->brand_url = $brand_url;
    }

    /**
     * Execute the job.
     */
    public function handle(): void {
      // Se il ticket ha il referer invia la mail a lui
      $referer = $this->ticket->referer();
      // Se l'utente che ha aperto il ticket non è admin invia la mail
      if(!$this->ticket->user['is_admin'] || ($referer && $referer->email)) {
        $link = '';
        $mail = '';
        $logoRedirectUrl = '';
        // Se l'utente che ha inviato il messaggio è admin la mail viene inviata all'utente del ticket
        if($this->user->is_admin){
          $link = env('FRONTEND_URL') . '/support/user/ticket/' . $this->ticket->id;
          $logoRedirectUrl = config('app.frontend_url');
          // $companyAdmin = $this->ticket->company->users->where('is_company_admin', true)->first();
          // Invia mail all'azienda
          // $mail = $companyAdmin['email'];
          // Invia mail al referente o all'utente che ha aperto il ticket
          if($referer){
            $mail = $referer->email;
          } else {
            $mail = $this->ticket->user['email'];
          }
          // Mail::to($mail)->send(new NewMessageEmail("company", $this->ticket, $this->message, $link));
        } else {
          $link = env('FRONTEND_URL') . '/support/admin/ticket/' . $this->ticket->id;
          $logoRedirectUrl = config('app.frontend_url') . '/support/admin';
          $mail = env('MAIL_TO_ADDRESS');
          // Invia mail al supporto
          // Mail::to($mail)->send(new NewMessageEmail("support", $this->ticket, $this->message, $link));
        }
        Mail::to($mail)->send(new NewMessageEmail("support", $this->ticket, $this->message, $link, $this->brand_url, $logoRedirectUrl));
      }
    }
}
