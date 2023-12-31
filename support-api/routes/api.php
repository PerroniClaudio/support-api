<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get(
        '/user',
        function (Request $request) {
            return $request->user();
        }
    );

    Route::get(
        '/user/all',
        [App\Http\Controllers\UserController::class, "allUsers"]
    );

    Route::post(
        '/user',
        [App\Http\Controllers\UserController::class, "store"]
    );

    Route::patch(
        '/user',
        [App\Http\Controllers\UserController::class, "update"]
    );

    Route::delete(
        '/user/{id}',
        [App\Http\Controllers\UserController::class, "destroy"]
    );

    Route::get(
        '/user/alladmins-ids',
        [App\Http\Controllers\UserController::class, "adminsIds"]
    );

    Route::get(
        '/user/alladmins',
        [App\Http\Controllers\UserController::class, "allAdmins"]
    );
});


Route::middleware(['auth:sanctum'])->get('/user-test', [App\Http\Controllers\UserController::class, "ticketTypes"]);


Route::middleware(['auth:sanctum'])->group(function () {
    Route::post(
        "/ticket/{ticket_id}/message",
        [TicketMessageController::class, "store"]
    );

    Route::get(
        "/ticket/{ticket_id}/messages",
        [TicketMessageController::class, "index"]
    );
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post(
        "/ticket/{ticket_id}/status-updates",
        [TicketStatusUpdateController::class, "store"]
    );

    Route::get(
        "/ticket/{ticket_id}/status-updates",
        [TicketStatusUpdateController::class, "index"]
    );
});

Route::middleware(['auth:sanctum'])->group(function () {
    // Route::resource('companies', App\Http\Controllers\CompanyController::class);
    Route::get(
        "/companies",
        [CompanyController::class, "index"]
    );

    Route::get(
        "/companies/{id}",
        [CompanyController::class, "show"]
    );

    Route::post(
        "/companies",
        [CompanyController::class, "store"]
    );

    Route::delete(
        "/companies/{id}",
        [CompanyController::class, "destroy"]
    );

    Route::patch(
        "/companies",
        [CompanyController::class, "update"]
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

Route::middleware(['auth:sanctum'])->group(function () {
    Route::resource('offices', App\Http\Controllers\OfficeController::class);
    // Route::get(
    //     "/offices/{company}/all-offices",
    //     [App\Http\Controllers\OfficeController::class, "companyOffices"]
    // );
});

Route::middleware(['auth:sanctum'])->group(function () {
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
        "/ticket/{ticket}/priority-update",
        [App\Http\Controllers\TicketController::class, "updateTicketPriority"]
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
        "/ticket-admin",
        [App\Http\Controllers\TicketController::class, "adminGroupsTickets"]
    );

    Route::post(
        "/ticket/{ticket}/assign-to-admin",
        [App\Http\Controllers\TicketController::class, "assignToAdminUser"]
    );
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::resource('attendance', App\Http\Controllers\AttendanceController::class);
    Route::get('presenze-type', [App\Http\Controllers\AttendanceController::class, "types"]);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('time-off-request/batch', [App\Http\Controllers\TimeOffRequestController::class, "storeBatch"]);
    Route::patch('time-off-request/batch', [App\Http\Controllers\TimeOffRequestController::class, "updateBatch"]);

    Route::resource('time-off-request', App\Http\Controllers\TimeOffRequestController::class);
    Route::get('time-off-type', [App\Http\Controllers\TimeOffRequestController::class, "types"]);
});

Route::middleware(['auth:sanctum'])->group(function () {
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

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get(
        "/ticket-type/all",
        [App\Http\Controllers\TicketTypeController::class, "index"]
    );

    Route::get(
        "/ticket-type/{ticketType}",
        [App\Http\Controllers\TicketTypeController::class, "show"]
    );



    Route::get(
        "/ticket-type-categories",
        [App\Http\Controllers\TicketTypeController::class, "categories"]
    );

    Route::patch(
        "/ticket-type-category/{ticketTypeCategory}",
        [App\Http\Controllers\TicketTypeController::class, "updateCategory"]
    );


    Route::get(
        "/ticket-type-groups/{ticketType}",
        [App\Http\Controllers\TicketTypeController::class, "getGroups"]
    );

    Route::get(
        "/ticket-type-webform/{ticketType}",
        [App\Http\Controllers\TicketTypeController::class, "getWebForm"]
    );

    // Route::get(
    //     "/ticket-type-companies/{ticketType}",
    //     [App\Http\Controllers\TicketTypeController::class, "getCompanies"]
    // );

    Route::get(
        "/ticket-type-company/{ticketType}",
        [App\Http\Controllers\TicketTypeController::class, "getCompany"]
    );

    // Route::patch(
    //     "/ticket-type/update-sla",
    //     [App\Http\Controllers\TicketTypeController::class, "updateSla"]
    // );

    // Restituisce il numero di ticket del tipo indicato per l'azienda assegnata al tipo
    Route::get(
        "/ticket-type/{ticketTypeId}/count-company",
        [App\Http\Controllers\TicketTypeController::class, "countTicketsInCompany"]
    );

    Route::patch(
        "/ticket-type/{ticketType}",
        [App\Http\Controllers\TicketTypeController::class, "update"]
    );

    Route::post(
        "/ticket-type",
        [App\Http\Controllers\TicketTypeController::class, "store"]
    );

    Route::post(
        "/ticket-type-category",
        [App\Http\Controllers\TicketTypeController::class, "storeCategory"]
    );

    Route::post(
        "/ticket-type-webform",
        [App\Http\Controllers\TicketTypeController::class, "createFormField"]
    );

    Route::post(
        "/ticket-type-groups",
        [App\Http\Controllers\TicketTypeController::class, "updateGroups"]
    );

    Route::post(
        "/ticket-type-groups/delete",
        [App\Http\Controllers\TicketTypeController::class, "deleteGroups"]
    );

    Route::post(
        "/ticket-type-companies",
        [App\Http\Controllers\TicketTypeController::class, "updateCompanies"]
    );

    // Route::post(
    //     "/ticket-type-companies/delete",
    //     [App\Http\Controllers\TicketTypeController::class, "deleteCompany"]
    // );

    Route::post(
        "/ticket-type/duplicate",
        [App\Http\Controllers\TicketTypeController::class, "duplicateTicketType"]
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

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get(
        "/groups",
        [GroupController::class, "index"]
    );

    Route::get(
        "/groups/{group}",
        [GroupController::class, "show"]
    );

    Route::get(
        "/groups/{group}/ticket-types",
        [GroupController::class, "ticketTypes"]
    );

    Route::get(
        "/groups/{group}/users",
        [GroupController::class, "users"]
    );

    Route::post(
        "/groups",
        [GroupController::class, "store"]
    );

    Route::post(
        "/groups-users",
        [GroupController::class, "updateUsers"]
    );
    Route::post(
        "/groups-types",
        [GroupController::class, "updateTypes"]
    );
});
