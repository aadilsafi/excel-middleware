<?php

namespace App\Services;

use App\Models\ExcelProcessingJob;
use App\Models\ExcelProcessingResult;
use App\Jobs\ProcessExcelRowJob;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class ExcelProcessingService
{
    public function processUploadedFile(ExcelProcessingJob $job): void
    {
        try {
            Log::info("Starting Excel processing for job {$job->id}");

            // Update job status
            $job->update([
                'status' => 'processing',
                'processing_started_at' => now(),
            ]);

            // Read the Excel file - use direct path
            $filePath = storage_path('app/public/' . $job->file_path);

            Log::info("Attempting to read file: {$filePath}");
            Log::info("File exists: " . (file_exists($filePath) ? 'yes' : 'no'));

            if (!file_exists($filePath)) {
                throw new Exception("File does not exist at path: {$filePath}");
            }

            Log::info("File size: " . filesize($filePath) . " bytes");

            $data = Excel::toArray([], $filePath);

            if (empty($data) || empty($data[0])) {
                throw new Exception('Excel file is empty or could not be read');
            }

            $rows = $data[0]; // Get first sheet
            $headers = array_shift($rows); // Remove header row

            // Find the manufacturer and part number columns
            $manufacturerColumn = $this->findColumnIndex($headers, 'manufacturer');
            $partNumberColumn = $this->findColumnIndex($headers, 'manufacturer part #');

            if ($manufacturerColumn === false || $partNumberColumn === false) {
                throw new Exception('Required columns "Manufacturer" and "Manufacturer part #" not found in Excel file');
            }

            // Filter out empty rows
            $validRows = array_filter($rows, function($row) use ($manufacturerColumn, $partNumberColumn) {
                return !empty($row[$manufacturerColumn]) && !empty($row[$partNumberColumn]);
            });

            if (empty($validRows)) {
                throw new Exception('No valid data rows found in Excel file');
            }

            // Update job with total rows
            $job->update([
                'total_rows' => count($validRows)
            ]);

            // Create result records for each row
            $rowNumber = 1;
            foreach ($validRows as $row) {
                $manufacturer = trim($row[$manufacturerColumn] ?? '');
                $partNumber = trim($row[$partNumberColumn] ?? '');

                if (empty($manufacturer) || empty($partNumber)) {
                    continue;
                }

                $result = ExcelProcessingResult::create([
                    'excel_processing_job_id' => $job->id,
                    'row_number' => $rowNumber,
                    'manufacturer' => $manufacturer,
                    'manufacturer_part_number' => $partNumber,
                    'status' => 'pending',
                ]);

                // Dispatch processing job for this row
                ProcessExcelRowJob::dispatch($result)->onQueue('reports');

                $rowNumber++;
            }

            Log::info("Created {$rowNumber} processing jobs for Excel job {$job->id}");

        } catch (Exception $e) {
            Log::error("Excel processing failed for job {$job->id}: " . $e->getMessage());

            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processing_completed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Find column index by header name (case insensitive)
     */
    private function findColumnIndex(array $headers, string $searchHeader): int|false
    {
        $searchHeader = strtolower(trim($searchHeader));

        foreach ($headers as $index => $header) {
            $header = strtolower(trim($header ?? ''));

            // Exact match
            if ($header === $searchHeader) {
                return $index;
            }

            // Partial matches for common variations
            if ($searchHeader === 'manufacturer' && strpos($header, 'manufacturer') !== false) {
                return $index;
            }

            if ($searchHeader === 'manufacturer part #' &&
                (strpos($header, 'part') !== false && strpos($header, 'number') !== false) ||
                (strpos($header, 'part') !== false && strpos($header, '#') !== false) ||
                (strpos($header, 'manufacturer part') !== false)) {
                return $index;
            }
        }

        return false;
    }
}
