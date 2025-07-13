<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class InventoryUpdateExport implements FromArray, WithHeadings
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        return array_map(function ($item) {
            // Ensure all values are plain data (not arrays)
            return [
                'VCPN' => $item['VCPN'] ?? '',
                'VendorCode' => $item['VendorCode'] ?? '',
                'PartNumber' => $item['PartNumber'] ?? '',
                'LongDescription' => $item['LongDescription'] ?? '',
                'CatalogBrandCode' => $item['CatalogBrandCode'] ?? '',
                'ManufacturerProductNo' => $item['ManufacturerProductNo'] ?? '',
                'UPCCode' => $item['UPCCode'] ?? '',
                'ExeterQty' => $item['ExeterQty'] ?? 0,
                'MidWestQty' => $item['MidWestQty'] ?? 0,
                'CaliforniaQty' => $item['CaliforniaQty'] ?? 0,
                'SouthEastQty' => $item['SouthEastQty'] ?? 0,
                'TexasQty' => $item['TexasQty'] ?? 0,
                'TexasDFWQty' => $item['TexasDFWQty'] ?? 0,
                'GreatLakesQty' => $item['GreatLakesQty'] ?? 0,
                'PacificNorthwestQty' => $item['PacificNorthwestQty'] ?? 0,
                'FloridaQty' => $item['FloridaQty'] ?? 0,
                'MinToSell' => $item['MinToSell'] ?? '',
                'ShippingFlag' => $item['ShippingFlag'] ?? '',
                'CoreCharge' => $item['CoreCharge'] ?? '',
                'FET' => $item['FET'] ?? '',
                'BlockedPart' => $item['BlockedPart'] ?? '',
                'CalifBlocked' => $item['CalifBlocked'] ?? '',
                'IsNonReturnable' => $item['IsNonReturnable'] ?? '',
                'IsHazmat' => $item['IsHazmat'] ?? '',
                'IsHazmatByAir' => $item['IsHazmatByAir'] ?? '',
                'IsHazmatByGround' => $item['IsHazmatByGround'] ?? '',
                'VendorName' => $item['VendorName'] ?? '',
            ];
        }, $this->data);
    }

    public function headings(): array
    {
        return [
            'VCPN',
            'VendorCode',
            'PartNumber',
            'LongDescription',
            'CatalogBrandCode',
            'ManufacturerProductNo',
            'UPCCode',
            'ExeterQty',
            'MidWestQty',
            'CaliforniaQty',
            'SouthEastQty',
            'TexasQty',
            'TexasDFWQty',
            'GreatLakesQty',
            'PacificNorthwestQty',
            'FloridaQty',
            'MinToSell',
            'ShippingFlag',
            'CoreCharge',
            'FET',
            'BlockedPart',
            'CalifBlocked',
            'IsNonReturnable',
            'IsHazmat',
            'IsHazmatByAir',
            'IsHazmatByGround',
            'VendorName',
        ];
    }
}
