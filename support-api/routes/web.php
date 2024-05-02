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
use App\Models\TicketReportExport;
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
    $user = User::find(1);
    $ticket = Ticket::find(1);

    Mail::to('c.perroni@ifortech.com')->send(new StatusUpdateMail($ticket, 1, $user));
});

Route::get('/test', function () {
    return "test";
});

Route::get('/mailtest', function() {

    $tickets = Ticket::where("status", "!=", 5)->with("company", "ticketType")->orderBy("created_at", "desc")->get();

    Mail::to("c.perroni@ifortech.com")->send(new \App\Mail\PlatformActivityMail($tickets));

    return [];
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
