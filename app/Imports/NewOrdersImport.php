<?php

namespace App\Imports;

use App\Models\Order;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class NewOrdersImport implements ToCollection
{
    /**
     * @param Collection $collection
     */
    public function collection(Collection $collection)
    {
        $sellerCloudService = new \App\Services\SellerCloudService();

        $orders = collect();
        Log::info('Processing new orders from excel');
        foreach ($collection as $key => $row) {
            if ($key == 0) {
                continue;
            }
            $orderId = $row[0]; // Assuming the first column is Order ID
            if (!$orders->has($orderId)) {
                $orders->put($orderId, collect([
                    'order_id' => $row[0],
                    'order_channel' => $row[1],
                    'channel_id' => $row[2],
                    'total_qty' => $row[3],
                    'vendor_name' => $row[5],
                    'full_shipping_name' => $row[7],
                    'street_line_1' => $row[8],
                    'street_line_2' => $row[9],
                    'city' => $row[10],
                    'state' => $row[11],
                    'postal_code' => $row[12],
                    'phone_number' => $row[13],
                    'items' => collect(),
                ]));
            }
            $shipping_method = 'Grnd';
            try{
                if(isset($row[17]) && $row[17] != '' ){
                    if($row[17] == 'Expedited'){
                        $shipping_method = '3Day';
                    }
                    if($row[17] == 'SecondDay'){
                        $shipping_method = '2Day';
                    }
                    if($row[17] == 'NextDay'){
                        $shipping_method = 'NDAS';
                    }
                }
            }
            catch(\Exception $e){
                Log::info('Error in getting shipping method');
                Log::info($e->getMessage());
            }
            $is_kit = $row[14] ?? false;
            $orders->get($orderId)->get('items')->push([
                'Qty' => $is_kit ? $row[15] : $row[4],
                'vendor_sku' => $is_kit ? $row[16] : $row[6],
                'shipping_method' => $shipping_method,
                'shipping_method_seawide' => $row[17],
                'shipping_override_seawide' => $row[18],
            ]);
        }

        foreach ($orders as $order) {
            Log::info('Order ID: ' . $order['order_id']);

            $source_id  = $order['order_id'];

            $StreetLine1 = $order['street_line_1'];
            $StreetLine2 = $order['street_line_2'];
            $City = $order['city'];
            $StateName = $order['state'];
            $PostalCode = $order['postal_code'];
            $items = $order['items'];
            $total_quantity = $order['total_qty'];
            $PhoneNumber = $order['phone_number'];
            $PhoneNumber = substr($PhoneNumber, 0, 10);
            $combinedString = $order['full_shipping_name'];


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


            if (Str::contains($order['vendor_name'], 'SeawideB2B')) {
                Log::info('Vendor is Seawide');
                $vendor_sku = $order['items'][0]['vendor_sku'];
                $res =  $this->seawideOrder($items, $source_id, $FirstName, $LastName, $StreetLine1, $StreetLine2, $City, $StateName, $PostalCode, $PhoneNumber, $vendor_sku, $total_quantity);
                if(is_string($res) && $res == 'No Shipping Option'){
                    continue;
                }
                if (!$res) {
                    Log::info('Vendor is  Seawide but items are not present');
                    $sellerCloudService->sendEmail(null, [
                        'body' => 'This Order is from Seawide but issue with items Vendor Order ID is => ' . $source_id,
                        'title' => 'Seawide Order but issue with items',
                        'heading' => 'Seawide Order but issue with items',
                    ]);
                }
            } elseif ($order['vendor_name'] == 'RSR') {
                Log::info('Vendor is RSR');
                $this->rsrOrder($items, $source_id, $FirstName, $LastName, $StreetLine1, $StreetLine2, $City, $StateName, $PostalCode, $PhoneNumber,'46530');
            } elseif ($order['vendor_name'] == 'RSR Dropship  67883') {
                Log::info('Vendor is RSR Dropship  67883');
                $this->rsrOrder($items, $source_id, $FirstName, $LastName, $StreetLine1, $StreetLine2, $City, $StateName, $PostalCode, $PhoneNumber,'67883');
            }
            elseif ($order['vendor_name'] == 'Kinseys Inc.') {
                Log::info('Vendor is Kinseys Inc.');
                $this->kinseyOrder($items,$source_id, $FirstName, $LastName, $StreetLine1, $StreetLine2, $City, $StateName, $PostalCode, $PhoneNumber);
            }
             else {
                Log::info('Vendor is not RSR and Seawide and Kinsey');
                $sellerCloudService->sendEmail(null, [
                    'body' => 'This Order is not from RSR and Seawide and Kinsey Vendor Order ID is => ' . $source_id,
                    'title' => 'Not RSR and Seawide and Kinsey Order',
                    'heading' => 'Not RSR and Seawide and Kinsey Order',
                ]);
            }
        }
    }
    public function seawideOrder($items, $source_id, $FirstName, $LastName, $StreetLine1, $StreetLine2, $City, $StateName, $PostalCode, $PhoneNumber, $FullPartNo, $total_quantity)
    {
        $seawideService = new \App\Services\SeawideService();

        Order::updateOrCreate([
            'order_source_id' => $FullPartNo,
            'vendor_id' => 15080,
            'order_id' => $source_id,
            // 'zipcode' => $
        ], [
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
        $partNumberQuantity = '';
        $partNumberQuantityShipping = '';
        foreach ($items as $item) {
            if (!empty($partNumberQuantity)) {
                $partNumberQuantity .= '|';
                $partNumberQuantityShipping .= '|';
            }
            $partNumberQuantity .= $item['vendor_sku'] . ',' . $item['Qty'];
            $partNumberQuantityShipping .= "K," . $item['vendor_sku'] . ',' . $item['Qty'];
        }

        if (!$partNumberQuantity) {
            Log::info('partNumber Not Found ' . $partNumberQuantity);
            return false;
        }
        $order = $items[0];
        $data = $seawideService->ShipOrderDropShipMultiparts(
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
            $partNumberQuantity,
            $partNumberQuantityShipping,
            $order
        );
        return $data;
    }
    public function rsrOrder($items, $source_id, $FirstName, $LastName, $StreetLine1, $StreetLine2, $City, $StateName, $PostalCode, $PhoneNumber,$store_id = '46530')
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
        if($store_id == '67883'){
            $content = "FILEHEADER;00;$store_id;$date;$newSequence\n$source_id;10;$FirstName;$LastName;$StreetLine1;$StreetLine2;$City;$StateName;$PostalCode;$PhoneNumber;Y;ahystradings@gmail.com;;\n";
        }else{
            $content = "FILEHEADER;00;$store_id;$date;$newSequence\n$source_id;10;$FirstName;$LastName;$StreetLine1;$StreetLine2;$City;$StateName;$PostalCode;$PhoneNumber;Y;Info@thesuppliesnmore.com;;\n";
        }

        $service_type = 'FedEx';
        $shipping_method = null;
        switch ($StateName) {
            case 'AA':
                $service_type = 'USPS';
                $shipping_method = 'PRIO';
                break;
            case 'AE':
                $service_type = 'USPS';
                $shipping_method = 'PRIO';
                break;
            case 'AP':
                $service_type = 'USPS';
                $shipping_method = 'PRIO';
                break;
        }

        foreach ($items as $item) {
            $vendorSKU = $item['vendor_sku'];
            Log::info($vendorSKU);
            $shipping_method = $shipping_method ?? ($item['shipping_method'] ?? 'Grnd');
            $quantity = str_pad($item['Qty'], 5, '0', STR_PAD_LEFT);
            $content .= "$source_id;20;$vendorSKU;$quantity;$service_type;$shipping_method\n";
            $total_quantity += $item['Qty'];
        }
        // return;
        $total_quantity = str_pad($total_quantity, 5, '0', STR_PAD_LEFT);

        $content .= "$source_id;90;$total_quantity\nFILETRAILER;99;00001";

        // Specify the file path and name
        $filePath = 'public/EORD-'.$store_id.'-' . $date . '-' . $newSequence . '.txt';
        // Store the content to a file
        Storage::put($filePath, $content);

        if (!Storage::disk('local')->exists($filePath)) {
            Log::info('file not found');
            $sellerCloudService->sendEmail(null, [
                'body' => "RSR ".$store_id == 67883 ? '(67883)' : ''." local file not found for upload to rsr ftp server for order id : " . $source_id,
                'title' => "RSR ".$store_id == 67883 ? '(67883)' : ''." local file not found for upload to rsr ftp Order Id = " . $source_id,
                'heading' => "RSR ".$store_id == 67883 ? '(67883)' : ''." local file not found for upload to rsr ftp Order Id = " . $source_id,
            ]);
            return response()->json(['error' => 'Local file does not exist.'], 404);
        }

        // Get the file content
        $fileContent = Storage::disk('local')->get($filePath);

        // Define the FTP path
        $ftpPath = 'eo/incoming/EORD-'.$store_id.'-' . $date . '-' . $newSequence . '.txt';

        // Upload the file to FTP
        // check if the file is uploaded
        if($store_id == '67883'){
            $ftp_file = Storage::disk('secondary_rsr')->put($ftpPath, $fileContent);
        }else{
            $ftp_file = Storage::disk('rsr')->put($ftpPath, $fileContent);
        }
        if ($ftp_file) {
            Log::info('file uploaded to FTP Order');
            try{
                Order::updateOrCreate( [
                    'order_source_id' => $source_id,
                    'vendor_id' => $store_id,
                    'order_id' => $source_id,
                ],[
                    'order_source_id' => $source_id,
                    'vendor_id' => $store_id,
                    'order_id' => $source_id,
                    'address' => $StreetLine1
                ]);
            }
            catch(Exception $exception){
                Log::info('Error in updating Order Model');
                Log::info($exception->getMessage());
            }
        } else {
            Log::info('file not uploaded to FTP Order');
            $sellerCloudService->sendEmail(null, [
                'body' => "RSR ".$store_id == 67883 ? '(67883)' : ''." failed to upload the compiled FTP file to the rsr ftp server for order id : " . $source_id,
                'title' => "RSR ".$store_id == 67883 ? '(67883)' : ''." File not Uploaded to FTP for Order Id = " . $source_id,
                'heading' => "RSR ".$store_id == 67883 ? '(67883)' : ''." File not Uploaded to FTP for Order Id = " . $source_id,
            ]);
        }
    }
    public function kinseyOrder($items,$source_id, $FirstName, $LastName, $StreetLine1, $StreetLine2, $City, $StateName, $ZipCode, $PhoneNumber)
    {
        $fullName = $FirstName . ' ' . $LastName;
        $salesLine = [];
        foreach ($items as $item) {
            $salesLine[] = [
                        "ProductID" => (string) $item['vendor_sku'],
                        "Quantity" => (string) $item['Qty']
            ];
        }

        $kinseyService = new \App\Services\KinseyService();
        Order::updateOrCreate([
            'order_source_id' => $source_id,
            'vendor_id' => 16344,
            'order_id' => $source_id,
        ], [
            'order_source_id' => $source_id,
            'vendor_id' => 16344,
            'order_id' => $source_id,
        ]);
        $response = $kinseyService->createSalesOrder($source_id,$salesLine,$fullName,$StreetLine1,$StreetLine2,$City,$StateName,$ZipCode,$PhoneNumber);
        if(!$response){
            Log::info('Failed to create sales order on Kinseys');
        }
        else{
            if (isset($response['salesOrders']) && is_array($response['salesOrders'])) {
                foreach ($response['salesOrders'] as $salesOrder) {
                    // Check if we have order lines
                    if (isset($salesOrder['orderLines']) && is_array($salesOrder['orderLines'])) {
                        foreach ($salesOrder['orderLines'] as $orderLine) {
                            // Check if status is not 7200
                            if (isset($orderLine['status']) && $orderLine['status'] !== 7200) {
                                // Log the information
                                Log::info(
                                    "Order issue detected",
                                    [
                                        'sales_order_no' => $salesOrder['salesOrderNo'] ?? 'Unknown',
                                        'product_id' => $orderLine['productID'] ?? 'Unknown',
                                        'quantity' => $orderLine['qtyOrd'] ?? 0,
                                        'status' => $orderLine['status'] ?? 'Unknown',
                                        'message' => $orderLine['message'] ?? 'No message provided'
                                    ]
                                );
                            }
                        }
                    }
                }
            }

            Log::info('Successfully created sales order on Kinseys');

        }
    }
}
