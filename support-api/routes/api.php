<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketMessageController;
use App\Http\Controllers\TicketStatusUpdateController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\GroupController;

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

Route::middleware(['auth:sanctum'])->group( function() {

    Route::get(
        '/user', function (Request $request) {
        return $request->user();
    });
    
    Route::get(
        '/user/alladmins-ids', 
        [App\Http\Controllers\UserController::class, "adminsIds"]
    );



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
        "/companies", 
        [CompanyController::class, "index"]
    );

    Route::get(
        "/companies/{company}/offices", 
        [CompanyController::class, "offices"]
    );

    Route::get(
        "/companies/{company}/admins", 
        [CompanyController::class, "admins"]
    );
    
    Route::get(
        "/companies/{company}/allusers", 
        [CompanyController::class, "allusers"]
    );
    
    Route::get(
        "/companies/{company}/ticket-types", 
        [CompanyController::class, "ticketTypes"]
    );

});

Route::middleware(['auth:sanctum'])->group(function() {
    Route::resource('ticket', App\Http\Controllers\TicketController::class);
    Route::get(
        "/ticket/{ticket}/files", 
        [App\Http\Controllers\TicketController::class, "files"]
    );

    Route::post(
        "/ticket/{ticket}/file", 
        [App\Http\Controllers\TicketController::class, "storeFile"]
    );
    
    Route::post(
        "/ticket/{ticket}/status-update", 
        [App\Http\Controllers\TicketController::class, "updateStatus"]
    );
    
    Route::post(
        "/ticket/{ticket}/add-note", 
        [App\Http\Controllers\TicketController::class, "addNote"]
    );
    
    Route::post(
        "/ticket/{ticket}/close", 
        [App\Http\Controllers\TicketController::class, "closeTicket"]
    );

    Route::get(
        "/ticket/file/{id}/temporary_url",
        [App\Http\Controllers\TicketController::class, "generatedSignedUrlForFile"]
    );
    
    Route::get(
        "/ticket/admin",
        [App\Http\Controllers\TicketController::class, "adminGroupsTickets"]
    );

});

Route::middleware(['auth:sanctum'])->group(function() {
    Route::resource('attendance', App\Http\Controllers\AttendanceController::class);
    Route::get('presenze-type', [App\Http\Controllers\AttendanceController::class, "types"]);
});

Route::middleware(['auth:sanctum'])->group(function() {
    Route::post('time-off-request/batch', [App\Http\Controllers\TimeOffRequestController::class, "storeBatch"]);
    Route::patch('time-off-request/batch', [App\Http\Controllers\TimeOffRequestController::class, "updateBatch"]);

    Route::resource('time-off-request', App\Http\Controllers\TimeOffRequestController::class);
    Route::get('time-off-type', [App\Http\Controllers\TimeOffRequestController::class, "types"]);
});

Route::middleware(['auth:sanctum'])->group(function() {
    Route::resource('business-trip', App\Http\Controllers\BusinessTripController::class);
    Route::get('business-trip/{business_trip}/expense', [App\Http\Controllers\BusinessTripController::class, "getExpenses"]);
    Route::post('business-trip/{business_trip}/expense', [App\Http\Controllers\BusinessTripController::class, "storeExpense"]);
    Route::get('business-trip/{business_trip}/transfer', [App\Http\Controllers\BusinessTripController::class, "getTransfers"]);
    Route::post('business-trip/{business_trip}/transfer', [App\Http\Controllers\BusinessTripController::class, "storeTransfer"]);
});

Route::middleware(['auth:sanctum'])->get(
    "/ticket-types", 
    [App\Http\Controllers\UserController::class, "ticketTypes"]
);

Route::middleware(['auth:sanctum'])->group( function() {


    
    Route::get(
        "/ticket-type-groups/{ticketType}", 
        [App\Http\Controllers\TicketTypeController::class, "getGroups"]
    );

    Route::get(
        "/ticket-type-webform/{ticketType}", 
        [App\Http\Controllers\TicketTypeController::class, "getWebForm"]
    );

});

// Route::middleware(['auth:sanctum'])->get(
//     "/ticket-type-webform/{ticketType}", 
//     [App\Http\Controllers\TicketTypeController::class, "getWebForm"]
// );

Route::post(
    "/upload-file", 
    [App\Http\Controllers\FileUploadController::class, "uploadFileToCloud"]
);

Route::middleware(['auth:sanctum'])->group( function() {

    Route::get(
        "/groups", 
        [GroupController::class, "index"]
    );
    
    Route::get(
        "/groups/{group}/ticket-types", 
        [GroupController::class, "ticketTypes"]
    );

});