<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;


class OrdersImport implements ToCollection, WithHeadingRow
{
    /**
     * @param Collection $collection
     */
    public function collection(Collection $rows)
    {
        $name = '';
        $date = '';
        $quantity = '';
        $product = '';
        foreach ($rows as $product) {
            \App\Models\Product::updateOrCreate(
                ['ProductSKU' => $product['ProductSKU']],
                [
                    'VendorID' => $product['VendorID'],
                    'Price' => $product['Price'],
                    'VendorSKU' => $product['VendorSKU'],
                    'IsAvailable' => $product['IsAvailable'],
                    'DateModified' => $product['DateModified'],
                    'Notes' => $product['Notes'],
                    'PricePerCase' => $product['PricePerCase'],
                    'ProductName' => $product['ProductName'],
                    'QtyPerCase' => $product['QtyPerCase'],
                    'Qty' => $product['Qty'],
                ]
            );
        }
    }
}
