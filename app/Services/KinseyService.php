<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
class KinseyService
{
    public function getProducts()
    {

        try {
            $filePath = storage_path('app/kinsey/kinsey_inventory.json');

            // Create a handler stack
            $stack = HandlerStack::create();

            // Add retry middleware
            $maxRetries = 5;
            $retryMiddleware = Middleware::retry(function (
                $retries,
                Request $request,
                Response $response = null,
                $exception = null
            ) use ($maxRetries) {
                // Retry on connection errors or 5xx server errors
                if ($retries >= $maxRetries) {
                    return false;
                }

                // Accept both ConnectException and RequestException
                if ($exception instanceof ConnectException || $exception instanceof RequestException) {
                    return true;
                }

                return false;
            }, function ($retries) {
                // Exponential backoff with some randomness
                return (1000 * pow(2, $retries)) + (rand(0, 1000));
            });

            $stack->push($retryMiddleware);

            // Create client with custom handler stack
            $client = new Client([
                'handler' => $stack,
                'timeout' => 300,
                'connect_timeout' => 30,
                'read_timeout' => 300,
                'http_errors' => false,
                'headers' => [
                    'X-API-KEY' => env('KINSEYS_KEY'),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);

            // Download to file
            $client->request('GET', 'https://api.kinseysinc.com/v2/inventory', [
                'sink' => $filePath
            ]);

            // Process the file if it exists and has content
            if (file_exists($filePath) && filesize($filePath) > 0) {
                $inventoryData = json_decode(file_get_contents($filePath), true);
                if (json_last_error() === JSON_ERROR_NONE && isset($inventoryData['Products'])) {
                    $inventoryData = collect($inventoryData['Products']);
                    return collect($inventoryData);
                }
                return collect([]);
            } else {
                return collect([]);
            }
        } catch (Exception $e) {
            Log::error('Kinsey API Error: ' . $e->getMessage());
            return collect([]);
        }
    }
}
