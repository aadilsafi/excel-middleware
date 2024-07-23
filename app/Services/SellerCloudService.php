<?php

namespace App\Services;

use App\Mail\FilesReport;
use App\Services\Interfaces\SellerCloudInterface;
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
            if ($e->getResponse()->getStatusCode() == 401) {
                Cache::forget('seller_cloud_token');
                $this->initializeToken();
            }
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
}
