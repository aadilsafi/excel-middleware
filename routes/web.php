<?php

use App\Http\Controllers\EmailController;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use SoapClient as SoapClient;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
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
Route::get('test-import', function () {
    $seawideService = new \App\Services\SeawideService();
    $res = $seawideService->GetShippingOptionsAll('A141009904','04619')->Rates;
    $res = collect($res);
    return response()->json($res->pluck('Rate','ServiceLevel'));
    dd($res->pluck('Rate','ServiceLevel'));
    $rates = [];
    foreach($res as $r){
        $rates[] = $r['ServiceLevel'];
    }
    return response()->json($rates);

    dd('die');
    $sellercloudService = new \App\Services\SellerCloudService();
    // get file from storage
    $file = Storage::disk('local')->path('ex/test.csv');
    $file_content = base64_encode(file_get_contents($file));

    // convert file
    dd($sellercloudService->ImportProducts($file_content));
});
// Route::get('test', function () {
//     // $spreadsheet = IOFactory::load(public_path('test.txt'));
//     $filePath = public_path('test.txt'); // Replace with your file's actual path
//     $spreadsheet = new Spreadsheet();
//     $worksheet = $spreadsheet->getActiveSheet();

//     // Step 2: Open the file and read it
//     if (($fileHandle = fopen($filePath, 'r')) !== false) {
//         $rowNumber = 1; // Start at the first row in the spreadsheet

//         // Step 3: Process each line of the text file
//         while (($line = fgets($fileHandle)) !== false) {
//             // Step 4: Split the line by TAB first, then semicolon
//             $row = explode("\t", $line); // First split by TAB

//             $columnLetter = 'A'; // Start at column A
//             foreach ($row as $cell) {
//                 // Further split each cell by semicolon
//                 $splitValues = explode(';', $cell);

//                 foreach ($splitValues as $value) {
//                     $value = trim($value); // Trim whitespace

//                     // Apply padding if we're in column B
//                     if ($columnLetter === 'B') {
//                         // convert value to string
//                         $value = str_pad($value, 12, '0', STR_PAD_LEFT);
//                         $worksheet->getStyle('B' . $rowNumber)
//                         ->getNumberFormat()
//                         ->setFormatCode('000000000000');
//                         $worksheet->setCellValueExplicit($columnLetter . $rowNumber, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

//                     }
//                     else{
//                         $worksheet->setCellValue($columnLetter . $rowNumber, $value);
//                     }

//                     $columnLetter++;
//                 }
//             }
//             $rowNumber++; // Move to the next row
//         }
//         fclose($fileHandle); // Close the file after reading
//     } else {
//         return "Unable to open the file!";
//     }

//     // Step 5: Define the file path for the output CSV file
//     $fileName = 'converted_file_' . time() . '.csv';
//     $outputPath = storage_path('app/public/' . $fileName);

//     // Step 6: Save the file as a CSV
//     $writer = new Csv($spreadsheet);
//     $writer->setDelimiter(','); // Use comma as the delimiter for the CSV
//     $writer->setEnclosure('"'); // Enclose fields in quotes if necessary
//     $writer->setLineEnding("\r\n"); // Use Windows-style line endings
//     $writer->save($outputPath);

//     // Step 7: Return the CSV file for download
//     return response()->download($outputPath)->deleteFileAfterSend(true);
// });

Route::get('fetch-products', function () {
    // run job
    \App\Jobs\GetVendorProducts::dispatch();
});

Route::get('/', function () {
    return view('welcome');
});
