<?php

use App\Http\Controllers\EmailController;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;
use SoapClient as SoapClient;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('test', function () {
$items = '[{\"ID\": 2221970, \"OrderID\": 6947447, \"ProductID\": \"705442007210\", \"Qty\": 1, \"DisplayName\": \"Cold Steel Trail Boss Axe  27 Inch\", \"AdjustedSitePrice\": 41.16, \"QtyReturned\": 0, \"QtyShipped\": 0, \"ID1\": 2221970, \"OrderID1\": 6947447, \"ProductID1\": \"705442007210\", \"Qty1\": 1, \"DisplayName1\": \"Cold Steel Trail Boss Axe  27 Inch\", \"OriginalBasePrice\": 0, \"SitePrice\": 49.6125, \"AdjustedSitePrice1\": 41.16, \"SiteCost\": 29.69, \"TaxExempt\": 0, \"TaxClass\": -1, \"NonShipping\": 0, \"ShipSeparately\": 0, \"DropShipMode\": 1, \"DropShipAddressID\": -1, \"LineTotal\": 41.16, \"LineTaxTotal\": 2.88, \"StatusCode\": 1, \"Weight\": 2, \"Length\": 28.5, \"Width\": 7, \"Height\": 1.75, \"MinimumQty\": 1, \"DisplayDescription\": \"Cold Steel Trail Boss Axe  27 Inch\", \"ImageURL\": \"\", \"ExtraInformation\": \"<OrderItemExtraInformation xmlns=\"http:\/\/www.bvsoftware.com\/schemas\/2003\/bvc3\/OrderItemExtraInformation.xsd\" \/>\", \"GiftWrap\": 0, \"GiftWrapMessage\": \"\", \"GiftWrapAllowed\": 0, \"InventoryKey\": \"\", \"ShippingCost\": 0, \"QtyShipped1\": 0, \"QtyReturned1\": 0, \"ParentID\": 0, \"ReferenceID\": \"104899173432161\", \"eBayTransactionID\": \"\", \"OriginalOrderSourceID\": \"\", \"PostingFee\": 0, \"FinalValueFee\": 6.17, \"IsBackOrder\": False, \"BackOrderAction\": 0, \"BackOrderActionDate\": \"0001-01-01T00:00:00\", \"BackOrderActionBy\": 0, \"EstimatedTimeArrival\": \"0001-01-01T00:00:00\", \"ReplacementSKU\": \"\", \"ListingError\": 0, \"FeedBackID\": \"\", \"FeedBackReminderSent\": \"0001-01-01T00:00:00\", \"BackOrderQty\": 0, \"BackOrderDate\": \"0001-01-01T00:00:00\", \"BackOrderBy\": 0, \"EstimatedShipDate\": \"0001-01-01T00:00:00\", \"NotifyCustomerService\": False, \"NotifyCustomer\": \"0\", \"DisputeID\": \"\", \"OriginalSKU\": \"\", \"IsSKUReplaced\": False, \"InsuranceCost\": 0, \"FeedbackLeft\": False, \"FeedbackLeftOn\": \"0001-01-01T00:00:00\", \"FeedBackLeftID\": \"\", \"IsDropShipped\": False, \"DropShippedOn\": \"0001-01-01T00:00:00\", \"DropShippedToVendor\": 0, \"Notes\": \"\", \"ProductIDOriginal\": \"705442007210\", \"SalesRepId\": 0, \"ShipFromWareHouseID\": 252, \"ShipFromWarehouseName\": \"Default Warehouse\", \"FeedBackFailureCount\": 0, \"FeedBackLastFailureOn\": \"0001-01-01T00:00:00\", \"FeedBackFailureMessage\": \"\", \"SourceOrderFileName\": \"\", \"eBayItemIDUnique\": \"104899173432161\", \"eBayTransactionIDUnique\": \"\", \"DontCountInventory\": False, \"ShippingTax\": 0, \"GiftWrapTax\": 0, \"DropShippedVendorOrderID\": \"\", \"GiftWrapType\": \"\", \"GiftWrapCharges\": 0, \"ShippingCostForAccounting\": 0, \"ReturnedToWarehouseID\": 252, \"AverageCost\": 0, \"LastCost\": 0, \"CreditMemoID\": 0, \"CreditMemoItemID\": 0, \"HasBuyDotCoupon\": False, \"BuyDotCouponAmount\": 0, \"DiscountType\": 0, \"DiscountAmount\": 0, \"DiscountTotal\": 0, \"QtyRequestedByOrderSource\": 1, \"ProductRebateID\": 0, \"ProductRebateValue\": 0, \"Purchaser\": 0, \"ShippingSourceOrderItemID\": \"\", \"SalesOutlet\": \"\", \"VariantID\": 0, \"ShippingSourceWarehouseID\": \"\", \"ExportedProductID\": \"\", \"ExportedDocumentNumber\": \"\", \"BatchExportedGUID\": \"\", \"DeliveryDocumentNumber\": \"\", \"BatchExportDeliveryGUID\": \"\", \"SrcUpdatedForItemShipping\": False, \"SrcUpdatedForItemShippingOn\": \"0001-01-01T00:00:00\", \"ShipType\": \"\", \"DropShippedStatus\": 0, \"BatchExportedJobID\": 0, \"BatchExportedDateTime\": \"0001-01-01T00:00:00\", \"ItemStatusCode\": 2, \"ItemPaymentStatus\": 10, \"ItemShippingStatus\": 1, \"BatchExportDeliveryJobID\": 0, \"BatchExportDeliveryDateTime\": \"0001-01-01T00:00:00\", \"CancellationRequestSentToInnotrac\": False, \"ShippingSourceCancellationQty\": 0, \"TotalRefunded\": 0, \"ReturnDocumentNumber\": \"\", \"BatchExportReturnGUID\": None, \"BatchExportReturnJobID\": 0, \"BatchExportReturnDateTime\": \"0001-01-01T00:00:00\", \"ProfitAndLossAdjustmentTotal\": 0, \"SalesRecordNumber\": \"\", \"ShippingSourceOrderItemSKU\": \"\", \"QtyPerCase\": 1, \"TotalCases\": 1, \"PricePerCase\": 41.16, \"QtyPicked\": 0, \"ProductIDRequested\": \"705442007210\", \"WholesaleConfirmedQty\": 0, \"WholesaleConfirmShipDate\": \"0001-01-01T00:00:00\", \"WholesaleBackOrderQty\": 0, \"WholesaleBackOrderShipDate\": \"0001-01-01T00:00:00\", \"WholesaleRefuseQty\": 0, \"KitItemsCount\": 0, \"VatRate\": 0, \"VATTotal\": 0, \"AmazonShipmentID\": \"\", \"WarehouseBinCartSlotID\": 0, \"SettlementID\": 0, \"RoundNumber\": 0, \"WarehouseBinCartID\": 0, \"MainItemID\": None, \"LinkedToPOItemID\": 0, \"OrderItemShipDate\": \"0001-01-01T00:00:00\", \"ProductName\": \"CS TRAIL BOSS 27\"\" 1055 CARBON STEEL\", \"InventoryAvailableQty\": 140, \"LocationNotes\": \"\", \"ShadowOf\": \"\", \"DefaultVendorName\": \"RSR\", \"BundleItems\": []}]';

// Step 1: Replace escaped quotes
$items = str_replace("\\\"", "\"", $items);

// Step 2: Fix the double quotes in the ProductName field
$items = preg_replace('/"ProductName":\s*"([^"]*)""([^"]*)"/', '"ProductName": "\1\"\2"', $items);

// Step 3: Remove the ExtraInformation field
$items = preg_replace('/"ExtraInformation":\s*".*?",/', '', $items);

// Step 4: Replace None with null
$items = str_replace('None', 'null', $items);

// Step 5: Replace False with false (for boolean values)
$items = str_replace('False', 'false', $items);

// Step 6: Remove empty quotes and replace them with null
$items = preg_replace('/:\s*,/', ': null,', $items);
$items = preg_replace('/:\s*}$/', ': null}', $items);

// Log the final JSON string before decoding
Log::info('items final before decoding: ' . $items);

// Step 7: Decode JSON string to handle nested escaping
$items = json_decode($items, true);

if (json_last_error() === JSON_ERROR_NONE) {
    Log::info('Successfully decoded JSON');
} else {
    Log::error('Failed to decode JSON: ' . json_last_error_msg());
}

// Step 8: Re-encode the JSON to ensure proper formatting
$items = json_encode($items);

Log::info('items final after re-encoding: ' . $items);

dd(json_decode($items,true));
dd(dispatch(new \App\Jobs\ProcessAllSeaWideOrdersJob()));
    $seawideService = new \App\Services\SeawideService();
    $FullPartNo = "YAK060015";
    $Quant = "1";
    $DropShipFirstName = "JungSun Bong";
    $DropShipLastName = "";
    $DropShipCompany = "";
    $DropShipAddress1 = "1722 OTTS CHAPEL RD GA104076";
    $DropShipAddress2 = "";
    $DropShipCity = "NEWARK";
    $DropShipState = "DE";
    $DropShipPostalCode = "19702";
    $DropShipPhone = "3474483190";
    $PONumber = "6946531";
    $AdditionalInfo = "";


    // $data = $seawideService->ShipOrderDropShip(
    //     $FullPartNo,
    //     $Quant,
    //     $DropShipFirstName,
    //     $DropShipLastName,
    //     $DropShipCompany,
    //     $DropShipAddress1,
    //     $DropShipAddress2,
    //     $DropShipCity,
    //     $DropShipState,
    //     $DropShipPostalCode,
    //     $DropShipPhone,
    //     $PONumber,
    //     $AdditionalInfo,
    // );
    dd($data);
});

Route::get('fetch-products', function () {
    // run job
    \App\Jobs\GetVendorProducts::dispatch();
});

Route::get('/', function () {
    return view('welcome');
});
