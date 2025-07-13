<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\ExcelProcessingResult;
use App\Models\ExcelProcessingJob;
use App\Services\OpenAIService;
use Exception;
use Illuminate\Support\Facades\Log;

class ProcessExcelRowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0; // 5 minutes timeout
    public $tries = 3;

    private ExcelProcessingResult $result;

    /**
     * Create a new job instance.
     */
    public function __construct(ExcelProcessingResult $result)
    {
        $this->result = $result;
    }

    /**
     * Execute the job.
     */
    public function handle(OpenAIService $openAIService): void
    {
        try {
            Log::info("Processing row {$this->result->row_number} for job {$this->result->excel_processing_job_id}");

            // Update status to processing
            $this->result->update([
                'status' => 'processing'
            ]);

            // Process with OpenAI
            $response = $openAIService->processProduct(
                $this->result->manufacturer,
                $this->result->manufacturer_part_number
            );

            Log::info("Process Product OpenAI response for row {$this->result->row_number}: " . json_encode($response));

            if ($response['success']) {
                // Update result with successful response
                $this->result->update([
                    'status' => 'completed',
                    'openai_response' => $response,
                    'processed_at' => now(),
                ]);

                Log::info("Successfully processed row {$this->result->row_number}");
            } else {
                // Handle OpenAI error
                $this->result->update([
                    'status' => 'failed',
                    'error_message' => $response['error'] ?? 'Unknown OpenAI error',
                    'processed_at' => now(),
                ]);

                Log::error("OpenAI error for row {$this->result->row_number}: " . ($response['error'] ?? 'Unknown error'));
            }

            // Update the parent job's processed count
            $this->updateJobProgress();

        } catch (Exception $e) {
            Log::error("Job failed for row {$this->result->row_number}: " . $e->getMessage());

            $this->result->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            $this->updateJobProgress();

            throw $e;
        }
    }

    /**
     * Update the parent job's progress
     */
    private function updateJobProgress(): void
    {
        $job = $this->result->excelProcessingJob;

        $completedCount = $job->results()
            ->whereIn('status', ['completed', 'failed'])
            ->count();

        $job->update([
            'processed_rows' => $completedCount
        ]);

        // Check if all rows are processed
        if ($completedCount >= $job->total_rows) {
            $job->update([
                'status' => 'completed',
                'processing_completed_at' => now(),
            ]);

            Log::info("Job {$job->id} completed processing all rows");

            // Dispatch CSV export job
            ExportResultsToCsvJob::dispatch($job)->onQueue('reports');
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessExcelRowJob failed permanently for result {$this->result->id}: " . $exception->getMessage());

        $this->result->update([
            'status' => 'failed',
            'error_message' => 'Job failed after maximum retries: ' . $exception->getMessage(),
            'processed_at' => now(),
        ]);

        $this->updateJobProgress();
    }
}
