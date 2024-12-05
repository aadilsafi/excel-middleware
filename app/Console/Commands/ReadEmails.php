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
            $sellerCloudService = new \App\Services\SellerCloudService();

            foreach ($messages as $message) {
                $body = $message->getTextBody();

                // Use regex to find the purchase order number and tracking number
                preg_match('/Purchase order number\s*:\s*(\d+)/', $body, $purchaseOrderMatches);
                preg_match('/Tracking number\s*:\s*(\d+)/', $body, $trackingNumberMatches);
                preg_match('/Service type\s*:\s*([^\r\n]+)/', $body, $serviceTypeMatches);

                $ship_date = Carbon::now()->format('Y-m-d\TH:i:s.v\Z');

                $orderId = $purchaseOrderMatches[1] ?? null;
                $trackingNumber = $trackingNumberMatches[1] ?? null;
                $serviceType = $serviceTypeMatches[1] ?? null;

                if ($orderId && $trackingNumber) {
                    $emailData[] = [
                        'order_id' => $orderId,
                        'tracking_number' => $trackingNumber,
                    ];
                    Log::info('processing emails for order id: ' . $orderId . ' and tracking number: ' . $trackingNumber . ' at ' . $ship_date);
                    $res = $sellerCloudService->updateShipping($orderId, $ship_date, $trackingNumber,'FedEx', $serviceType);
                    if(!$res){
                        Log::error('Failed to update order id: ' . $orderId . ' and tracking number: ' . $trackingNumber. ' at ' . $ship_date);
                        $message->setFlag(['Seen']);
                        continue;
                    }
                } else if(!$orderId) {
                    preg_match('/Shipper Information\s*([\s\S]*?)Recipient Information\s*([\s\S]*?)Please do not respond/', $body, $infoMatches);

                    $shipperAndRecipientLines = isset($infoMatches[1]) ? $infoMatches[1] . "\n" . $infoMatches[2] : '';

                    // Splitting the combined shipper and recipient lines based on new lines
                    $lines = explode("\n", $shipperAndRecipientLines);

                    // Initializing arrays for shipper and recipient information
                    $shipperInfo = [];
                    $recipientInfo = [];

                    // Parsing each line to extract shipper and recipient columns
                    foreach ($lines as $line) {
                        // Splitting each line based on a consistent number of spaces or tab that separates shipper and recipient columns
                        $columns = preg_split('/\s{2,}/', trim($line));

                        if (count($columns) == 2) {
                            $shipperInfo[] = trim($columns[0]);
                            $recipientInfo[] = trim($columns[1]);
                        }
                    }

                    // Assigning the parsed values to individual variables
                    $shipperName = $shipperInfo[0] ?? 'Not found';
                    $shipperStreet = $shipperInfo[1] ?? 'Not found';
                    $shipperCity = $shipperInfo[2] ?? 'Not found';
                    $shipperState = $shipperInfo[3] ?? 'Not found';
                    $shipperCountry = $shipperInfo[4] ?? 'Not found';
                    $shipperPostal = $shipperInfo[5] ?? 'Not found';

                    $recipientName = $recipientInfo[0] ?? 'Not found';
                    $recipientStreet = $recipientInfo[1] ?? 'Not found';
                    $recipientCity = $recipientInfo[2] ?? 'Not found';
                    $recipientState = $recipientInfo[3] ?? 'Not found';
                    $recipientCountry = $recipientInfo[4] ?? 'Not found';
                    $recipientPostal = $recipientInfo[5] ?? 'Not found';

                    $error_message = 'Missing PO Number For '. "\n\n\n\n" ."Tracking Number: ". ($trackingNumber ?? 'Not Found')."\n" . "Service Type: ". ($serviceType ?? 'Not Found') . "\n\n\n\n" . 'Shipper Information' . "\n" . 'Name: ' . $shipperName . "\n" . 'Street: ' . $shipperStreet . "\n" . 'City: ' . $shipperCity . "\n" . 'State: ' . $shipperState . "\n" . 'Country: ' . $shipperCountry . "\n" . 'Postal: ' . $shipperPostal . "\n\n\n\n" . 'Recipient Information' . "\n" . 'Name: ' . $recipientName . "\n" . 'Street: ' . $recipientStreet . "\n" . 'City: ' . $recipientCity . "\n" . 'State: ' . $recipientState . "\n" . 'Country: ' . $recipientCountry . "\n" . 'Postal: ' . $recipientPostal;
                    $sellerCloudService->sendEmail(null, [
                        'body' => $error_message,
                        'title' => 'Missing PO Number',
                        'heading' => 'Missing PO Number',
                    ]);
                    // log the tracking number, service type, shipper information and recipent information
                    Log::error($error_message . ' at ' . $ship_date);
                }

                $message->setFlag(['Seen']);
            }


            // You can return the data as JSON, save to database, etc.

        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }
}
