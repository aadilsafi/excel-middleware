<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;

class ProductsShippingRates implements FromArray
{
    protected $data;

    /**
     * Constructor to pass data into the export.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Prepare the array of data to be exported.
     *
     * @return array
     */
    public function array(): array
    {
        return $this->data;
    }
}
