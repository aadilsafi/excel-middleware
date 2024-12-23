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
        $file_path = Storage::disk('local')->path('orderImports/newimport.csv');
        $spreadsheet = IOFactory::load($file_path);
        $sheet = $spreadsheet->getActiveSheet();

        // Parse the file data
        $rows = $sheet->toArray(null, true, true, true); // Parse rows
        $modifiedData = [];
        $headers = ['VCPN', 'CaseQty']; // Start with static headers
        $isFirstRow = true;

        foreach ($rows as $key => $row) {
            if ($isFirstRow) {
                $isFirstRow = false; // Skip processing the first row as it's header
                continue;
            }

            // Call APIs with data and store responses
            $response = $this->callAPI($row);
            if ($response) {
                // Merge new service levels into headers dynamically
                foreach (array_keys($response) as $serviceLevel) {
                    if (!in_array($serviceLevel, $headers)) {
                        $headers[] = $serviceLevel; // Add new service level to headers
                    }
                }

                // Build row data based on headers
                $rowData = [];
                foreach ($headers as $header) {
                    if ($header === 'VCPN') {
                        $rowData[] = $row['A'] ?? ''; // Value for VCPN
                    } elseif ($header === 'CaseQty') {
                        $rowData[] = $row['B'] ?? ''; // Value for CaseQty
                    } else {
                        $rowData[] = $response[$header] ?? ''; // API response data or empty
                    }
                }

                // Add row data to modified data
                $modifiedData[] = $rowData;
            }

            if ($key >= 10) { // Limit processing for testing (optional)
                break;
            }
        }

        // Add headers as the first row in the modified data
        $modifiedData = array_merge([$headers], $modifiedData);

        // File name for the modified file
        $modifiedFileName = 'modified_file.xlsx';

        // Store the modified file in the exports directory
        Excel::store(new ProductsShippingRates($modifiedData), 'exports/' . $modifiedFileName, 'local');

        // Output success message
        $this->info("File has been successfully processed and stored at: storage/exports/$modifiedFileName");
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
