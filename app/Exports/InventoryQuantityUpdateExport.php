<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class InventoryQuantityUpdateExport implements FromArray, WithHeadings
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        \Log::info('Export array content', ['data' => $this->data]);

        return array_map(function ($item) {
            // Ensure all values are plain data (not arrays)
            return [
                'VCPN' => $item['VCPN'] ?? '',
                'VenCode' => $item['VenCode'] ?? '',
                'PartNumber' => $item['PartNumber'] ?? '',
                "TotalQty" => $item['TotalQty'] ?? '',
                "SOInv" => \json_encode($item['SOInv']) ?? '',
                "MinToSell" => $item['MinToSell'] ?? '',
                "ShippingFlag" => $item['ShippingFlag'] ?? '',
                "CoreCharge" => $item['CoreCharge'] ?? '',
            ];
        }, $this->data);
    }

    public function headings(): array
    {
        return [
            'VCPN',
            'VenCode',
            'PartNumber',
            'TotalQty',
            'SOInv',
            'MinToSell',
            'ShippingFlag',
            'CoreCharge',
        ];
    }
}
