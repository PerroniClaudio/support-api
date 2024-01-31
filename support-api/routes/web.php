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

require __DIR__ . '/auth.php';
