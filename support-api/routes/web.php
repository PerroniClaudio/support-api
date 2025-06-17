<?php

use App\Mail\OtpEmail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;


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
    return [
        'app_name' => config('app.name'),
        'Laravel' => app()->version(),
        'timezone' => config('app.timezone'),
        'current_time' => now()->toDateTimeString(),
        'environment' => config('app.env')
    ];
});


Route::get('/info', function () {
    phpinfo();
});

Route::get('/testmail', function () {
    Mail::to('c.perroni@ifortech.com')->send(new OtpEmail(2345));
});

Route::get('/test', function () {
    $hardwareId = request()->query('hw');
    $hardware = App\Models\Hardware::find($hardwareId);
    $brand = $hardware->company->brands()->first();
    $google_url = $brand->withGUrl()->logo_url;

    echo 'hardwareId: ' . $hardwareId . '<br>';
    echo 'marca: ' . $brand->make . '<br>';
    echo 'modello: ' . $brand->model . '<br>';
    echo $google_url . '<br>';
    echo '<img src="' . $google_url . '" alt="Brand Logo"><br>';
    return "test";
});


Route::get('/welcome', function () {
    return "welcome";
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


Route::get('/import', [App\Http\Controllers\OldTicketController::class, 'import'])->name('import');

require __DIR__ . '/auth.php';
require __DIR__ . '/webhook.php';

Route::middleware(['throttle:5,1', 'auth:sanctum'])->group(function () {
    Route::post('/two-factor-authentication-challenge', [App\Http\Controllers\UserController::class, 'twoFactorChallenge'])->name('two-factor-challenge-user');
});
