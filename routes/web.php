<?php

use App\Http\Controllers\EmailController;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
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
    // $DropShipPostalCode = '32714';
    // $zipcode = null;
    // if (strlen($DropShipPostalCode) >= 5) {
    //     $zipcode =  substr($DropShipPostalCode, 0, 5);
    // }
    // $seawideService = new \App\Services\SeawideService();
    // dd($seawideService->GetShippingOptions('W6851394'));
});

Route::get('fetch-products', function () {
    // run job
    \App\Jobs\GetVendorProducts::dispatch();
});

Route::get('/', function () {
    return view('welcome');
});
