<?php

use App\Models\Order;
use App\Models\Product;
use App\Mail\FilesReport;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('test1', function () {

    dd(str_pad(1, 4, '0', STR_PAD_LEFT));
    // Define the local path
    $localPath = 'local_uploads/test_file.txt';

    // Check if the local file exists
    if (!Storage::disk('local')->exists($localPath)) {
        return response()->json(['error' => 'Local file does not exist.'], 404);
    }

    // Get the file content
    $fileContent = Storage::disk('local')->get($localPath);

    // Define the FTP path
    $ftpPath = 'eo/incoming/test_file.txt';

    // Upload the file to FTP
    Storage::disk('rsr')->put($ftpPath, $fileContent);

    return response()->json(['message' => 'File has been copied to the FTP server successfully.']);
});
Route::post('test', function (Request $request) {
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
        return response()->json(['error' => 'Vendor is not RSR.'], 404);
    }
    Log::info(json_encode($request->all()));
    Log::info('Shipping : ' . json_encode($request->ShippingAddress, true));
    Log::info('items : ' . json_encode($items));
    $ShippingAddress = $request->ShippingAddress;
    $date = date('Ymd');
    // quantity with the leading zeros if needed
    $quantity = str_pad(1, 5, '0', STR_PAD_LEFT);
    $order_id = $request->id;
    $source_id  = $order_id; //$request->OrderSourceOrderID;
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
            Mail::to('test@test.com')->send(new FilesReport(null, ['heading' => 'Vendor Sku not Found', 'body' => 'this Vendor was not on our database ', 'title' => 'Vendor Sku not found']));
            return;
        }
        $quantity = str_pad($item['Qty'], 5, '0', STR_PAD_LEFT);
        $content .= "$source_id;20;$vendorSKU;$quantity;FedEx;Grnd\n";
        $total_quantity += $item['Qty'];
    }
    $total_quantity = str_pad($total_quantity, 5, '0', STR_PAD_LEFT);

    $content .= "$source_id;90;$total_quantity\nFILETRAILER;99;00001";

    // Specify the file path and name
    $filePath = 'public/EORD-46530-' . $date . '-' . $newSequence . '.txt';
    // Store the content to a file
    Storage::put($filePath, $content);

    // Get the full path of the local file
    // Define the FTP path

    // $ftpPath = 'eo/incoming/EORD-46530-'.$date.'-'.$newSequence.'.txt';
    // Storage::disk('rsr')->put($ftpPath, $content);



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
        Order::updateOrCreate(
            [
                'order_id' => $order_id,
                'order_source_id' => $source_id,
            ],
            [
                'order_id' => $order_id,
                'order_source_id' => $source_id,
            ]
        );
        Log::info('file uploaded to FTP Order');
    } else {
        Log::info('file not uploaded to FTP Order');
    }

    // Log the paths for debugging
    // Log::info("FTP File Path: $ftpPath");

    // Try to upload the file to the FTP server
    // $success = Storage::disk('rsr')->put($ftpPath, fopen($localFileFullPath, 'r'));


});
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
