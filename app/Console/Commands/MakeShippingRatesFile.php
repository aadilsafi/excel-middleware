<?php

namespace App\Console\Commands;

use App\Jobs\MakeShippingRateFileJob;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ModifiedExport;
use App\Exports\ProductsShippingRates;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MakeShippingRatesFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:make-shipping-rates-file';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Making Shipping Rate File';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = Storage::disk('local')->path('orderImports/newimport.csv');
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Parse the file data
        $rows = $sheet->toArray(null, true, true, true); // Parse rows
        $headerRow = array_shift($rows); // Extract header row
        $headerRow[] ='UPM';
        $headerRow[] ='U11';
        $headerRow[] ='U09';
        $headerRow[] ='U02';
        $headerRow[] ='U15';
        $headerRow[] ='U19';
        $headerRow[] ='U52';
        $headerRow[] ='U03';
        $headerRow[] ='U13';
        $headerRow[] ='U55';
        $headerRow[] ='U53';
        $headerRow[] ='LTL';
        $chunkedRows = array_chunk($rows, length: 50); // Chunk rows into groups of 50
        foreach ($chunkedRows as $chunk) {
            MakeShippingRateFileJob::dispatch($headerRow, $chunk)
                ->delay(now()->addSeconds(60))
                ->onQueue('excel-file'); // Dispatch job for each chunk
        }

        $this->info("All rows have been queued for processing.");
    }


    private function callAPI($row)
    {
        if (isset($row['A'], $row['B'])) {
            $seawideService = new \App\Services\SeawideService();
            $res = $seawideService->GetShippingOptionsAll($row['A'], '04619', $row['B'])->Rates;

            // Collect and transform the API response
            $res = collect($res)->pluck('Rate', 'ServiceLevel');

            // Prepare a consistent response format
            $apiResponse = [];
            foreach ($res as $serviceLevel => $rate) {
                $apiResponse[$serviceLevel] = $rate; // Use service levels as keys
            }

            return $apiResponse;
        }

        return null; // Return null if the API call fails or inputs are invalid
    }
}
