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
use App\Models\TicketType;
use App\Models\TicketTypeCategory;
use Illuminate\Support\Facades\Schema;

ini_set ('display_errors', 1);
ini_set ('display_startup_errors', 1);
error_reporting (E_ALL);

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
    return "test";

});

Route::get('/welcome', function () {
    return "welcome";

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

    // CREA TIPO per l'azienda (indicare l'id dell'azienda)
    // $newTypes = TicketType::factory()->count(2)->create([
    //     'company_id' => 19,
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

    

});

require __DIR__ . '/auth.php';
