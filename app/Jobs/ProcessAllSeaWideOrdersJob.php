<?php

namespace App\Jobs;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAllSeaWideOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public $timeout = null;
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('seawide processing orders command');
        $all_orders = Order::where('vendor_id', 15080)->get();
        $seawideService = new \App\Services\SeawideService();
        $sellercloudService = new \App\Services\SellerCloudService();
        foreach ($all_orders as $order) {
            Log::info('Processing order id: ' . $order->order_id);
            $data = $seawideService->GetOrderHistory($order->order_id);
            Log::info('Order data: ' . json_encode($data));
            if ($data->tracking_num) {
                // $file_date = Carbon::createFromFormat('Ymd', $data->date)->addDay();
                $file_date = Carbon::now();
                $ship_date = Carbon::parse($file_date)->format('Y-m-d\TH:i:s.v\Z');
                $shipping_method = $this->getShippingDetails($data->shipping_method);
                $res = $sellercloudService->updateShipping($order->order_id, $ship_date, $data->tracking_num, $shipping_method->carrier_name, $shipping_method->shipping_method, 258);
                // Delete order here
                if (!$res) {
                    Log::error('Failed to update order id: ' . $order->order_id . ' and tracking number: ' . $data->tracking_num . ' at ' . $ship_date);
                    continue;
                }
                $order->delete();
            }
        }
        Log::info('seawide processing orders command end');
    }

    public function getShippingDetails($code)
    {
        $shipping = [
            "carrier_name" => "FedEx",
            "shipping_method" => "FedEx 2Day",
        ];
        if ($code == 8) {
            $shipping = [
                "carrier_name" => "USPS",
                "shipping_method" => "USPS Priority Mail",
            ];
        } else if ($code == 3) {
            $shipping = [
                "carrier_name" => "UPS",
                "shipping_method" => "UPS Ground",
            ];
        }

        return (object)$shipping;
    }
}
