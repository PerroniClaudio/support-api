<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketMessageController;

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

Route::middleware(['auth:sanctum'])->group( function() {
    Route::get(
       "/ticket/{ticket_id}/messages", 
       [TicketMessageController::class, "index"]
    );
});

Route::middleware(['auth:sanctum'])->group(function() {
    Route::resource('ticket', App\Http\Controllers\TicketController::class);
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