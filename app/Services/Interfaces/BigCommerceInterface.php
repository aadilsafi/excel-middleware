<?php

namespace App\Services\Interfaces;

interface BigCommerceInterface
{
    public function getProducts();
    public function createProduct(array $data);
    public function updateProduct(int $productId, array $data);
    public function deleteProduct(int $productId);
    public function getProduct(int $productId);
    public function createProductCustomField(int $productId, array $data);
    public function deleteProductCustomField (int $productId, int $customFieldId);
    public function getProductCustomFields (int $productId);
}
