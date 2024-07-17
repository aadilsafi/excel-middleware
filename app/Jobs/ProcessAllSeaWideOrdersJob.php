<?php

namespace App\Jobs;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        $all_orders = Order::where('vendor_id', 15080)->get();
        $seawideService = new \App\Services\SeawideService();
        $sellercloudService = new \App\Services\SellerCloudService();
        foreach ($all_orders as $order) {
            $data = $seawideService->GetOrderHistory($order->order_id);
            if ($data->tracking_num) {
                $file_date = Carbon::createFromFormat('Ymd', $data->date);
                $ship_date = Carbon::parse($file_date)->format('Y-m-d\TH:i:s.v\Z');
                $shipping_method = $this->getShippingDetails($data->shipping_method);
                $sellercloudService->updateShipping($order->order_id, $ship_date, $data->tracking_num, $shipping_method->carrier_name, $shipping_method->shipping_method, 258);
                // Delete order here
                $order->delete();
            }
        }
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
        }
        else if ($code == 3) {
            $shipping = [
                "carrier_name" => "UPS",
                "shipping_method" => "UPS Ground",
            ];
        }

        return (Object)$shipping;
    }
}
