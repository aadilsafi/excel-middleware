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
    private $maxRetries = 2;

    public function __construct()
    {
        Log::info('inside constructor');
        $this->baseUrl = 'https://ci.api.sellercloud.com/rest/api/';
        $this->username = env('SELLER_CLOUD_USERNAME');
        $this->password = env('SELLER_CLOUD_PASSWORD');
        $this->headers = [
            'Content-Type' => 'application/json',
        ];
        $this->client = new Client();

        // Don't initialize token or make API calls in constructor
        // Just set up the basic properties
    }

    /**
     * Get a valid token, refreshing if necessary
     */
    private function getValidToken()
    {
        Log::info('inside get valid token');
        // Try to get cached token first
        $this->token = Cache::get('seller_cloud_token');

        // If no token in cache, get a new one
        if (!$this->token) {
            $this->refreshToken();
        }

        // Update headers with current token
        $this->headers['Authorization'] = 'Bearer ' . $this->token;

        return $this->token;
    }

    /**
     * Force refresh the token
     */
    private function refreshToken()
    {
        Log::info('inside refresh token');
        try {
            Log::info(message: 'Refreshing SellerCloud token');
            $response = $this->client->post($this->baseUrl . 'token', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'Username' => $this->username,
                    'Password' => $this->password,
                ],
            ]);

            $this->token = json_decode($response->getBody(), true)['access_token'];

            // Cache for slightly less than the typical expiry time (2000 minutes)
            Cache::put('seller_cloud_token', $this->token, now()->addMinutes(2000));

            return $this->token;
        } catch (Exception $e) {
            Log::error('Failed to refresh SellerCloud token: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute an API request with token retry logic
     */
    private function executeRequest($method, $endpoint, $options = [], $retryCount = 0)
    {
        Log::info('inside execution');
        // Ensure we have a valid token before making the request
        $this->getValidToken();

        // Make sure headers are in the options
        if (!isset($options['headers'])) {
            $options['headers'] = $this->headers;
        }

        try {
            $response = $this->client->$method($this->baseUrl . $endpoint, $options);
            return $response;
        } catch (Exception $e) {
            // Check if it's a token error and we haven't exceeded max retries
            if ($retryCount < $this->maxRetries &&
                (strpos($e->getMessage(), '401') !== false ||
                 strpos($e->getMessage(), 'Invalid token') !== false)) {

                Log::warning('Token error detected, refreshing and retrying. Attempt: ' . ($retryCount + 1));

                // Force token refresh
                Cache::forget('seller_cloud_token');
                $this->refreshToken();

                // Update headers in options
                if (isset($options['headers'])) {
                    $options['headers']['Authorization'] = 'Bearer ' . $this->token;
                } else {
                    $options['headers'] = $this->headers;
                }
                // Retry the request
                return $this->executeRequest($method, $endpoint, $options, $retryCount + 1);
            }

            // If we've reached max retries or it's not a token issue, throw the exception
            throw $e;
        }
    }
    public function getProducts($pageNumber = 1, $pageSize = 100, $vendorId = 15073)
    {
        // pass bearer token
        try {
            $response = $this->executeRequest('get', "Vendors/$vendorId/products?model.pageNumber=$pageNumber&model.pageSize=$pageSize", [
                'headers' => $this->headers,
            ]);

            return json_decode($response->getBody(), true)['Items'];
        } catch (Exception $e) {
            Log::error('Failed to get products: ' . $e->getMessage());
            return [];
        }
    }
    public function getOrder()
    {
        $response = $this->executeRequest('get', "Orders?model.pageNumber=1&model.pageSize=100", [
            'headers' => $this->headers,
        ]);

        return json_decode($response->getBody(), true)['Items'];
    }
    public function updateShipping($order_id, $ship_date, $tracking_number, $carrier_name = 'FedEx', $shipping_method = 'FedEx 2Day', $warehouses_id = 255)
    {
        try {
            $response = $this->executeRequest('put',"Orders/ShippingStatus/SinglePackage", [
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
            $response = $this->executeRequest('put', "Inventory/ImportPhysicalInventory", [
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
    public function updateProduct ($product_id,$site_cost)
    {
        try {
            Log::info('Updating product id: ' . $product_id . ' with site cost: ' . $site_cost);
            $response = $this->executeRequest('put', "Catalog/BasicInfo", [
                'headers' => $this->headers,
                'json' => [
                    "ProductID" => $product_id,
                    "SiteCost" => $site_cost
                ],
            ]);
            return true;
        } catch (Exception $e) {
            Log::error($e->getMessage());
            Log::info('Failed to update product id: ' . $product_id . ' with site cost: ' . $site_cost);
            return false;
        }
    }
}
