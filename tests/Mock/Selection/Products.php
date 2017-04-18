<?php

namespace Kelemen\SimpleMapper\Tests\Mock\Selection;

use SimpleMapper\Selection;

class Products extends Selection
{
    /**
     * Fetch only active products
     * @return Products
     */
    public function active()
    {
        $this->getSelection()->where([
            'is_hidden' => 0,
            'is_deleted' => 0
        ]);
        return $this;
    }

    /**
     * Fetch only products visible for admins
     * @return Products
     */
    public function forAdmin()
    {
        $this->getSelection()->where([
            'is_hidden' => 0
        ]);
        return $this;
    }
}