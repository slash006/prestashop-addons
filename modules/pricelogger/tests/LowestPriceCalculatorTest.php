<?php

class LowestPriceCalculator {

    private function getBaseProductPrice() {

        return 4000;
    }

    private function filterRecentEntries($productData) {
        if (empty($productData)) {
            return [];
        }

        $lastTimestamp = new DateTime(end($productData)['timestamp']);
        $filteredEntries = array_filter($productData, function($entry) use ($lastTimestamp) {
            $entryTimestamp = new DateTime($entry['timestamp']);
            $difference = $lastTimestamp->diff($entryTimestamp)->days;

            return $difference <= 30;
        });

//        print_r($filteredEntries);

        return $filteredEntries;
    }

    public function calculateLowestPrice($productData) {

        $productData = $this->filterRecentEntries($productData);
        $currentPrice = end($productData)["price"];
        $lowestPrice =  $this->getBaseProductPrice();
        $i = 0;

        foreach ($productData as $element) {
            $i++;
            if($i === count($productData))
                continue;

            if ($element['price'] < $lowestPrice) {
                $lowestPrice = $element['price'];

            }
        }
        
        return array(
            "lowest_price" => $lowestPrice,
            "current_price" => $currentPrice
        );

    }
}


class LowestPriceCalculatorTest extends PHPUnit\Framework\TestCase {


    public function testCalculateLowest() {

        $productLog = [
            array("id" => 1, "id_product" => 500, "id_product_attribute" => 0, "price" => 4000, "timestamp" => "2023-12-01 13:50:05"),
            array("id" => 2, "id_product" => 500, "id_product_attribute" => 0, "price" => 3300, "timestamp" => "2023-12-01 13:50:05"),
            array("id" => 3, "id_product" => 500, "id_product_attribute" => 0, "price" => 3500, "timestamp" => "2023-12-01 15:00:05"),
            array("id" => 4, "id_product" => 500, "id_product_attribute" => 0, "price" => 3200, "timestamp" => "2023-12-01 20:50:05"),
        ];

        $calculator = new LowestPriceCalculator();
        $result = $calculator->calculateLowestPrice($productLog);
        $this->assertEquals(3300, $result['lowest_price']);
        $this->assertEquals(3200, $result['current_price']);
    }

    public function testCalculateLowestFirstPromo() {

        $productLog = [
            array("id" => 1, "id_product" => 500, "id_product_attribute" => 0, "price" => 3500, "timestamp" => "2023-12-01 13:50:05")
        ];
        $calculator = new LowestPriceCalculator();
        $result = $calculator->calculateLowestPrice($productLog);
        $this->assertEquals(4000, $result['lowest_price']);
        $this->assertEquals(3500, $result['current_price']);
    }

    public function testCalculateLowestMultiplePromotions() {

        $productLog = [
            array("id" => 1, "id_product" => 500, "id_product_attribute" => 0, "price" => 4000, "timestamp" => "2023-12-01 13:50:05"),
            array("id" => 2, "id_product" => 500, "id_product_attribute" => 0, "price" => 3100, "timestamp" => "2023-12-01 13:50:05"),
            array("id" => 3, "id_product" => 500, "id_product_attribute" => 0, "price" => 3200, "timestamp" => "2023-12-01 15:00:05"),
            array("id" => 4, "id_product" => 500, "id_product_attribute" => 0, "price" => 3150, "timestamp" => "2023-12-01 20:50:05"),
            array("id" => 5, "id_product" => 500, "id_product_attribute" => 0, "price" => 3000, "timestamp" => "2023-12-02 21:50:05"),
            array("id" => 6, "id_product" => 500, "id_product_attribute" => 0, "price" => 3200, "timestamp" => "2023-12-03 15:00:05"),
            array("id" => 7, "id_product" => 500, "id_product_attribute" => 0, "price" => 3999, "timestamp" => "2023-12-10 20:50:05"),
        ];
        $calculator = new LowestPriceCalculator();
        $result = $calculator->calculateLowestPrice($productLog);
        $this->assertEquals(3000, $result['lowest_price']);
        $this->assertEquals(3999, $result['current_price']);
    }

    public function testCalculateLowestPromotionsOutOfDateRange() {

        $productLog = [
            array("id" => 1, "id_product" => 500, "id_product_attribute" => 0, "price" => 4000, "timestamp" => "2023-12-01 13:50:05"),
            array("id" => 2, "id_product" => 500, "id_product_attribute" => 0, "price" => 3100, "timestamp" => "2023-09-01 13:50:05"),
            array("id" => 3, "id_product" => 500, "id_product_attribute" => 0, "price" => 3200, "timestamp" => "2023-12-01 15:00:05"),
            array("id" => 4, "id_product" => 500, "id_product_attribute" => 0, "price" => 3150, "timestamp" => "2023-12-01 20:50:05"),
            array("id" => 5, "id_product" => 500, "id_product_attribute" => 0, "price" => 3000, "timestamp" => "2023-10-02 21:50:05"),
            array("id" => 6, "id_product" => 500, "id_product_attribute" => 0, "price" => 3200, "timestamp" => "2023-12-03 15:00:05"),
            array("id" => 7, "id_product" => 500, "id_product_attribute" => 0, "price" => 2500, "timestamp" => "2023-12-10 20:50:05"),
        ];
        $calculator = new LowestPriceCalculator();
        $result = $calculator->calculateLowestPrice($productLog);

        $this->assertEquals(2500, $result['current_price']);
        $this->assertEquals(3150, $result['lowest_price']);
    }

}
