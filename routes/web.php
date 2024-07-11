<?php

use App\Http\Controllers\EmailController;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

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
Route::get('test-emails', [EmailController::class,'readEmails'])->name('read-emails');
Route::get('test', function () {
    $sellerCloudService = new \App\Services\SellerCloudService();
    $ship_date = Carbon::now()->format('Y-m-d\TH:i:s.v\Z');
    $tracking_number = '403299015666';
    dd($sellerCloudService->updateShipping('6939500', $ship_date, $tracking_number, 'FedEx', 'FedEx Home Delivery'));

});

Route::get('fetch-products', function () {
    // run job
    \App\Jobs\GetVendorProducts::dispatch();
});

Route::get('/', function () {
    return view('welcome');
});
