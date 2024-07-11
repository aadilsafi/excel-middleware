<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Webklex\IMAP\Facades\Client;

class EmailController extends Controller
{
    public function readEmails()
    {
        try {
            $client = Client::account('default'); // Connect to the default account
            $client->connect();

            $folder = $client->getFolder('INBOX');
            $messages = $folder->messages()->from('TrackingUpdates@fedex.com')->get();

            $emailData = [];

            foreach ($messages as $message) {
                $body = $message->getTextBody();

                // Use regex to find the purchase order number and tracking number
                preg_match('/Purchase order number\s*:\s*(\d+)/', $body, $purchaseOrderMatches);
                preg_match('/Tracking number\s*:\s*(\d+)/', $body, $trackingNumberMatches);

                $purchaseOrderNumber = $purchaseOrderMatches[1] ?? null;
                $trackingNumber = $trackingNumberMatches[1] ?? null;

                if ($purchaseOrderNumber && $trackingNumber) {
                    $emailData[] = [
                        'purchase_order_number' => $purchaseOrderNumber,
                        'tracking_number' => $trackingNumber,
                    ];
                }
            }

            // You can return the data as JSON, save to database, etc.
            return response()->json($emailData);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
