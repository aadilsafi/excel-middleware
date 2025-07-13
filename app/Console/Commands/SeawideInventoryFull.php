<?php

namespace App\Console\Commands;

use App\Jobs\SeawideInventoryFull as JobsSeawideInventoryFull;
use Illuminate\Console\Command;

class SeawideInventoryFull extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seawide-inventory-full';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seawide Inventory full';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        \dispatch(new JobsSeawideInventoryFull())->onQueue('seawide-inventory');
    }
}
