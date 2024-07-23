<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Mail\FilesReport;
use App\Models\Order;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class ProcessOrderFiles extends Command
{
    protected $signature = 'order-files-process';
    protected $description = 'Read files from a directory, send them as email attachments, and delete them';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {

            Log::info('processing ftp files for orders command');
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
                Log::info('processing order file :'.$filePath);
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
                } else if (\strpos($filePath, 'ESHIP') !== false) {
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
                        $res = $sellerCloudService->updateShipping($order_id, $ship_date, $tracking_number);
                        if(!$res){
                            Log::error('Failed to update order id: ' . $order_id . ' and tracking number: ' . $tracking_number. ' at ' . $ship_date);
                            continue;
                        }
                    } else {
                        $this->error('Tracking number or invoice number not found.');
                    }
                } else {
                    $tracker = $this->getTracking($file_content);
                    $order_id = $tracker[2];
                    $sellerCloudService->sendEmail($attachment, ['heading' => 'Order File Error on OrderId => ' . $order_id, 'body' => 'Order file attached on OrderId => ' . $order_id, 'title' => 'Order File on OrderId => ' . $order_id]);
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
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            $this->error('An error occurred while processing the files.');
        }
    }

    public function getTracking($fileContent, $is_error = false)
    {
        $lines = explode(PHP_EOL, $fileContent);

        // Initialize variables to store the tracking number and invoice number
        $trackingNumber = '';
        $invoiceNumber = '';
        $orderId = '';
        // Loop through each line and find the relevant data
        foreach ($lines as $line) {
            if ($is_error) {
                if (strpos($line, ';90;') !== false) {
                    $parts = explode(';', $line);
                    $orderId = trim($parts[0]);
                    break; // Exit loop once found
                }
            } else {
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
        }

        return [$trackingNumber, $invoiceNumber, $orderId];
    }
}
