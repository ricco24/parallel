<?php

namespace Kelemen\SimpleMapper\Tests;

require_once 'DatabaseConnection.php';
require_once 'Mock/Selection/Products.php';
require_once 'Mock/Selection/ProductCategories.php';
require_once 'Mock/ActiveRow/Product.php';
require_once 'Mock/ActiveRow/ProductType.php';
require_once 'Mock/ActiveRow/ProductCategory.php';
require_once 'Mock/Structure.php';

use Kelemen\SimpleMapper\Tests\Mock\ActiveRow\Product;
use Kelemen\SimpleMapper\Tests\Mock\Selection\Products;
use Kelemen\SimpleMapper\Tests\Mock\Structure;
use PHPUnit_Framework_TestCase;
use DatabaseConnection;

class TestBase extends PHPUnit_Framework_TestCase
{
    /**
     * Setup before every test
     */
    public function setUp()
    {
        DatabaseConnection::refreshDatabase();
    }

    /**
     * Fetch one product
     * @param int $id
     * @return Product
     */
    protected function getProduct($id)
    {
        $p = DatabaseConnection::getContext()->table('products')->wherePrimary($id)->fetch();
        return new Product($p, $this->getStructure());
    }

    /**
     * Fetch products selection
     * @param array $where
     * @return Products
     */
    protected function getProducts(array $where = [])
    {
        $selection = DatabaseConnection::getContext()->table('products');
        if ($where) {
            $selection->where($where);
        }
        return new Products($selection, $this->getStructure());
    }

    /**
     * Build structure
     * @return Structure
     */
    protected function getStructure()
    {
        return new Structure();
    }
}