<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GetVendorProducts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public $timeout = 0;
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $sellerCloudService = new \App\Services\SellerCloudService();
        $pageNumber = 1;
        do {
            $products = $sellerCloudService->getProducts($pageNumber);
            foreach ($products as $product) {
                \App\Models\Product::updateOrCreate(
                    ['ProductSKU' => $product['ProductSKU']],
                    [
                        'VendorID' => $product['VendorID'],
                        'Price' => $product['Price'],
                        'VendorSKU' => $product['VendorSKU'],
                        'IsAvailable' => $product['IsAvailable'],
                        'DateModified' => $product['DateModified'],
                        'Notes' => $product['Notes'],
                        'PricePerCase' => $product['PricePerCase'],
                        'ProductName' => $product['ProductName'],
                        'QtyPerCase' => $product['QtyPerCase'],
                        'Qty' => $product['Qty'],
                    ]
                );
            }
            $pageNumber++;
        } while (count($products) > 0);
    }
}
