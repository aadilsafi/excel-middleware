<?php

namespace App\Jobs;

use App\Exports\InventoryQuantityUpdateExport;
use App\Services\SeawideService;
use App\Services\SellerCloudService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class SeawideInventoryQuantityUpdates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        $seawideService = new SeawideService();
        $res = $seawideService->GetInventoryQuantityUpdates();
        $rawTable = $res->InventoryUpdates['Table'] ?? [];
        if (empty($rawTable) || count($rawTable) <= 0) {
            Log::info('Seawide Inventory Quantity Updates empty response');
            // schedule the job again for 30 minutes later
            // self::dispatch(/* pass necessary data */)
            //     ->delay(now()->addMinutes(30));
            Log::info('Seawide Inventory Quantity Updates job rescheduled for 30 minutes later');
            return;
        }
        $fileName = 'inventory-quantity-updates-' . now()->format('Ymd_His') . '.xlsx';
        $tempPath = storage_path("app/temp/$fileName");

        // Ensure the temp directory exists
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0777, true);
        }

        Excel::store(new InventoryQuantityUpdateExport($rawTable), "temp/$fileName");
        $fileContent = file_get_contents($tempPath);

        // Prepare file data
        $fileData = [
            'name' => $fileName,
            'content' => $fileContent
        ];
        $sellerCloudService = new SellerCloudService();
        $sellerCloudService->sendEmail($fileData, [
            'title' => 'Inventory Update Report',
            'heading' => 'Latest Inventory Quantities',
            'body' => 'Attached is the latest inventory quantity update Excel report.'
        ], 'Latest SeaWide Inventory Quantities');
        unlink($tempPath);
        Log::info('Inventory Quantity Update Email sent via Zapier.');
    }
}
