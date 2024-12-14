<?php

namespace App\Console\Commands;

use App\Jobs\DeleteOrdersJob;
use Illuminate\Console\Command;

class DeleteOldOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete-old-orders';

    /**
     * The console Delete Orders older than 4 days.
     *
     * @var string
     */
    protected $description = 'Delete Orders older than 4 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // trigger job
        dispatch(new DeleteOrdersJob());
    }
}
