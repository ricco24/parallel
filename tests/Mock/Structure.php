<?php

namespace Kelemen\SimpleMapper\Tests\Mock;

use Kelemen\SimpleMapper\Tests\Mock\ActiveRow\Product;
use Kelemen\SimpleMapper\Tests\Mock\ActiveRow\ProductCategory;
use Kelemen\SimpleMapper\Tests\Mock\ActiveRow\ProductType;
use Kelemen\SimpleMapper\Tests\Mock\Selection\ProductCategories;
use Kelemen\SimpleMapper\Tests\Mock\Selection\Products;
use Kelemen\SimpleMapper\Tests\Mock\Selection\ProductTypes;
use SimpleMapper\CustomStructure;

class Structure extends CustomStructure
{
    public function __construct()
    {
        $this
            ->addActiveRowClass('products', Product::class)
            ->addActiveRowClass('product_types', ProductType::class)
            ->addActiveRowClass('product_categories', ProductCategory::class)
            ->addSelectionClass('products', Products::class)
            ->addSelectionClass('product_types', ProductTypes::class)
            ->addSelectionClass('product_categories', ProductCategories::class);
    }
}