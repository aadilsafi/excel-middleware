<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\ExcelProcessingJob;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class ExportResultsToCsvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes timeout

    private ExcelProcessingJob $processingJob;

    /**
     * Create a new job instance.
     */
    public function __construct(ExcelProcessingJob $processingJob)
    {
        $this->processingJob = $processingJob;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting CSV export for job {$this->processingJob->id}");

            $results = $this->processingJob->results()
                ->where('status', 'completed')
                ->orderBy('row_number')
                ->get();

            if ($results->isEmpty()) {
                Log::warning("No completed results found for job {$this->processingJob->id}");
                return;
            }

            // Create CSV content
            $csvContent = $this->generateCsvContent($results);

            // Generate filename
            $filename = 'exports/job_' . $this->processingJob->id . '_results_' . now()->format('Y-m-d_H-i-s') . '.csv';

            // Store the CSV file
            Storage::put($filename, $csvContent);

            // Update the job with export path
            $this->processingJob->update([
                'csv_export_path' => $filename
            ]);

            Log::info("CSV export completed for job {$this->processingJob->id}. File: {$filename}");

        } catch (Exception $e) {
            Log::error("CSV export failed for job {$this->processingJob->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate CSV content from results
     */
    private function generateCsvContent($results): string
    {
        $csv = [];

        // CSV Headers
        $headers = [
            'Row Number',
            'Manufacturer',
            'Manufacturer Part Number',
            'UPC',
            'Title',
            'Meta Description',
            'Product Description',
            'Bullet Points',
            // 'Key Specs',
            'Main Category',
            'Subcategory',
            'Type Category',
            'Keywords',
            'Product Highlights',
            // 'Additional Specs',
            'Status',
            'Error Message',
            'Processed At'
        ];

        $csv[] = $headers;

        // Data rows
        foreach ($results as $result) {
            $data = $result->openai_response['data'] ?? [];

            $row = [
                $result->row_number,
                $result->manufacturer,
                $result->manufacturer_part_number,
                $data['upc'] ?? '',
                $data['title'] ?? '',
                $data['meta_description'] ?? '',
                $data['product_description'] ?? '',
                isset($data['bullet_points']) ? implode('; ', $data['bullet_points']) : '',
                // isset($data['key_specs']) ? implode('; ', $data['key_specs']) : '',
                $data['main_category'] ?? '',
                $data['subcategory'] ?? '',
                $data['type_category'] ?? '',
                isset($data['keywords']) ? implode(', ', $data['keywords']) : '',
                isset($data['product_highlights']) ? implode('; ', $data['product_highlights']) : '',
                // isset($data['additional_specs']) ? implode('; ', $data['additional_specs']) : '',
                $result->status,
                $result->error_message ?? '',
                $result->processed_at ? $result->processed_at->format('Y-m-d H:i:s') : ''
            ];

            $csv[] = $row;
        }

        // Convert to CSV string
        $output = fopen('php://temp', 'r+');
        foreach ($csv as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csvString = stream_get_contents($output);
        fclose($output);

        return $csvString;
    }
}
