<?php

namespace Kelemen\SimpleMapper\Tests\Mock\ActiveRow;

use SimpleMapper\ActiveRow;

class Product extends ActiveRow
{
    /**
     * @return ProductType
     */
    public function getType()
    {
        return $this->ref('type');
    }

    /**
     * @return ProductCategory[]
     */
    public function getCategories()
    {
        return $this->mmRelated($this->related('product_categories'), 'product_category');
    }

    /**
     * @return ProductCategory[]
     */
    public function getSortedCategories()
    {
        return $this->mmRelated($this->related('product_categories')->order('sorting ASC'), 'product_category');
    }
}