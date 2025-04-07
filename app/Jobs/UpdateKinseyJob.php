<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Product;
use Exception;
use Illuminate\Support\Facades\Log;

class UpdateKinseyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 0;
    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // $xmlPath = storage_path('app/kinsey/kin-inv.xml');
            $txtPath = storage_path('app/kinsey/inventory.txt'); // Output file
            $products = Product::where('VendorID', 16344)->pluck('ProductSKU')->toArray();
            $kinsey = new \App\Services\KinseyService();
            $inventory = $kinsey->getProducts();
            $result = [];
            if (count($inventory) > 0) {
                // Filter the Products array to only include items with matching ProductSKU
                $filteredProducts = $inventory
                    ->filter(function ($product) use ($products) {
                        // Assuming manufacturerId in the API response corresponds to ProductSKU in your database
                        return in_array($product['upc'], $products);
                    })
                    ->values()
                    ->all();
                // diff between filtered products and products
                $notMatches = array_diff($products, array_column($filteredProducts, 'upc'));
                $notMatches = array_values($notMatches);

                // Create a new response array with only the filtered products
                $result = [
                    'Products' => $filteredProducts,
                    'notMatching' => $notMatches,
                ];
                // Process your inventory data here


            }
            else {
                Log::info("Error on Kinsey API getting products!");
                return;
            }

            // Open output file
            $file = fopen($txtPath, 'w');

            // Define header row
            $header = "ProductID\tWarehouse\tPhysicalInventoryQty\tInventoryDate\tLocationNotes";
            fwrite($file, $header . PHP_EOL);
            // Extract and write data
            $sellercloudService = new \App\Services\SellerCloudService();

            foreach ($result['Products'] as $item) {
                $upc = str_pad((string) $item['upc'], 12, "0", STR_PAD_LEFT); // Ensure 12-digit UPC
                    $qtyAvailable = (int) $item['quantityOnHand'] ?? 0;
                    $warehouse = "kinseys"; // Fixed value
                    $inventoryDate = ""; // Empty
                    $locationNotes = ""; // Empty
                    $price = (float) $item['price'] ?? 0;

                    $line = "$upc\t$warehouse\t$qtyAvailable\t$inventoryDate\t$locationNotes";
                    fwrite($file, $line . PHP_EOL);
                    $sellercloudService->updateProduct($upc,$price);
            }

            foreach ($result['notMatching'] as $item) {
                $upc = str_pad((string) $item, 12, "0", STR_PAD_LEFT); // Ensure 12-digit UPC
                    $qtyAvailable = 0;
                    $warehouse = "kinseys"; // Fixed value
                    $inventoryDate = ""; // Empty
                    $locationNotes = ""; // Empty

                    $line = "$upc\t$warehouse\t$qtyAvailable\t$inventoryDate\t$locationNotes";
                    fwrite($file, $line . PHP_EOL);
            }


            fclose($file);

            // get csv file content
            $file_content = base64_encode(file_get_contents($txtPath));
            $sellercloudService->updateInventory($file_content);

            Log::info('Successfully updated Kinsey inventory');
        } catch (Exception $ex) {
            Log::error('Failed to update Kinsey inventory: ' . $ex->getMessage());
        }
    }
}
