<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    public function newOrder(Request $request)
    {
        Log::info('Webhook  Order id => ' . $request->id);

        $sellerCloudService = new \App\Services\SellerCloudService();
        $items = $request->Items;
        // Convert single quotes to double quotes
        $items = str_replace("'", "\"", $items);
        $items = preg_replace('/"ProductName":\s*""(.*?)"",/', '"ProductName": "\1",', $items);

        Log::info('items 1st : ' . json_encode($items));

        $items = preg_replace('/"ProductName":\s*"([^"]*)""([^"]*)"",/', '"ProductName": "\1\"\2",', $items);

        Log::info('items 1st.2 : '.\json_encode($items));

        // Step 2: Fix improper XML string handling in "ExtraInformation"
        $items = preg_replace('/"ExtraInformation":\s*".*?",/', '', $items);

        Log::info('items 2nd : ' . json_encode($items));

        // Step 3: Replace None with null
        $items = str_replace('None', 'null', $items);

        Log::info('items 3rd : ' . json_encode($items));

        // Step 4: Replace False with false (for boolean values)
        $items = str_replace('False', 'false', $items);

        Log::info('items 4th : ' . json_encode($items));

        $items = preg_replace('/(?<=[a-zA-Z])"(?=[a-zA-Z])/', "'", $items);

        Log::info('items 5th : ' . json_encode($items));

        $items = json_decode($items, true);

        Log::info(json_encode($request->all()));
        Log::info('Shipping : ' . json_encode($request->ShippingAddress, true));
        Log::info('items : ' . json_encode($items));
        $ShippingAddress = $request->ShippingAddress;
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

        Log::info($PhoneNumber);

        if (isset($items[0])) {

            if (Str::contains($items[0]['DefaultVendorName'], 'SeawideB2B')) {
                Log::info('Vendor is Seawide');
                $this->seawideOrder($request, $items, $source_id, $FirstName, $LastName, $StreetLine1, $StreetLine2, $City, $StateName, $PostalCode, $PhoneNumber);
            } elseif (Str::contains($items[0]['DefaultVendorName'], 'RSR')) {
                Log::info('Vendor is RSR');
                $this->rsrOrder($request, $items, $source_id, $FirstName, $LastName, $StreetLine1, $StreetLine2, $City, $StateName, $PostalCode, $PhoneNumber);
            } else {
                Log::info('Vendor is not RSR and Seawide');
                $sellerCloudService->sendEmail(null, [
                    'body' => 'This Order is not from RSR and Seawide Vendor Order ID is => ' . $request->id,
                    'title' => 'Not RSR and Seawide Order',
                    'heading' => 'Not RSR and Seawide Order',
                ]);
            }
            return response()->json([], 200);
        }
        else{
            Log::info('items[0] not set ');
        }
        Log::info('Vendor is not RSR and Seawide');
        $sellerCloudService->sendEmail(null, [
            'body' => 'This Order is not from RSR and Seawide Vendor Order ID is => ' . $request->id,
            'title' => 'Not RSR and Seawide Order',
            'heading' => 'Not RSR and Seawide Order',
        ]);
        return response()->json([], 200);
    }

    public function seawideOrder($request, $items, $source_id, $FirstName, $LastName, $StreetLine1, $StreetLine2, $City, $StateName, $PostalCode, $PhoneNumber)
    {
        $total_quantity= 0;
        $sellerCloudService = new \App\Services\SellerCloudService();
        $seawideService = new \App\Services\SeawideService();
        $FullPartNo = null; //vendor sku from db
        foreach ($items as $item) {
            Log::info($item['ProductID']);
            $FullPartNo = Product::where('ProductSKU', $item['ProductID'])->where('VendorId',15080)->first()?->VendorSKU;
            if (!$FullPartNo) {
                $sellerCloudService->sendEmail(null, ['heading' => 'Vendor Sku not Found', 'body' => 'this Vendor was not on our database Order ID is => ' . $request->id, 'title' => 'Vendor Sku not found']);
                return;
            }
            $total_quantity += $item['Qty'];
        }

        $source_id  = $request->id;
        Order::updateOrCreate([
            'order_source_id' => $FullPartNo,
            'vendor_id' => 15080,
            'order_id' => $source_id,
            // 'zipcode' => $
        ],[
            'order_source_id' => $FullPartNo,
            'vendor_id' => 15080,
            'order_id' => $source_id,
        ]);

        $Quant  = $total_quantity;
        $DropShipFirstName = $FirstName;
        $DropShipLastName = $LastName;
        $DropShipCompany = 'The Supplies N More';
        $DropShipAddress1 = $StreetLine1;
        $DropShipAddress2 = $StreetLine2;
        $DropShipCity = $City;
        $DropShipState = $StateName;
        $DropShipPostalCode = $PostalCode;
        $DropShipPhone = $PhoneNumber;
        $PONumber = $source_id;
        $AdditionalInfo = '';
        $data = $seawideService->ShipOrderDropShip(
            $FullPartNo,
            $Quant,
            $DropShipFirstName,
            $DropShipLastName,
            $DropShipCompany,
            $DropShipAddress1,
            $DropShipAddress2,
            $DropShipCity,
            $DropShipState,
            $DropShipPostalCode,
            $DropShipPhone,
            $PONumber,
            $AdditionalInfo,
        );
    }

    public function rsrOrder($request, $items, $source_id, $FirstName, $LastName, $StreetLine1, $StreetLine2, $City, $StateName, $PostalCode, $PhoneNumber)
    {
        $sellerCloudService = new \App\Services\SellerCloudService();
        $date = date('Ymd');
        // quantity with the leading zeros if needed
        $quantity = str_pad(1, 5, '0', STR_PAD_LEFT);
        $total_quantity = 0;
        $sequence = DB::table('order_sequences')->lockForUpdate()->first();

        $newSequence = $sequence->current_sequence + 1;

        DB::table('order_sequences')->update(['current_sequence' => $newSequence]);

        $newSequence = str_pad($newSequence, 4, '0', STR_PAD_LEFT);

        $content = "FILEHEADER;00;46530;$date;$newSequence\n$source_id;10;$FirstName;$LastName;$StreetLine1;$StreetLine2;$City;$StateName;$PostalCode;$PhoneNumber;Y;Info@thesuppliesnmore.com;;\n";
        foreach ($items as $item) {
            Log::info($item['ProductID']);
            $vendorSKU = Product::where('ProductSKU', $item['ProductID'])->where('VendorId',15073)->first()?->VendorSKU;
            if (!$vendorSKU) {
                $sellerCloudService->sendEmail(null, ['heading' => 'Vendor Sku not Found', 'body' => 'this Vendor was not on our database Order ID is => ' . $request->id, 'title' => 'Vendor Sku not found']);
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
