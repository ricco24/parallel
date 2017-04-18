<?php

namespace Kelemen\SimpleMapper\Tests;

require_once 'TestBase.php';

use Kelemen\SimpleMapper\Tests\Mock\ActiveRow\ProductType;
use BaseData;
use Nette\DeprecatedException;

class ActiveRowTest extends TestBase
{
    public function testReference()
    {
        $products = $this->getProducts();
        foreach ($products as $product) {
            $this->assertInstanceOf(ProductType::class, $product->getType());
        }
    }

    public function testMMRelated()
    {
        $product = $this->getProduct(2);
        $categories = $product->getCategories();

        $this->assertCount(2, $categories);

        $i = 3;
        foreach ($categories as $category) {
            $this->assertEquals('Category ' . $i, $category->title);
            $i++;
        }
    }

    public function testSortedRelated()
    {
        $product = $this->getProduct(2);
        $categories = $product->getSortedCategories();

        $this->assertCount(2, $categories);
        $this->assertTrue(isset($categories[3]));
        $this->assertTrue(isset($categories[4]));

        $i = 4;
        foreach ($categories as $category) {
            $this->assertEquals('Category ' . $i, $category->title);
            $i--;
        }
    }

    public function testWrapperFunctions()
    {
        $productId = 2;
        $product = $this->getProduct($productId);

        $this->assertEquals(BaseData::$products[$productId], $product->toArray());
        $this->assertEquals($productId, $product->getPrimary());
        $this->assertEquals($productId, $product->getSignature());
        $this->assertTrue($product->update(['title' => 'Updated product']));
        $this->assertEquals('Updated product', $product->title);
        $this->assertEquals(1, $product->delete());
    }

    public function testArrayAccessIssetGet()
    {
        $product = $this->getProduct(4);
        $this->assertTrue(isset($product['title']));
        $this->assertEquals('Product 4', $product['title']);
    }

    public function testArrayAccessSet()
    {
        $product = $this->getProduct(4);
        $this->expectException(DeprecatedException::class);
        $product['title'] = 'New product title';
    }

    public function testArrayAccessUnset()
    {
        $product = $this->getProduct(4);
        $this->expectException(DeprecatedException::class);
        unset($product['title']);
    }

    public function testIterator()
    {
        $productId = 4;
        $product = $this->getProduct($productId);
        foreach ($product as $key => $value) {
            $this->assertEquals(BaseData::$products[$productId][$key], $value);
        }
    }

    public function testMagicGet()
    {
        $product = $this->getProduct(4);
        $this->assertInstanceOf(ProductType::class, $product->type);
        $this->assertEquals($product->type_id, $product->type->id);
        $this->assertEquals(BaseData::$productTypes[$product->type_id]['title'], $product->type->title);
    }
}