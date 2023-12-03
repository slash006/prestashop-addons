<?php

class LowestPriceCalculator {
    // Here you can add class properties if needed

    public function __construct() {
        // Constructor of the class, if needed
    }

    public function getBaseProductPrice($id_product) {
        // Logic for fetching the base price of the product
        // This method will be mocked in tests
    }

    public function getProductPriceByAttribute($id_product, $id_product_attribute) {
        // Logic for fetching the product's price based on an attribute
        // This method will also be mocked in tests
    }

    public function calculateLowestPriceForProductWithAttribute($productData, $attributeData) {


        $lowestPrice = $productData["current_price"] + $attributeData["previous_lowest_price"];

        return array("lowest_price" => $lowestPrice);

    }
}


class LowestPriceCalculatorTest extends PHPUnit\Framework\TestCase {
    public function testGetBaseProductPrice() {
        $calculator = $this->createMock(LowestPriceCalculator::class);
        $calculator->method('getBaseProductPrice')
            ->willReturn([
                'id' => '1',
                'id_product' => '18460',
                'id_product_attribute' => '0',
                'previous_lowest_price' => '3251.219512',
                'current_price' => '4000.000000',
                'previous_price_timestamp' => '2023-12-01 13:50:05',
                'current_price_timestamp' => '2023-12-01 13:50:05'
            ]);

        $result = $calculator->getBaseProductPrice('18460');
        // Asserts to check the result
    }

    public function testGetProductPriceByAttribute() {
        $calculator = new LowestPriceCalculator();

        $baseProduct = [
            'id' => '1',
            'id_product' => '18460',
            'id_product_attribute' => '0',
            'previous_lowest_price' => '3251.219512',
            'current_price' => '4000.000000',
            'previous_price_timestamp' => '2023-11-01 13:50:05',
            'current_price_timestamp' => '2023-11-02 13:50:05'
        ];

        $attribute = [
            'id' => '2',
            'id_product' => '18460',
            'id_product_attribute' => '3245',
            'previous_lowest_price' => '0',
            'current_price' => '-200',
            'previous_price_timestamp' => '2023-11-05 13:50:08',
            'current_price_timestamp' => '2023-11-05 13:50:08'
        ];

        $result = $calculator->calculateLowestPriceForProductWithAttribute($baseProduct, $attribute);

        $this->assertEquals("4000", $result["lowest_price"]);

        print_r($result);

        // Asserts to check the result
    }
}
