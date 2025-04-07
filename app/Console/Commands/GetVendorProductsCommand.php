<?php

namespace App\Console\Commands;

use App\Jobs\GetVendorProducts;
use Illuminate\Console\Command;

class GetVendorProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get-vendor-products {vendorId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Vendor Products';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $vendor_id = $this->vendorId = $this->argument('vendorId');
        \dispatch(new GetVendorProducts($vendor_id))->onQueue('products');
    }
}
