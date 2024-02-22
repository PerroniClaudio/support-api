<?php

use App\Jobs\SendCloseTicketEmail;
use App\Jobs\SendNewMessageEmail;
use App\Jobs\SendWelcomeEmail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
use App\Mail\StatusUpdateMail;
use App\Models\User;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\Supplier;
use App\Models\TicketStats;
use App\Models\TicketType;
use App\Models\TicketTypeCategory;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::get('/info', function () {
    phpinfo();
});

Route::get('/testmail', function () {
    //$user = User::where('email', 'c.perroni@ifortech.com')->first();
    //Mail::to($user->email)->send(new WelcomeEmail($user));

    // $ticket = Ticket::where('id', 1)->with(['ticketType' => function ($query) {
    //     $query->with('category');
    // }, 'company', 'user', 'files'])->first();

    // $sender = User::find(1);
    // $ticketMessage = new TicketMessage(
    //     [
    //         'ticket_id' => 1,
    //         'user_id' => 1,
    //         'message' => 'test message',
    //         'attachment' => null,
    //         'is_read' => 0
    //     ]
    // );

    // Mail::to('c.perroni@ifortech.com')->send(new TicketMessageMail($ticket, $sender, $ticketMessage));

    $user = User::find(1);
    $ticket = Ticket::find(1);

    Mail::to('c.perroni@ifortech.com')->send(new StatusUpdateMail($ticket, 1, $user));
});

Route::get('/test', function () {

    function getNightHours($start, $end) {
        $nightHours = 0;
        $startDay = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();
        $nightStart = $startDay->copy()->addHours(18);
        $nightEnd = $startDay->copy()->addHours(8);

        if ($startDay->isSameDay($endDay)) {
            if ($start->isBefore($nightStart) && $end->isAfter($nightEnd)) {
                $nightHours = 10;
            } else if ($start->isBefore($nightStart) && $end->isBefore($nightEnd)) {
                $nightHours = $start->diffInHours($nightEnd);
            } else if ($start->isAfter($nightStart) && $end->isAfter($nightEnd)) {
                $nightHours = $end->diffInHours($nightStart);
            }
        } else {
            $nightHours = $start->diffInHours($nightEnd);
            $nightHours += $end->diffInHours($nightStart);
        }

        return $nightHours;
    }

    $openTicekts = Ticket::where('status', '!=', '5')->with('ticketType.category')->get();

    $results = [
        'incident_open' => 0,
        'incident_in_progress' => 0,
        'incident_waiting' => 0,
        'incident_out_of_sla' => 0,
        'request_open' => 0,
        'request_in_progress' => 0,
        'request_waiting' => 0,
        'request_out_of_sla' => 0
    ];

    foreach ($openTicekts as $ticket) {


        switch ($ticket->ticketType->category->is_problem) {
            case 1:
                switch ($ticket->status) {
                    case 0:
                        $results['incident_open']++;
                        break;
                    case 1:
                    case 2:
                        $results['incident_in_progress']++;
                        break;
                    case 3:
                        $results['incident_waiting']++;
                        break;
                }
                break;
            case 0:
                switch ($ticket->status) {
                    case 0:
                        $results['request_open']++;
                        break;
                    case 1:
                    case 2:
                        $results['request_in_progress']++;
                        break;
                    case 3:
                        $results['request_waiting']++;
                        break;
                }
                break;
        }

        /*
        
            Per verificare se il ticket in sla bisogna utilizzare il campo sla_solve del ticket. 
            Bisogna verificare che la differenza tra la data attuale e la data di creazione del ticket sia minore della data di sla_solve.
            Calcolando questa differenza bisogna tenere conto del fatto che le ore tra mezzanotte e le 8 del mattino non vanno calcolate.
            Calcolando questa differenza bisogna tenere conto del fatto che le ore tra le 18 e mezzanotte non vanno calcolate.
            Calcolando questa differenza bisogna tenere conto che il sabato, la domenica ed i giorni festivi non vanno calcolati.

        */

        $sla = $ticket->sla_solve / 60;
        $ticketCreationDate = $ticket->created_at;
        $now = now();

        $diffInHours = $ticketCreationDate->diffInHours($now);

        // ? Rimuovere le ore tra mezzanotte e le 8 del mattino da $diffInHours

        $diffInHours -= getNightHours($ticketCreationDate, $now);

        // ? Rimuovere le ore tra le 18:00 e mezzanotte da $diffInHours

        $diffInHours -= getNightHours($ticketCreationDate->copy()->addHours(18), $now);

        // ? Rimuovere le ore di sabato e domenica da $diffInHours

        $diffInHours -= $ticketCreationDate->diffInDaysFiltered(function ($date) {
            return $date->isWeekend();
        }, $now);

        // ? Se il ticket è rimasto in attesa è necessario rimuovere le ore in cui è rimasto in attesa.

        $waitingHours = $ticket->waitingHours();
        $diffInHours -= $waitingHours;


        if ($diffInHours > $sla) {
            switch ($ticket->ticketType->category->is_problem) {
                case 1:
                    $results['incident_out_of_sla']++;
                    break;
                case 0:
                    $results['request_out_of_sla']++;
                    break;
            }
        }
    }

    $ticketStats = TicketStats::create([
        'incident_open' => $results['incident_open'],
        'incident_in_progress' => $results['incident_in_progress'],
        'incident_waiting' => $results['incident_waiting'],
        'incident_out_of_sla' => $results['incident_out_of_sla'],
        'request_open' => $results['request_open'],
        'request_in_progress' => $results['request_in_progress'],
        'request_waiting' => $results['request_waiting'],
        'request_out_of_sla' => $results['request_out_of_sla'],
        'compnanies_opened_tickets' => "{}"
    ]);


    return $results;
});

Route::get('/welcome', function () {
    return "welcome";

    // return App\Models\Group::find(1)->level();
    // return App\Models\Group::find(1)->children;
    // return App\Models\Group::find(1)->getAllChildren();

    // $user = User::find(82);
    // return $user->tickets->merge($user->refererTickets());
    
    // return Ticket::find(73)->messages[0]->message;
    // return json_decode(Ticket::find(154)->messages[0]->message)->referer;
    // return $referer = User::find(json_decode(Ticket::find(108)->messages[0]->message)->referer);
    // return Ticket::find(73)->handler;
    // if($referer){
    //     return $referer;
    // }

    // $update = App\Models\TicketStatusUpdate::find(143);
    // dispatch(new App\Jobs\SendUpdateEmail($update));
    // return $update;

    // $user = User::find(13);
    // $ticket = Ticket::where('id', 91)->first();
    // $company = $ticket->company;
    // $ticketType =  $ticket->ticketType;
    // $category =  $ticketType->category;
    // $brand_url = $ticket->brandUrl();
    // dispatch(new App\Jobs\SendOpenTicketEmail($ticket, $brand_url));
    // $brand_url = $ticket->brandUrl();
    // dispatch(new SendCloseTicketEmail($ticket, 'Messaggio di chiusura', $brand_url));
    // // dispatch(new SendWelcomeEmail($user, "URL non serve"));
    // dispatch(new SendNewMessageEmail($ticket, $user, "URL non serve"));
    // return "mail sent";


});

Route::get('/factory', function () {
    return "factory";
    // Con make si crea ma non si salva, con create si crea e si salva sul db

    // Utenti: usare utenti admin (possono aprire tutti i tipi di ticket)
    // Tipo: 66 71

    // CREA AZIENDA
    // $newCompanies = Company::factory()->count(3)->create();
    // foreach ($newCompanies as $company) {
    //     echo $company;
    //     echo "<br><br>";
    // }

    // CREA CATEGORIA
    // $newCategories = TicketTypeCategory::factory()->count(2)->create();

    // CREA TIPO per l'azienda (indicare l'id dell'azienda)
    // $newTypes = TicketType::factory()->count(2)->create([
    //     'company_id' => 1,
    // ]);
    // $newTypes[] = TicketType::factory()->count(2)->create([
    //     'company_id' => 2,
    // ]);
    // $newTypes[] = TicketType::factory()->count(2)->create([
    //     'company_id' => 3,
    // ]);
    // foreach ($newTypes as $type) {
    //     echo $type;
    //     echo "<br><br>";
    // }

    // CREA TICKET (controllare i campi id)
    // $newTickets = Ticket::factory()->count(2)->create([
    //     'user_id' => 13,
    //     'company_id' => 19,
    //     'type_id' => 103,
    //     'group_id' => 1,
    //     'sla_take' => 240,
    //     'sla_solve' => 3000,
    //     'priority' => 'low',
    // ]);
    // foreach ($newTickets as $ticket) {
    //     echo $ticket;
    //     echo "<br><br>";
    // }

    // $type = TicketType::all()->random();
    // $company = $type->company;
    // // Si potrebbe pensare di prendere l'utente tra quelli dell'azienda del tipo di ticket.
    // $user = User::where('is_admin', 1)->get()->random();
    // $group = $type->groups->first();
    // $type['priority'] ;
    // $ticket = Ticket::factory()->create([
    //     'user_id' => $user->id,
    //     'company_id' => $company->id,
    //     'type_id' => $type->id,
    //     'group_id' => $group->id,
    //     'sla_take' => $type->default_sla_take ?? 240,
    //     'sla_solve' => $type->default_sla_solve ?? 3000,
    //     'priority' => $type->default_priority ?? 'low',
    // ]);
    // return $ticket;


});

require __DIR__ . '/auth.php';
