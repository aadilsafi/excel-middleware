<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessSeawideOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process-seawide-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all Sea Wide Orders';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        \dispatch(new \App\Jobs\ProcessAllSeaWideOrdersJob());
    }
}
