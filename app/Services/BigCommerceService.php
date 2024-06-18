<?php

namespace App\Services;

use App\Services\Interfaces\BigCommerceInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;


class BigCommerceService implements BigCommerceInterface
{
    private $baseUrl;
    private $clientId;
    private $accessToken;
    private $headers;

    public function __construct()
    {
        $this->baseUrl = 'https://api.bigcommerce.com/' . env('BC_LOCAL_STORE_HASH') . '/v3/catalog/products';
        $this->clientId = env('BC_LOCAL_CLIENT_ID');
        $this->accessToken = env('BC_LOCAL_ACCESS_TOKEN');
        $this->headers = [
            'X-Auth-Client' => $this->clientId,
            'X-Auth-Token' => $this->accessToken,
            'Content-Type' => 'application/json',
        ];
    }

    public function getProducts()
    {
        $client = new Client();

        $response = $client->get($this->baseUrl, [
            'headers' => $this->headers,
        ]);

        return json_decode($response->getBody(), true)['data'];
    }

    public function createProduct(array $data)
    {
        $client = new Client();

        $response = $client->post($this->baseUrl, [
            'headers' => $this->headers,
            'json' => $data,
        ]);

        return json_decode($response->getBody(), true);
    }

    public function updateProduct(int $productId, array $data)
    {
        $client = new Client();

        $response = $client->put($this->baseUrl . '/' . $productId, [
            'headers' => $this->headers,
            'json' => $data,
        ]);

        return json_decode($response->getBody(), true);
    }

    public function deleteProduct(int $productId)
    {
        $client = new Client();

        $response = $client->delete($this->baseUrl . '/' . $productId, [
            'headers' => $this->headers,
        ]);

        return $response->getStatusCode() === 204;
    }

    public function getProduct(int $productId)
    {
        $client = new Client();

        $response = $client->get($this->baseUrl . '/' . $productId, [
            'headers' => $this->headers,
        ]);

        return json_decode($response->getBody(), true);
    }
    public function listProductCustomFields(int $productId)
    {
        $client = new Client();
        $response = $client->get($this->baseUrl . '/' . $productId . '/custom-fields', [
            'headers' => $this->headers,
        ]);
        return json_decode($response->getBody(), true);
    }

    public function createProductCustomField(int $productId, array $data)
    {
        $custom_field_id = $this->getCustomFieldId($productId, $data['name']);
        if ($custom_field_id) {
            return $this->updateProductCustomField($productId, $custom_field_id, $data);
        } else {
            $client = new Client();
            try {
                $response = $client->post($this->baseUrl . '/' . $productId . '/custom-fields', [
                    'headers' => $this->headers,
                    'json' => $data,
                ]);
            } catch (\Exception $e) {
                $response = $e->getResponse();
                $response_body = $response->getBody()->getContents();
                $response_data = json_decode($response_body, true);
                Log::error('Error creating custom field: ' . $response_data['title']);
                throw new \Exception($response_data['title'], $response->getStatusCode());
            }

            return json_decode($response->getBody(), true);
        }
    }

    public function updateProductCustomField(int $productId, int $customFieldId, array $data)
    {
        $client = new Client();
        $response = $client->put($this->baseUrl . '/' . $productId . '/custom-fields/' . $customFieldId, [
            'headers' => $this->headers,
            'json' => $data,
        ]);
        return json_decode($response->getBody(), true);
    }

    public function getCustomFieldId(int $productId, string $custom_field_name)
    {
        try {

            $client = new Client();
            $response = $client->get($this->baseUrl . "/" . $productId  . '/custom-fields', [
                'headers' => $this->headers,
            ]);
            $custom_fields = json_decode($response->getBody(), true);
            $custom_fields = Arr::get($custom_fields, 'data', []); // get the data key from the response
            foreach ($custom_fields as $custom_field) {
                if ($custom_field['name'] === $custom_field_name) {
                    return $custom_field['id'];
                }
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        return null;
    }
    public function deleteProductCustomField (int $productId, int $customFieldId)
    {
        $client = new Client();
        $response = $client->delete($this->baseUrl . '/' . $productId . '/custom-fields/' . $customFieldId, [
            'headers' => $this->headers,
        ]);
        return $response->getStatusCode() === 204;
    }
    public function getProductCustomFields (int $productId)
    {
        $client = new Client();
        $response = $client->get($this->baseUrl . '/' . $productId . '/custom-fields', [
            'headers' => $this->headers,
        ]);
        return json_decode($response->getBody(), true)['data'];
    }
}
