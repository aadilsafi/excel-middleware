<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateTrackingKinseyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public $orders = [];
    public function __construct($orders)
    {
        $this->orders = $orders;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $orders = $this->orders;
        $kinseyService = new \App\Services\KinseyService();
        $sellerCloudService = new \App\Services\SellerCloudService();
        foreach ($orders as $order) {
            $shipments = $kinseyService->getShipments($order->order_id);
            if ($shipments) {
                foreach ($shipments['packages'] as $shipment) {
                    $file_date = Carbon::now();
                    $ship_date = Carbon::parse($file_date)->format('Y-m-d\TH:i:s.v\Z');
                    $tracking = $shipment['trackingNo'] ?? '';
                    $carrier = $shipment['carrierCode'] ?? '';
                    $service = $shipment['serviceCode'] ?? '';
                    $serviceDescription = $this->getShippingServiceDescription($service, $carrier);
                    if ($tracking && $serviceDescription) {
                        $resp = $sellerCloudService->updateShipping($order->order_id, $ship_date, $tracking, $carrier, $serviceDescription, 260);
                        if($resp){
                            $order->delete();
                        }
                    }
                }
            }
        }
    }

    private function getShippingServiceDescription($serviceCode, $carrier)
    {
        $services = [
            'GROUND-ADVANTAGE' => 'USPS Ground Advantage (under 2 lbs)',
            'EXPRESS-PARCEL' => 'USPS Priority Mail Express Parcel',
            'FIRST-PARCEL' => 'USPS First Class Mail Parcel',
            'PRIORITY-PARCEL' => 'USPS Priority Mail Parcel',
            '2_DAY_AM' => 'FedEx 2 Day Delivery by 10:30AM',
            '2_DAY' => 'FedEx 2 Day Delivery',
            '1_DAY_FREIGHT' => 'FedEx 1 Day Freight (Palletized)',
            '2_DAY_FREIGHT' => 'FedEx 2 Day Freight (Palletized)',
            '3_DAY_FREIGHT' => 'FedEx 3 Day Freight (Palletized)',
            'EXPRESS_SAVER' => 'FedEx Express Saver 3 Day',
            'FIRST_OVERNIGHT' => 'FedEx First Overnight',
            'FIRST_FREIGHT' => 'FedEx First Overnight Freight',
            'FREIGHT_ECONOMY' => 'FedEx Freight Economy',
            'FREIGHT_PRIORITY' => 'FedEx Freight Priority',
            'GROUND_HOME_DELIVERY' => 'FedEx Ground Home Delivery ($4 Surcharge)',
            'PRIORITY_OVERNIGHT' => 'FedEx Priority Overnight',
            'SMART_POST' => 'FedEx Smart Post',
            'STANDARD_OVERNIGHT' => 'FedEx Standard Overnight',
            'INTERNATIONAL_PRIORITY_FREIGHT' => 'FedEx International Priority Freight',
            'INTERNATIONAL_ECONOMY' => 'FedEx International Economy',
            'INTERNATIONAL_ECONOMY_FREIGHT' => 'FedEx International Economy Freight',
            'INTERNATIONAL_FIRST' => 'FedEx International First Class',
            'INTERNATIONAL_PRIORITY' => 'FedEx International Priority',
            'INTERNATIONAL_GROUND' => 'FedEx International Ground',
            'EXPRESS' => 'UPS Express',
            'EXPRESS_PLUS' => 'UPS Express Plus',
            '2ND_DAY_AIR_A.M.' => 'UPS 2nd Day Air A.M.',
            '2ND_DAY_AIR' => 'UPS 2nd Day Air',
            'NEXT_DAY_AIR_SAVER' => 'UPS Next Day Air Saver',
            'NEXT_DAY_AIR' => 'UPS Next Day Air',
            'NEXT_DAY_AIR_EARLY' => 'UPS Next Day Air Early',
            '3_DAY_SELECT' => 'UPS 3 Day Select Air',
            'GFP' => 'UPS Ground with Freight Pricing',
            '2ND_DAY_AIR_PR' => 'UPS 2nd Day Air Puerto Rico',
            'SAVER_CANADA' => 'UPS Canadian Economy',
            'STANDARD' => 'UPS Standard Canada',
            'EXPEDITED_CANADA' => 'UPS Expedited Canada',
            'NEXT_DAY_AIR_PR' => 'UPS Next Day Air Puerto Rico',
            'GROUND_PR' => 'UPS Ground Puerto Rico',
            'GROUND' => \strtolower($carrier) == 'fedex' ? 'FedEx Ground' : 'UPS Ground',
        ];

        $description  = $services[$serviceCode] ?? null;
        if (!$description) {
            // instead of the if else use switch
            switch($carrier){
                case 'USPS':
                    $description = 'USPS Priority Mail';
                    break;
                case 'UPS':
                    $description = 'UPS Ground';
                    break;
                case 'FedEx':
                    $description = 'FedEx Home Delivery';
                    break;
            }
        }
        return $description;
    }
}
