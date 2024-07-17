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
