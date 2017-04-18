<?php

class BaseData
{
    public static $productTypes = [
        1 => ['id' => 1, 'title' => 'Product type 1', 'image' => 'image 1'],
        2 => ['id' => 2, 'title' => 'Product type 2', 'image' => 'image 2'],
        3 => ['id' => 3, 'title' => 'Product type 3', 'image' => 'image 3'],
        4 => ['id' => 4, 'title' => 'Product type 4', 'image' => 'image 4'],
        5 => ['id' => 5, 'title' => 'Product type 5', 'image' => 'image 5']
    ];

    public static $products = [
        1 => ['id' => 1, 'title' => 'Product 1', 'image' => 'Product image 1', 'type_id' => 1, 'price' => 24, 'is_deleted' => 0, 'is_hidden' => 0],
        2 => ['id' => 2, 'title' => 'Product 2', 'image' => 'Product image 2', 'type_id' => 1, 'price' => 20, 'is_deleted' => 0, 'is_hidden' => 0],
        3 => ['id' => 3, 'title' => 'Product 3', 'image' => 'Product image 3', 'type_id' => 1, 'price' => 17, 'is_deleted' => 0, 'is_hidden' => 0],
        4 => ['id' => 4, 'title' => 'Product 4', 'image' => 'Product image 4', 'type_id' => 2, 'price' => 74, 'is_deleted' => 1, 'is_hidden' => 0],
        5 => ['id' => 5, 'title' => 'Product 5', 'image' => 'Product image 5', 'type_id' => 3, 'price' => 25, 'is_deleted' => 0, 'is_hidden' => 0],
        6 => ['id' => 6, 'title' => 'Product 6', 'image' => 'Product image 6', 'type_id' => 3, 'price' => 99, 'is_deleted' => 0, 'is_hidden' => 0],
        7 => ['id' => 7, 'title' => 'Product 7', 'image' => 'Product image 7', 'type_id' => 4, 'price' => 7, 'is_deleted' => 1, 'is_hidden' => 0],
        8 => ['id' => 8, 'title' => 'Product 8', 'image' => 'Product image 8', 'type_id' => 4, 'price' => 64, 'is_deleted' => 1, 'is_hidden' => 0],
        9 => ['id' => 9, 'title' => 'Product 9', 'image' => 'Product image 9', 'type_id' => 5, 'price' => 32, 'is_deleted' => 0, 'is_hidden' => 1],
        10 => ['id' => 10, 'title' => 'Product 10', 'image' => 'Product image 10', 'type_id' => 5, 'price' => 82, 'is_deleted' => 0, 'is_hidden' => 0]
    ];

    public static $productCategories = [
        1 => ['id' => 1, 'title' => 'Category 1'],
        2 => ['id' => 2, 'title' => 'Category 2'],
        3 => ['id' => 3, 'title' => 'Category 3'],
        4 => ['id' => 4, 'title' => 'Category 4'],
        5 => ['id' => 5, 'title' => 'Category 5'],
        6 => ['id' => 6, 'title' => 'Category 6'],
        7 => ['id' => 7, 'title' => 'Category 7']
    ];

    public static $productsProductCategories = [
        1 => ['id' => 1, 'product_id' => 1, 'product_category_id' => 1, 'sorting' => 10],
        2 => ['id' => 2, 'product_id' => 1, 'product_category_id' => 2, 'sorting' => 9],
        3 => ['id' => 3, 'product_id' => 2, 'product_category_id' => 3, 'sorting' => 8],
        4 => ['id' => 4, 'product_id' => 2, 'product_category_id' => 4, 'sorting' => 7],
        5 => ['id' => 5, 'product_id' => 3, 'product_category_id' => 5, 'sorting' => 6],
        6 => ['id' => 6, 'product_id' => 3, 'product_category_id' => 6, 'sorting' => 5],
        7 => ['id' => 7, 'product_id' => 4, 'product_category_id' => 7, 'sorting' => 4],
        8 => ['id' => 8, 'product_id' => 5, 'product_category_id' => 1, 'sorting' => 3],
        9 => ['id' => 9, 'product_id' => 6, 'product_category_id' => 2, 'sorting' => 2],
        10 => ['id' => 10, 'product_id' => 7, 'product_category_id' => 3, 'sorting' => 1],
        11 => ['id' => 11, 'product_id' => 8, 'product_category_id' => 4, 'sorting' => 11],
        12 => ['id' => 12, 'product_id' => 9, 'product_category_id' => 5, 'sorting' => 15],
    ];
}