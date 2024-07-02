<?php

namespace App\Services\Interfaces;

interface SellerCloudInterface
{
    public function getProducts($pageNumber = 1, $pageSize = 100, $vendorId = 15073);
}
