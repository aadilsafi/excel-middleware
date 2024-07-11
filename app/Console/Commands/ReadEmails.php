<?php

namespace App\Console\Commands;

use App\Services\SellerCloudService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Facades\Client;

class ReadEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'read-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Read Emails and mark them as read';

    /**
     * Execute the console command.
     */
    public function handle(SellerCloudService $sellerCloudService)
    {
        try {
            $client = Client::account('default'); // Connect to the default account
            $client->connect();

            $folder = $client->getFolder('INBOX');
            $messages = $folder->messages()->from('TrackingUpdates@fedex.com')->unseen()->get();

            $emailData = [];

            foreach ($messages as $message) {
                $body = $message->getTextBody();

                // Use regex to find the purchase order number and tracking number
                preg_match('/Purchase order number\s*:\s*(\d+)/', $body, $purchaseOrderMatches);
                preg_match('/Tracking number\s*:\s*(\d+)/', $body, $trackingNumberMatches);
                $ship_date = Carbon::now()->format('Y-m-d\TH:i:s.v\Z');

                $orderId = $purchaseOrderMatches[1] ?? null;
                $trackingNumber = $trackingNumberMatches[1] ?? null;

                if ($orderId && $trackingNumber) {
                    $emailData[] = [
                        'order_id' => $orderId,
                        'tracking_number' => $trackingNumber,
                    ];
                    Log::info('processing emails for order id: ' . $orderId . ' and tracking number: ' . $trackingNumber);
                    $sellerCloudService->updateShipping($orderId, $ship_date, $trackingNumber);
                }

                $message->setFlag(['Seen']);
            }


            // You can return the data as JSON, save to database, etc.

        } catch (\Exception $e) {
            Log::error($e->getMessage());}
    }
}
