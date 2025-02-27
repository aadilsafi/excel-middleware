<?php

namespace App\Services;

use Exception;
use stdClass;
use SoapClient;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use PhpParser\Node\Expr\Throw_;

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
            Log::info('GetOrderHistory Error => ' . $e->getMessage());
            return (object)['tracking_num' => $tracking_num, 'date' => $date, 'shipping_method' => $shipping_method];
        }
    }

    public function GetShippingOptions($FullPartNo, $zipcode = null,$quantity = 1)
    {
        $shippingOption = [
            'ServiceLevel' => null,
            'Rate' => null,
        ];
        try {

            $this->params->FullPartNo = $FullPartNo;
            $this->params->ToZip = $zipcode;
            $this->params->Quantity = $quantity;

            $response = $this->client->__soapCall('GetShippingOptionsWithQuantity', [$this->params]);
            // Extract the XML from the response

            $xml = $response->GetShippingOptionsWithQuantityResult->any;

            // Load the XML string into a SimpleXMLElement object
            $xmlObject = simplexml_load_string($xml);

            // Decode the JSON to an associative array
            $responseArray = json_decode(json_encode($xmlObject), true);
            $responseArray = $responseArray['ShippingOptions'];

            Log::info('shipping options from get => '.\json_encode($responseArray));
            if (isset($responseArray['Rates'])) {
                $rates = $responseArray['Rates'];
                if (isset($rates[0]) && is_array($rates[0])) {
                    // Case 1: Rates is an array of rate objects
                    foreach ($responseArray['Rates'] as $option) {
                        if(isset($option['Rate']) &&  $option['ServiceLevel'] == 'U11'){
                            $shippingOption['Rate'] = $option['Rate'];
                            $shippingOption['ServiceLevel'] = $option['ServiceLevel'];
                            break;
                        }
                        if (isset($option['Rate']) && $option['Rate'] <= $shippingOption['Rate'] || !$shippingOption['Rate']) {
                            $shippingOption['Rate'] = $option['Rate'];
                            $shippingOption['ServiceLevel'] = $option['ServiceLevel'];
                        }
                    }
                } else {
                    $shippingOption['Rate'] = $rates['Rate'];
                    $shippingOption['ServiceLevel'] = $rates['ServiceLevel'];
                }
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
            $shippingOptions = $this->GetShippingOptions($FullPartNo, $zipcode,$Quant);

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
            if (!Str::contains($result, 'OK')) {
                $sellerCloudService = new SellerCloudService();
                $sellerCloudService->sendEmail(null, ['heading' => 'Error on Seawide', 'body' => 'Error on Seawide Order ID is => ' . $PONumber . ' ' . json_encode($result), 'title' => 'Seawide error']);
                return false;
            }
            return Str::contains($result, 'OK');
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            $sellerCloudService = new SellerCloudService();
            $sellerCloudService->sendEmail(null, ['heading' => 'Error on Seawide', 'body' => 'Error on Seawide Order ID is => ' . $PONumber . ' ' . $e->getMessage(), 'title' => 'Seawide error']);

            return false;
        }
    }

    public function ShipOrderDropShipMultiparts(
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
        $partNumberQuantity,
        $partNumberQuantityShipping,
        $order = null
    ) {
        Log::info('multi part start');
        Log::info('Seawide Processing order => ' . $FullPartNo);
        $DropShipCountry = "US";
        $DropShipEmail = "info@thesuppliesnmore.com";
        try {
            $zipcode = null;
            if (strlen($DropShipPostalCode) >= 5) {
                $zipcode =  substr($DropShipPostalCode, 0, 5);
            }
            $shippingOptions = $this->GetShippingOptionsMultipleParts($zipcode,$partNumberQuantityShipping,$order);
            if(!isset($shippingOptions->ServiceLevel)){
                // throw exception for unable to find shipping option for the order
                throw new Exception('Unable To find shipping option',406);
            }
            Log::info(\json_encode('shipping options multipart drop ship => '.$shippingOptions->ServiceLevel));
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
            $this->params->PartNumberQuantity = $partNumberQuantity;

            $this->params->OrderProcessMethod = 1; // 1 = complete order 0 = No parts are ordered; this assures the user that the order can be fulfilled by Keystone.


            Log::info('multipart params => '.\json_encode($this->params));
            $response = $this->client->__soapCall('ShipOrderDropShipMultipleParts', [$this->params]);
            $anyXml = $response->ShipOrderDropShipMultiplePartsResult->any;

            $xml = simplexml_load_string($anyXml);

            // Register namespaces to use XPath
            $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');
            $xml->registerXPathNamespace('msdata', 'urn:schemas-microsoft-com:xml-msdata');

            // Extract the status
            $statusNodes = $xml->xpath('//Status');
            $result = null;

            foreach ($statusNodes as $node) {
                if (isset($node->Status)) {
                    $result = (string) $node->Status;
                    break; // Assuming we need only the first occurrence
                }
            }

            // Extract the XML from the response
            if (!Str::contains($result, 'OK')) {
                Log::info($statusNodes);
                $sellerCloudService = new SellerCloudService();
                $sellerCloudService->sendEmail(null, ['heading' => 'Error on Seawide', 'body' => 'Error on Seawide Order ID is => ' . $PONumber . ' ' . json_encode($result), 'title' => 'Seawide error']);
                return false;
            }
            Log::info('multi part end');

            return Str::contains($result, 'OK');
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            $sellerCloudService = new SellerCloudService();
            $sellerCloudService->sendEmail(null, ['heading' => 'Error on Seawide', 'body' => 'Error on Seawide Order ID is => ' . $PONumber . ' ' . $e->getMessage(), 'title' => 'Seawide error']);
            Log::info('multi part end');
            if($e->getCode() == 406){
                return 'No Shipping Option';
            }
            return false;
        }
    }

    public function GetShippingOptionsMultipleParts($zipcode,$partNumberQuantityShipping,$order = null)
    {
        $shippingOption = [
            'ServiceLevel' => null,
            'Rate' => null,
        ];
        try {

            // $this->params->FullPartNo = $FullPartNo;
            $this->params->ToZip = $zipcode;
            $this->params->PartNumbersQty = $partNumberQuantityShipping;
            $response = $this->client->__soapCall('GetShippingOptionsMultipleParts', [$this->params]);
            // Extract the XML from the response

            $xml = $response->GetShippingOptionsMultiplePartsResult->any;

            // Load the XML string into a SimpleXMLElement object
            $xmlObject = simplexml_load_string($xml);

            // Decode the JSON to an associative array
            $responseArray = json_decode(json_encode($xmlObject), true);
            $responseArray = $responseArray['ShippingOptions'];

            Log::info('shipping options from get => '.\json_encode($responseArray));
            $shipping_method = $order['shipping_method_seawide']; // FreeEconomy | NextDay | Expedited
            $shipping_override = $order['shipping_override_seawide'];

                // Define allowed service levels for each shipping method
            $allowed_service_levels = [
                'FreeEconomy' => ['U11', 'U09', 'U02', 'U15', 'U19', 'U52', 'U03', 'U13', 'U55', 'U53'],
                'Standard' => ['U11', 'U09', 'U02', 'U15', 'U19', 'U52', 'U03', 'U13', 'U55', 'U53'],
                'Expedited' => ['U09', 'U02', 'U15', 'U19', 'U52', 'U03', 'U13', 'U55', 'U53'],
                'SecondDay' => ['U02', 'U15', 'U52', 'U03', 'U13', 'U55', 'U53'],
                'NextDay' => ['U03', 'U13', 'U55', 'U53']
            ];

            if (isset($responseArray['Rates'])) {
                $rates = $responseArray['Rates'];
                $shippingOption = ['Rate' => null, 'ServiceLevel' => null];


                // If shipping_override is set, use it directly
                if (!empty($shipping_override)) {
                    if (isset($rates[0]) && is_array($rates[0])) {
                        foreach ($rates as $option) {
                            if ($option['ServiceLevel'] === $shipping_override) {
                                $shippingOption['Rate'] = $option['Rate'];
                                $shippingOption['ServiceLevel'] = $option['ServiceLevel'];
                                break;
                            }
                        }
                    } elseif ($rates['ServiceLevel'] === $shipping_override) {
                        $shippingOption['Rate'] = $rates['Rate'];
                        $shippingOption['ServiceLevel'] = $rates['ServiceLevel'];
                    }
                }
                // If no shipping_override, find cheapest rate among allowed service levels
                else {
                    $allowed_levels = $allowed_service_levels[$shipping_method] ?? [];

                    if (isset($rates[0]) && is_array($rates[0])) {
                        foreach ($rates as $option) {
                            if (in_array($option['ServiceLevel'], $allowed_levels) &&
                                ($shippingOption['Rate'] === null || $option['Rate'] < $shippingOption['Rate']))
                            {
                                $shippingOption['Rate'] = $option['Rate'];
                                $shippingOption['ServiceLevel'] = $option['ServiceLevel'];
                            }
                        }
                    } elseif (in_array($rates['ServiceLevel'], $allowed_levels)) {
                        $shippingOption['Rate'] = $rates['Rate'];
                        $shippingOption['ServiceLevel'] = $rates['ServiceLevel'];
                    }
                }
            }
            return (object)$shippingOption;
        } catch (Exception $e) {
            return (object)$shippingOption;
        }
    }

    public function GetShippingOptionsAll($FullPartNo, $zipcode = null,$quantity = 1)
    {
        $shippingOption = [
            'ServiceLevel' => null,
            'Rate' => null,
        ];
        try {

            $this->params->FullPartNo = $FullPartNo;
            $this->params->ToZip = $zipcode;
            $this->params->Quantity = $quantity;

            $response = $this->client->__soapCall('GetShippingOptionsWithQuantity', [$this->params]);
            // Extract the XML from the response

            $xml = $response->GetShippingOptionsWithQuantityResult->any;

            // Load the XML string into a SimpleXMLElement object
            $xmlObject = simplexml_load_string($xml);

            // Decode the JSON to an associative array
            $responseArray = json_decode(json_encode($xmlObject), true);
            $responseArray = $responseArray['ShippingOptions'];


            return (object)$responseArray;
        } catch (\Exception $e) {
            return (object)$shippingOption;
        }
    }
}
