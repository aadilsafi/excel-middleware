<?php

namespace App\Console\Commands;

use App\Jobs\UpdateTrackingKinseyJob;
use Illuminate\Console\Command;

class UpdateTrackingKinsey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-tracking-kinsey';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // dispatch kinsey job
        $orders = \App\Models\Order::where('vendor_id', 16344)->get();
        if (count($orders) > 0) {
            \dispatch(new UpdateTrackingKinseyJob($orders));
        }
    }
}
