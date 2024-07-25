<?php

namespace App\Services;

use stdClass;
use SoapClient;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SeawideService
{

    public $client, $params;

    public function __construct()
    {
        $endpoint = 'https://order.ekeystone.com/wselectronicorder/electronicorder.asmx?wsdl';
        $this->client = new SoapClient($endpoint, ['trace' => 1, 'exceptions' => 1]);
        $this->params = new stdClass();
        $this->params->Key = env('SEAWIDE_KEY');
        $this->params->FullAccountNo = env('SEAWIDE_FULLACCOUNTNO');
    }

    public function GetOrderHistory($po_number)
    {
        $tracking_num = "";
        $date = "";
        $shipping_method = "";
        try {


            $this->params->PONumber = $po_number;
            $this->params->FromDate = '';
            $this->params->ToDate = '';

            $response = $this->client->__soapCall('GetOrderHistory', [$this->params]);
            // Extract the XML from the response
            $xml = $response->GetOrderHistoryResult->any;

            // Load the XML string into a SimpleXMLElement object
            $xmlObject = simplexml_load_string($xml);

            // Decode the JSON to an associative array
            $responseArray = json_decode(json_encode($xmlObject), true);
            $responseArray = $responseArray['NewDataSet'];

            if (isset($responseArray['Table1'])) {
                foreach ($responseArray['Table1'] as $order) {
                    if (isset($order['EKORD_x0023_']) && $order['EKORD_x0023_']  == "NoData" || !isset($order['EKORD_x0023_'])) {
                        return (object)['tracking_num' => $tracking_num, 'date' => $date, 'shipping_method' => $shipping_method];
                    }
                    if (is_string($order['EKTRCK']) && $order['EKTRCK'] != "") {
                        $tracking_num = $order['EKTRCK'];
                        $date = $order['EKDATE'];
                        $shipping_method = $order['EKSVIA'];
                        break;
                    }
                }
            }
            // trim strings
            $tracking_num = trim($tracking_num);
            $date = trim($date);
            $shipping_method = trim($shipping_method);

            return (object)['tracking_num' => $tracking_num, 'date' => $date, 'shipping_method' => $shipping_method];
        } catch (\Exception $e) {
            return (object)['tracking_num' => $tracking_num, 'date' => $date, 'shipping_method' => $shipping_method];
        }
    }

    public function GetShippingOptions($FullPartNo, $zipcode = null)
    {
        $shippingOption = [
            'ServiceLevel' => null,
            'Rate' => null,
        ];
        try {

            $this->params->FullPartNo = $FullPartNo;
            $this->params->ToZip = $zipcode;
            $response = $this->client->__soapCall('GetShippingOptions', [$this->params]);
            // Extract the XML from the response

            $xml = $response->GetShippingOptionsResult->any;

            // Load the XML string into a SimpleXMLElement object
            $xmlObject = simplexml_load_string($xml);

            // Decode the JSON to an associative array
            $responseArray = json_decode(json_encode($xmlObject), true);
            $responseArray = $responseArray['ShippingOptions'];

            // if (isset($responseArray['Rates'])) {
            //     foreach ($responseArray['Rates'] as $option) {
            //         if (isset($option['Rate']) && $option['Rate'] <= $shippingOption['Rate'] || !$shippingOption['Rate']) {
            //             $shippingOption['Rate'] = $option['Rate'];
            //             $shippingOption['ServiceLevel'] = $option['ServiceLevel'];
            //         }
            //     }
            // }
            Log::info('Seawide Shipping Options => ' . \json_encode($responseArray));
            if (!is_array($responseArray)) {
                throw new \Exception('Expected $responseArray to be an array, got: ' . gettype($responseArray));
            }

            if (isset($responseArray['Rates']) && is_array($responseArray['Rates'])) {
                foreach ($responseArray['Rates'] as $option) {
                    if (isset($option['Rate']) && (empty($shippingOption['Rate']) || $option['Rate'] <= $shippingOption['Rate'])) {
                        $shippingOption['Rate'] = $option['Rate'];
                        $shippingOption['ServiceLevel'] = $option['ServiceLevel'];
                    }
                }
            } else {
                // Additional debug information if 'Rates' key is not found or is not an array
                throw new \Exception('Expected $responseArray["Rates"] to be an array, got: ' . gettype($responseArray['Rates']));
            }

            return (object)$shippingOption;
        } catch (\Exception $e) {
            return (object)$shippingOption;
        }
    }

    public function ShipOrderDropShip(
        $FullPartNo,
        $Quant,
        $DropShipFirstName,
        $DropShipLastName,
        $DropShipCompany,
        $DropShipAddress1,
        $DropShipAddress2,
        $DropShipCity,
        $DropShipState,
        $DropShipPostalCode,
        $DropShipPhone,
        $PONumber,
    ) {

        Log::info('Seawide Processing order => ' . $FullPartNo);
        $DropShipCountry = "US";
        $DropShipEmail = "info@thesuppliesnmore.com";
        try {
            $zipcode = null;
            if (strlen($DropShipPostalCode) >= 5) {
                $zipcode =  substr($DropShipPostalCode, 0, 5);
            }
            $shippingOptions = $this->GetShippingOptions($FullPartNo,$zipcode);

            $this->params->FullPartNo = $FullPartNo;
            $this->params->Quant = $Quant;
            $this->params->DropShipFirstName = $DropShipFirstName;
            $this->params->DropShipLastName = $DropShipLastName;
            $this->params->DropShipCompany = $DropShipCompany;
            $this->params->DropShipAddress1 = $DropShipAddress1;
            $this->params->DropShipAddress2 = $DropShipAddress2;
            $this->params->DropShipCity = $DropShipCity;
            $this->params->DropShipState = $DropShipState;
            $this->params->DropShipPostalCode = $DropShipPostalCode;
            $this->params->DropShipPhone = $DropShipPhone;
            $this->params->DropShipEmail = $DropShipEmail;
            $this->params->DropShipCountry = $DropShipCountry;
            $this->params->PONumber = $PONumber;
            $this->params->ServiceLevel =  $shippingOptions->ServiceLevel;

            $response = $this->client->__soapCall('ShipOrderDropShip', [$this->params]);

            Log::info('Seawide Response => ' . \json_encode($response));
            // Extract the XML from the response

            $result = $response->ShipOrderDropShipResult;
            if(!Str::contains($result, 'OK')){
                $sellerCloudService = new SellerCloudService();
                $sellerCloudService->sendEmail(null, ['heading' => 'Error on Seawide', 'body' => 'Error on Seawide Order ID is => ' . $PONumber . ' '.json_encode($result), 'title' => 'Seawide error']);

            }
            return Str::contains($result, 'OK');
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            $sellerCloudService = new SellerCloudService();
            $sellerCloudService->sendEmail(null, ['heading' => 'Error on Seawide', 'body' => 'Error on Seawide Order ID is => ' . $PONumber . ' '.$e->getMessage(), 'title' => 'Seawide error']);

            return false;
        }
    }
}
