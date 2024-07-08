<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessFtpFilesJob implements ShouldQueue
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
    public function handle()
    {
        Log::info('processing ftp files for orders');
        $sellerCloudService = new \App\Services\SellerCloudService();

        // Define the FTP directory
        $directory = 'eo/outgoing';
        $files = Storage::disk('rsr')->files($directory);

        if (empty($files)) {
            $this->info('No files found.');
            return;
        }

        foreach ($files as $file) {
            $filePath = storage_path('app/' . $file);
            $ftp_file = Storage::disk('rsr')->get($file);

            $local_file = Storage::disk('local')->put($file, $ftp_file);

            $file_content = Storage::disk('local')->get($file);
            $attachment = [
                'path' => $filePath,
                'content' => $file_content,
                'name' => basename($file),
                'mime' => Storage::disk('local')->mimeType($file) ?? 'text/plain',
            ];
            // Send email
            if (strpos($filePath, 'ECONF') !== false) {
                // Just delete
            }
            else if (\strpos($filePath, 'ESHIP') !== false) {
                $tracker = $this->getTracking($file_content);
                $tracking_number = $tracker[0];
                $invoice_number  = $tracker[1];
                $order_id = $tracker[2];
                // $order_id = Order::where('order_source_id', $source_id)->first()->order_id;
                // Output the extracted data
                if ($tracking_number && $invoice_number) {
                    $this->info("Tracking Number: $tracking_number");
                    $this->info("Invoice Number: $invoice_number");
                    Log::info('Tracking : ' . $tracking_number);
                    Log::info('Tracking : ' . $invoice_number);

                    // Get date from file name
                    preg_match('/(\d{8})/', basename($file), $matches);
                    if (isset($matches[1])) {
                        $file_date = Carbon::createFromFormat('Ymd', $matches[1]);
                    } else {
                        $file_date = Carbon::now();
                    }
                    $ship_date = Carbon::parse($file_date)->format('Y-m-d\TH:i:s.v\Z');
                    // last column is warehouse id default is 255 for RSR Dropship
                    $sellerCloudService->updateShipping($order_id, $ship_date, $tracking_number, 'FedEx', 'FedEx 2Day');
                } else {
                    $this->error('Tracking number or invoice number not found.');
                }
            } else {
                $sellerCloudService->sendEmail($attachment);
                // Mail::to('test@test.com')->send(new FilesReport($attachment));
            }
            Storage::disk('local')->delete($file);
            Storage::disk('rsr')->delete($file);
        }



        // Delete files
        // foreach ($files as $file) {
        //     // Storage::disk('rsr')->delete($file);
        // }

        $this->info('Files have been processed, emailed, and deleted successfully.');
    }

    public function getTracking($fileContent)
    {
        $lines = explode(PHP_EOL, $fileContent);

        // Initialize variables to store the tracking number and invoice number
        $trackingNumber = '';
        $invoiceNumber = '';
        $orderId = '';
        // Loop through each line and find the relevant data
        foreach ($lines as $line) {
            if (strpos($line, ';60;') !== false) {
                $parts = explode(';', $line);
                if (count($parts) > 4) {
                    $orderId = trim($parts[0]);
                    $trackingNumber = trim($parts[3]);
                    $invoiceNumber = trim($parts[4]);
                    break; // Exit loop once found
                }
            }
        }

        return [$trackingNumber, $invoiceNumber, $orderId];
    }
}
