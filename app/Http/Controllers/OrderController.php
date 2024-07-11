<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
class OrderController extends Controller
{
    public function newOrder (Request $request)
    {
        Log::info('Webhook  Order id => '.$request->id);

        $sellerCloudService = new \App\Services\SellerCloudService();
        $items = $request->Items;
        // Convert single quotes to double quotes
        $items = str_replace("'", "\"", $items);
        $items = preg_replace('/"ProductName":\s*""(.*?)"",/', '"ProductName": "\1",', $items);

        // Step 2: Fix improper XML string handling in "ExtraInformation"
        $items = preg_replace('/"ExtraInformation":\s*".*?",/', '', $items);

        // Step 3: Replace None with null
        $items = str_replace('None', 'null', $items);

        // Step 4: Replace False with false (for boolean values)
        $items = str_replace('False', 'false', $items);
        $items = preg_replace('/(?<=[a-zA-Z])"(?=[a-zA-Z])/', "'", $items);

        $items = json_decode($items, true);
        if (!isset($items[0]) || (isset($items[0])  && !Str::contains($items[0]['DefaultVendorName'], 'RSR'))) {
            Log::info('Venor is not RSR');
            $sellerCloudService->sendEmail(null,[
                'body' => 'This Order is not from RSR Vendor Order ID is => '.$request->id,
                'title' => 'Not RSR Order',
                'heading' => 'Not RSR Order',
            ]);
            return response()->json([], 200);
        }
        Log::info(json_encode($request->all()));
        Log::info('Shipping : ' . json_encode($request->ShippingAddress, true));
        Log::info('items : ' . json_encode($items));
        $ShippingAddress = $request->ShippingAddress;
        $date = date('Ymd');
        // quantity with the leading zeros if needed
        $quantity = str_pad(1, 5, '0', STR_PAD_LEFT);
        $source_id  = $request->id; //$request->OrderSourceOrderID;
        $FirstName = $ShippingAddress['FirstName'];
        $MiddleInitial = $ShippingAddress['MiddleInitial'];
        $LastName = $ShippingAddress['LastName'];
        $StreetLine1 = $ShippingAddress['StreetLine1'];
        $StreetLine2 = $ShippingAddress['StreetLine2'];
        $City = $ShippingAddress['City'];
        $StateName = $ShippingAddress['StateName'];
        $PostalCode = $ShippingAddress['PostalCode'];
        $PhoneNumber = $ShippingAddress['PhoneNumber'];
        $PhoneNumber = substr($PhoneNumber, 0, 10);
        $combinedString = $FirstName . ' ' . $MiddleInitial . ' ' . $LastName;


            // Check if the combined string contains "PO"
            if (Str::contains($combinedString, 'PO')) {
                // Find the position of "PO"
                $poPosition = strpos($combinedString, 'PO');

                // Get the part before "PO" or up to the first 25 characters, whichever is shorter
                $FirstName = substr($combinedString, 0, min(25, $poPosition));

                // Get the rest of the string starting from "PO"
                $remainingString = substr($combinedString, $poPosition);

                // Ensure LastName is no more than 25 characters
                $LastName = substr($remainingString, 0, 25);
            } else {
                // If "PO" is not found, handle accordingly
                $FirstName = substr($combinedString, 0, 25);
                $LastName = '';
            }
        Log::info($FirstName);
        Log::info($LastName);
        $total_quantity = 0;

        Log::info($PhoneNumber);
        $sequence = DB::table('order_sequences')->lockForUpdate()->first();

        $newSequence = $sequence->current_sequence + 1;

        DB::table('order_sequences')->update(['current_sequence' => $newSequence]);

        $newSequence = str_pad($newSequence, 4, '0', STR_PAD_LEFT);

        $content = "FILEHEADER;00;46530;$date;$newSequence\n$source_id;10;$FirstName;$LastName;$StreetLine1;$StreetLine2;$City;$StateName;$PostalCode;$PhoneNumber;Y;Info@thesuppliesnmore.com;;\n";
        foreach ($items as $item) {
            Log::info($item['ProductID']);
            $vendorSKU = Product::where('ProductSKU', $item['ProductID'])->first()?->VendorSKU;
            if (!$vendorSKU) {
                $sellerCloudService->sendEmail(null,['heading' => 'Vendor Sku not Found', 'body' => 'this Vendor was not on our database Order ID is => '.$request->id, 'title' => 'Vendor Sku not found']);
                return;
            }
            $quantity = str_pad($item['Qty'], 5, '0', STR_PAD_LEFT);
            $content .= "$source_id;20;$vendorSKU;$quantity;FedEx;Grnd\n";
            $total_quantity += $item['Qty'];
        }
        Log::info($content);
        // return;
        $total_quantity = str_pad($total_quantity, 5, '0', STR_PAD_LEFT);

        $content .= "$source_id;90;$total_quantity\nFILETRAILER;99;00001";

        // Specify the file path and name
        $filePath = 'public/EORD-46530-' . $date . '-' . $newSequence . '.txt';
        // Store the content to a file
        Storage::put($filePath, $content);

        if (!Storage::disk('local')->exists($filePath)) {
            Log::info('file not found');
            return response()->json(['error' => 'Local file does not exist.'], 404);
        }

        // Get the file content
        $fileContent = Storage::disk('local')->get($filePath);

        // Define the FTP path
        $ftpPath = 'eo/incoming/EORD-46530-' . $date . '-' . $newSequence . '.txt';

        // Upload the file to FTP
        // check if the file is uploaded
        $ftp_file = Storage::disk('rsr')->put($ftpPath, $fileContent);
        if ($ftp_file) {
            Log::info('file uploaded to FTP Order');
        } else {
            Log::info('file not uploaded to FTP Order');
        }

    }
}
