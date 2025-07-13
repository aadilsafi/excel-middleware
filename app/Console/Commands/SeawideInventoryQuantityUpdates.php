<?php

namespace App\Console\Commands;

use App\Jobs\SeawideInventoryQuantityUpdates as JobsSeawideInventoryQuantityUpdates;
use Illuminate\Console\Command;

class SeawideInventoryQuantityUpdates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seawide-inventory-quantity-updates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seawide Inventory Quantity Updates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        \dispatch(new JobsSeawideInventoryQuantityUpdates())->onQueue('seawide-inventory');

    }
}
