<?php

namespace App\Services;

use App\Mail\FilesReport;
use App\Services\Interfaces\SellerCloudInterface;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SellerCloudService implements SellerCloudInterface
{
    private $baseUrl;
    private $username;
    private $password;
    private $headers;
    private $token;
    private $client;

    public function __construct()
    {

        // Vendors/15073/products?model.pageNumber=1&model.pageSize=100
        try {
            $this->initializeToken();
            $products = $this->getProducts();
        } catch (Exception $ex) {
        }
    }

    public function initializeToken()
    {
        $this->baseUrl = 'https://ci.api.sellercloud.com/rest/api/';
        $this->username = env('SELLER_CLOUD_USERNAME');
        $this->password = env('SELLER_CLOUD_PASSWORD');
        $this->headers = [
            'Content-Type' => 'application/json',
        ];
        $this->client = new Client();
        $this->token = Cache::remember('seller_cloud_token', 2000, function () {
            $response = $this->client->post($this->baseUrl . 'token', [
                'headers' => $this->headers,
                'json' => [
                    'Username' => $this->username,
                    'Password' => $this->password,
                ],
            ]);

            return json_decode($response->getBody(), true)['access_token'];
        });
        $this->headers = Arr::add($this->headers, 'Authorization', 'Bearer ' . $this->token);
    }
    public function getProducts($pageNumber = 1, $pageSize = 100, $vendorId = 15073)
    {
        // pass bearer token
        try {
            $response = $this->client->get($this->baseUrl . "Vendors/$vendorId/products?model.pageNumber=$pageNumber&model.pageSize=$pageSize", [
                'headers' => $this->headers,
            ]);

            return json_decode($response->getBody(), true)['Items'];
        } catch (Exception $e) {
            Cache::forget('seller_cloud_token');
            $this->initializeToken();
            return [];
        }
    }
    public function getOrder()
    {
        $response = $this->client->get($this->baseUrl . "Orders?model.pageNumber=1&model.pageSize=100", [
            'headers' => $this->headers,
        ]);

        return json_decode($response->getBody(), true)['Items'];
    }
    public function updateShipping($order_id, $ship_date, $tracking_number, $carrier_name = 'FedEx', $shipping_method = 'FedEx 2Day', $warehouses_id = 255)
    {
        try {
            $response = $this->client->put($this->baseUrl . "Orders/ShippingStatus/SinglePackage", [
                'headers' => $this->headers,
                'json' => [
                    'OrderID' => $order_id,
                    'ShipDate' => $ship_date,
                    'TrackingNumber' => $tracking_number,
                    'CarrierName' => $carrier_name,
                    'ShippingMethod' => $shipping_method,
                    'WarehouseID' => $warehouses_id
                ],
            ]);
            return true;
        } catch (Exception $e) {
            Log::error($e->getMessage());
            $this->sendEmail(null, [
                'body' => 'Failed to update order id: ' . $order_id . ' and tracking number: ' . $tracking_number . ' at ' . $ship_date,
                'title' => "Failed Updating Shipping Details Order Id = " . $order_id,
                'heading' => "Failed Updating Shipping Details Order Id = " . $order_id,
            ]);
            return false;
        }
    }
    public function sendEmail(
        $file = null,
        $data = ['body' => '', 'heading' => '', 'title' => ''],
    ) {
        // Render the view content
        $emailContent = view('emails.files_report', [
            'title' => $data['title'] ?? '',
            'heading' => $data['heading'] ?? '',
            'body' => $data['body'] ?? ''
        ])->render();

        // Send the request to Zapier
        $email_setup = [
            [
                'name'     => 'htmlContent',
                'contents' => $emailContent
            ]
        ];

        // Conditionally add the attachment if it exists
        if ($file) {
            $file_content = $file['content'];
            $email_setup[] = [
                'name'     => 'attachment',
                'contents' => $file_content,
                'filename' => $file['name']
            ];
        }
        $response = $this->client->post('https://hooks.zapier.com/hooks/catch/19222741/23hglr5/', [
            'multipart' => $email_setup
        ]);

        if ($response->getStatusCode() == 200) {
            Log::info('Successfully sent to Zapier');
        } else {
            Log::error('Failed to send to Zapier');
        }

        return response()->json(['status' => 'success']);
    }
    // public function ImportProducts($file_content)
    // {
    //     try {
    //         $response = $this->client->post($this->baseUrl . "Catalog/Imports/Custom", [
    //             'headers' => $this->headers,
    //             'json' => [
    //                 "ProfileName" =>  "RSR Catalog test",
    //                 "Metadata" =>  [
    //                     "CreateProductIfDoesntExist" =>  true,
    //                     "CompanyIdForNewProduct" =>  0,
    //                     "UpdateFromCompanyId" =>  0,
    //                     "DoNotUpdateProducts" =>  true
    //                 ],
    //                 "FileContents" =>  $file_content,
    //                 "FileExtension" =>  "csv",
    //                 "Format" =>  1
    //             ],
    //         ]);
    //         return true;
    //     } catch (Exception $e) {
    //         dd($e);
    //         return false;
    //     }
    // }

    public function updateInventory($file_content,$file_extension="txt", $warehouse_id = 260)
    {
        try {
            $date = Carbon::now()->format('m/d/Y h:i A');
            $response = $this->client->put($this->baseUrl . "Inventory/ImportPhysicalInventory", [
                'headers' => $this->headers,
                'json' => [
                    "UpdateType" => 0,
                    "FileContent" => $file_content,
                    "Format" => 0,
                    // "FileExtension" => $file_extension,
                    "WarehouseID" => $warehouse_id,
                    "InventoryDate"=> $date,
                    "MergeDefaultWarehouseInventoryIntoShadowParent" => true

                ],
            ]);

            return true;
        } catch (Exception $e) {
            Log::error($e->getMessage());
            $this->sendEmail(null, [
                'body' => 'Unable to update kinseys inventory',
                'title' => "Unable to update kinseys inventory",
                'heading' => "Unable to update kinseys inventory",
            ]);
            return false;
        }
    }
}
