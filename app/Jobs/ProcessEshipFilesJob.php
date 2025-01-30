<?php

namespace App\Jobs;

use App\Services\SellerCloudService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessEshipFilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $file;
    private $ftpName;

    public function __construct(string $file, string $ftpName)
    {
        $this->file = $file;
        $this->ftpName = $ftpName;
        $this->onQueue('eship');
    }

    public function handle(): void
    {
        try {
            if (!Storage::disk($this->ftpName)->exists($this->file)) {
                Log::error("File not found in {$this->ftpName} storage: {$this->file}");
                return;
            }

            $file_content = Storage::disk($this->ftpName)->get($this->file);
            $this->processEshipContent($file_content);
            if (Storage::disk($this->ftpName)->exists($this->file)) {
                Storage::disk($this->ftpName)->delete($this->file);
                Log::info("Successfully deleted file from {$this->ftpName} FTP: {$this->file}");
            }
        } catch (\Exception $e) {
            Log::error('ProcessEshipFilesJob failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function processEshipContent(string $content): void
    {
        $sellerCloudService = new SellerCloudService();
        $shipments = $this->parseShipmentsFromContent($content);

        foreach ($shipments as $shipment) {
            $order_id = $shipment['order_id'] ?? '';
            $tracking_number = $shipment['tracking_number'] ?? '';

            if (!$tracking_number || !$order_id) {
                Log::info('Tracking number or invoice number not found.');
                continue;
            }

            Log::info('Processing OrderID: ' . $order_id . ' with Tracking: ' . $tracking_number);

            $ship_date = $this->getShipDateFromFileName($this->file);
            $success = $sellerCloudService->updateShipping(
                $order_id,
                $ship_date,
                $tracking_number,
                'USPS',
                'USPS Priority Mail'
            );

            if (!$success) {
                Log::error("Failed to update order id: {$order_id} and tracking number: {$tracking_number} at {$ship_date}");
            }
        }
    }

    private function getShipDateFromFileName(string $file): string
    {
        preg_match('/(\d{8})/', basename($file), $matches);
        $file_date = isset($matches[1])
            ? Carbon::createFromFormat('Ymd', $matches[1])
            : Carbon::now();

        return Carbon::parse($file_date)->format('Y-m-d\TH:i:s.v\Z');
    }

    private function parseShipmentsFromContent(string $content): array
    {
        $uspPrioShipments = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = explode(';', $line);

            if (count($parts) >= 10 && $parts[8] === 'USP' && $parts[9] === 'PRIO') {
                $uspPrioShipments[] = [
                    'order_id' => $parts[0],
                    'tracking_number' => $parts[3]
                ];
            }
        }

        return $uspPrioShipments;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessEshipFilesJob failed for file: ' . $this->file, [
            'exception' => $exception->getMessage()
        ]);
    }
}
