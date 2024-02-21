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
    $user = User::find(1);
    $ticket = Ticket::find(1);

    Mail::to('c.perroni@ifortech.com')->send(new StatusUpdateMail($ticket, 1, $user));
});

Route::get('/test', function () {
    return "test";
});

Route::get('/welcome', function () {
    return "welcome";
});

Route::get('/factory', function () {
    return "factory";
});

require __DIR__ . '/auth.php';
