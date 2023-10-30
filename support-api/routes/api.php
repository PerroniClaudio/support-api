<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketMessageController;
use App\Http\Controllers\TicketStatusUpdateController;
use App\Http\Controllers\CompanyController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum'])->get('/user-test', [App\Http\Controllers\UserController::class, "ticketTypes"]);


Route::middleware(['auth:sanctum'])->group( function() {
    Route::post(
        "/ticket/{ticket_id}/message", 
        [TicketMessageController::class, "store"]
    );

    Route::get(
        "/ticket/{ticket_id}/messages", 
        [TicketMessageController::class, "index"]
     );
});

Route::middleware(['auth:sanctum'])->group( function() {
    Route::post(
        "/ticket/{ticket_id}/status-updates", 
        [TicketStatusUpdateController::class, "store"]
    );

    Route::get(
        "/ticket/{ticket_id}/status-updates", 
        [TicketStatusUpdateController::class, "index"]
     );
});

Route::middleware(['auth:sanctum'])->group( function() {

    Route::get(
        "/companies/{company}/offices", 
        [CompanyController::class, "offices"]
    );

    Route::get(
        "/companies/{company}/admins", 
        [CompanyController::class, "admins"]
    );

});

Route::middleware(['auth:sanctum'])->group(function() {
    Route::resource('ticket', App\Http\Controllers\TicketController::class);
});

Route::middleware(['auth:sanctum'])->group(function() {
    Route::resource('attendance', App\Http\Controllers\AttendanceController::class);
});

Route::middleware(['auth:sanctum'])->get(
    "/ticket-types", 
    [App\Http\Controllers\UserController::class, "ticketTypes"]
);

Route::middleware(['auth:sanctum'])->get(
    "/ticket-type-webform/{ticketType}", 
    [App\Http\Controllers\TicketTypeController::class, "getWebForm"]
);

Route::post(
    "/upload-file", 
    [App\Http\Controllers\FileUploadController::class, "uploadFileToCloud"]
);