<?php

namespace App\Http\Controllers;

use App\Jobs\StoreDgveryLive;
use App\Jobs\StoreDgveryTrackingError;
use Illuminate\Http\Request;

class WebhookController extends Controller {
    public function handle(Request $request) {
        // Process webhook payload

        switch ($request->header('X-Support-Webhook-Event')) {
            case 'ticket.new_live_academelearning':
                // Handle ticket created event
                dispatch(new StoreDgveryLive($request->all()));

                break;
            case 'ticket.new_tracking_error_academelearning':
                // Handle ticket created event
                dispatch(new StoreDgveryTrackingError($request->all()));

                break;
            default:
                // Handle other events
                $message = "No...";
                break;
        }


        // Perform actions based on the webhook data

        return response()->json(['success' => true]);
    }
}
