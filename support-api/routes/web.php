<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
use App\Mail\StatusUpdateMail;
use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketMessage;
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
    return "test";
    // $types = \App\Models\TicketType::with('category')->get();
    // $response ="";
    // foreach ($types as $type) {
    //     $response .= "<b>ID: </b>" . $type->id 
    //         . "<b> - Tipo: </b>" . $type->name 
    //         . "<b> - ID Categoria: </b>" . $type->ticket_type_category_id 
    //         . "<b> - Categoria: </b>" . $type->category->name 
    //         . "<b> - ID Compagnia: </b>" . $type->company_id 
    //         . "<b> - Eliminato: </b>" . $type->is_deleted 
    //         . "<br>";
    // }
    // return $response;
    
    // $category = \App\Models\TicketTypeCategory::where('id', 2)->first();
    // $types = $category->ticketTypes;
    // $response = "";
    // foreach ($types as $type) {
    //     $response .= "<b>ID: </b>" . $type->id 
    //         . "<b> - Tipo: </b>" . $type->name 
    //         . "<b> - ID Categoria: </b>" . $type->ticket_type_category_id 
    //         . "<b> - Categoria: </b>" . $type->category->name 
    //         . "<b> - ID Compagnia: </b>" . $type->company_id 
    //         . "<b> - Eliminato: </b>" . $type->is_deleted 
    //         . "<br>";
        
    // }
    // return $response;
});

require __DIR__ . '/auth.php';
