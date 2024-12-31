<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProductsShippingRates;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MakeShippingRateFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $headerRow;
    protected $rows;

    public $timeout = 0;
    public $tries = 0;

    public $retryAfter = 60;

    /**
     * Create a new job instance.
     */
    public function __construct($headerRow, $rows)
    {
        $this->headerRow = $headerRow;
        $this->rows = $rows;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $modifiedData = [];

        foreach ($this->rows as $row) {

            // Call the API
            $response = $this->callAPI($row);
            if ($response) {
                // Build the row data based on the header
                // $rowData = $this->buildRowData($this->headerRow, $row, $response);
                $rowData = [];
                $rowData[] = $row['A'] ?? ''; // Value for VCPN
                $rowData[] = $row['B'] ?? ''; // Value for CaseQty
                $rowData[] = $response['UPM'] ?? '';
                $rowData[] = $response['U11'] ?? '';
                $rowData[] = $response['U09'] ?? '';
                $rowData[] = $response['U02'] ?? '';
                $rowData[] = $response['U15'] ?? '';
                $rowData[] = $response['U19'] ?? '';
                $rowData[] = $response['U52'] ?? '';
                $rowData[] = $response['U03'] ?? '';
                $rowData[] = $response['U13'] ?? '';
                $rowData[] = $response['U55'] ?? '';
                $rowData[] = $response['U53'] ?? '';
                $rowData[] = $response['LTL'] ?? '';

                // Add row data to modified data
                $modifiedData[] = $rowData;
            }
        }

        // Append the processed data to the output file
        $this->appendToFile($modifiedData);
    }

    private function callAPI($row)
    {
        if (isset($row['A'], $row['B'])) {
            try {
                // Initialize your API service
                $seawideService = new \App\Services\SeawideService();

                // Fetch the rates
                $res = $seawideService->GetShippingOptionsAll($row['A'], '04619', $row['B']);
                $res = collect($res->Rates)->pluck('Rate', 'ServiceLevel');

                // Prepare a consistent response format
                $apiResponse = [];
                foreach ($res as $serviceLevel => $rate) {
                    $apiResponse[$serviceLevel] = $rate; // Use service levels as keys
                }

                return $apiResponse;
            } catch (Exception $ex) {
                Log::info($ex);
                Log::info($row['A']);
                Log::info('an error occured while processing the api call');
                throw new \RuntimeException('API call failed, retrying...');

            }
        }

        return null; // Return null if the row data is invalid
    }


    private function buildRowData($headers, $row, $response)
    {
        $rowData = [];

        foreach ($headers as $header) {
            if ($header === 'VCPN') {
                $rowData[] = $row['A'] ?? '';
            } elseif ($header === 'CaseQty') {
                $rowData[] = $row['B'] ?? '';
            } else {
                $rowData[] = $response[$header] ?? '';
            }
        }
        return $rowData;
    }

    private function appendToFile($data)
    {
        // Path for the output file
        $outputPath = storage_path('app/exports/modified_file.xlsx');

        // Check if file exists and create if not
        if (!Storage::exists('exports/modified_file.xlsx')) {
            Excel::store(new ProductsShippingRates([$this->headerRow]), 'exports/modified_file.xlsx', 'local');
        }

        // Append new data to the file
        $spreadsheet = IOFactory::load($outputPath);
        $sheet = $spreadsheet->getActiveSheet();
        $lastRow = $sheet->getHighestRow();

        foreach ($data as $row) {
            $lastRow++;
            $sheet->fromArray($row, null, "A$lastRow");
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($outputPath);
    }
}
