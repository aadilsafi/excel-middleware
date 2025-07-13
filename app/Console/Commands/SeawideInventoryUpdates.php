<?php

namespace App\Console\Commands;

use App\Jobs\SeawideInventoryUpdates as JobsSeawideInventoryUpdates;
use Illuminate\Console\Command;

class SeawideInventoryUpdates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seawide-inventory-updates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seawide Inventory Updates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        \dispatch(new JobsSeawideInventoryUpdates())->onQueue('seawide-inventory');
    }
}
