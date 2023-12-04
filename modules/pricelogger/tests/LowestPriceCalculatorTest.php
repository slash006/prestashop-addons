<?php

class LowestPriceCalculator
{

    private function getBaseProductPrice()
    {

        //TODO: use data from Product::
        return 4000;
    }

    private function filterRecentEntries($productData)
    {
        if (empty($productData)) {
            return [];
        }

        $lastTimestamp = new DateTime(end($productData)['timestamp']);
        $filteredEntries = array_filter($productData, function ($entry) use ($lastTimestamp) {
            $entryTimestamp = new DateTime($entry['timestamp']);
            $difference = $lastTimestamp->diff($entryTimestamp)->days;

            return $difference <= 30;
        });

        return $filteredEntries;
    }

    public function findLowestPrice($productData, $showDebug = false): array
    {


        $productLowestPrice = $this->getBaseProductPrice();
        $priceResults = array();
        $lastBasePriceData = null;

        $currentPrice = end($productData)["price"];

        //Remove last elements from calculations
        array_pop($productData);
        $productData = $this->filterRecentEntries($productData);

        foreach ($productData as $entry) {

            if($entry["id_product_attribute"] === 0) {
                $lastBasePriceData = $entry["price"];
                $priceResults[] = $entry["price"];
            }
            else {
                if(!$lastBasePriceData)
                    $lastBasePriceData = $this->getBaseProductPrice();

                $priceResults[] = $lastBasePriceData + $entry["price"]; //TODO append price change id
            }
        }

        if($showDebug)
            print_r($priceResults);

        if(count($priceResults))
            $minimalPrice = min($priceResults);

        else
            $minimalPrice = $productLowestPrice;

        return array("lowest_price" => $minimalPrice, "current_price" => $currentPrice > 0 ? $currentPrice : $lastBasePriceData + $currentPrice);

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
        $result = $calculator->findLowestPrice($productLog);
        $this->assertEquals(3300, $result['lowest_price']);
        $this->assertEquals(3200, $result['current_price']);
    }

    public function testCalculateLowestFirstPromo() {

        $productLog = [
            array("id" => 1, "id_product" => 500, "id_product_attribute" => 0, "price" => 3900, "timestamp" => "2023-12-01 13:50:05")
        ];
        $calculator = new LowestPriceCalculator();
        $result = $calculator->findLowestPrice($productLog);
        $this->assertEquals(4000, $result['lowest_price']);
        $this->assertEquals(3900, $result['current_price']);
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
        $result = $calculator->findLowestPrice($productLog);
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
        $result = $calculator->findLowestPrice($productLog);

        $this->assertEquals(2500, $result['current_price']);
        $this->assertEquals(3150, $result['lowest_price']);
    }

    public function testCalculateLowestMixedWithCombinations() {

        $productLog = [
            array("id" => 1, "id_product" => 500, "id_product_attribute" => 0, "price" => 4000, "timestamp" => "2023-12-01 13:50:05"),
            array("id" => 2, "id_product" => 500, "id_product_attribute" => 0, "price" => 2900, "timestamp" => "2023-12-02 13:50:05"),
            array("id" => 3, "id_product" => 500, "id_product_attribute" => 0, "price" => 3500, "timestamp" => "2023-12-03 15:00:05"),
            array("id" => 4, "id_product" => 500, "id_product_attribute" => 2000, "price" => -200, "timestamp" => "2023-12-04 20:50:05"),
            array("id" => 5, "id_product" => 500, "id_product_attribute" => 2000, "price" => -700, "timestamp" => "2023-12-05 20:50:05"),
            array("id" => 6, "id_product" => 500, "id_product_attribute" => 2000, "price" => -100, "timestamp" => "2023-12-06 20:50:05"),
            array("id" => 7, "id_product" => 500, "id_product_attribute" => 0, "price" => 3300, "timestamp" => "2023-12-07 15:00:05"),

        ];

        $calculator = new LowestPriceCalculator();

        //SQL id_product = 500 AND (id_product_attribute = 2000 OR  id_product_attribute = 0)
        $result = $calculator->findLowestPrice($productLog, false);

        $this->assertEquals(3300, $result['current_price']);
        $this->assertEquals(2800, $result['lowest_price']);

    }


    public function testCalculateLowestMixedWithCombinationsBaseLowest() {

        $productLog = [
            array("id" => 1, "id_product" => 500, "id_product_attribute" => 0, "price" => 1250, "timestamp" => "2023-10-01 13:50:05"),
            array("id" => 2, "id_product" => 500, "id_product_attribute" => 0, "price" => 3000, "timestamp" => "2023-11-01 13:50:05"),
            array("id" => 3, "id_product" => 500, "id_product_attribute" => 0, "price" => 1750, "timestamp" => "2023-12-01 13:50:05"),
            array("id" => 4, "id_product" => 500, "id_product_attribute" => 0, "price" => 2000, "timestamp" => "2023-12-02 13:50:05"),
            array("id" => 5, "id_product" => 500, "id_product_attribute" => 0, "price" => 3500, "timestamp" => "2023-12-03 15:00:05"),
            array("id" => 6, "id_product" => 500, "id_product_attribute" => 2000, "price" => -200, "timestamp" => "2023-12-04 20:50:05"),
            array("id" => 7, "id_product" => 500, "id_product_attribute" => 2000, "price" => -700, "timestamp" => "2023-12-05 20:50:05"),
            array("id" => 8, "id_product" => 500, "id_product_attribute" => 2000, "price" => -900, "timestamp" => "2023-12-06 20:50:05"),
            array("id" => 8, "id_product" => 500, "id_product_attribute" => 2000, "price" => -1900, "timestamp" => "2023-12-06 20:50:05"),
            array("id" => 9, "id_product" => 500, "id_product_attribute" => 0, "price" => 4000, "timestamp" => "2023-12-07 15:00:05"),

        ];

        $calculator = new LowestPriceCalculator();

        //SQL id_product = 500 AND (id_product_attribute = 2000 OR  id_product_attribute = 0)
        $result = $calculator->findLowestPrice($productLog);

        $this->assertEquals(4000, $result['current_price']); //TODO it should return 4000 - 1900?
        $this->assertEquals(1600, $result['lowest_price']);
    }

    public function testCalculateLowestOnlyAttributes() {

        $productLog = [
            array("id" => 6, "id_product" => 500, "id_product_attribute" => 2000, "price" => -200, "timestamp" => "2023-12-04 20:50:05"),
            array("id" => 7, "id_product" => 500, "id_product_attribute" => 2000, "price" => -700, "timestamp" => "2023-12-05 20:50:05"),
            array("id" => 8, "id_product" => 500, "id_product_attribute" => 2000, "price" => -900, "timestamp" => "2023-12-06 20:50:05")

        ];

        $calculator = new LowestPriceCalculator();

        //SQL id_product = 500 AND (id_product_attribute = 2000 OR  id_product_attribute = 0)
        $result = $calculator->findLowestPrice($productLog, false);

        $this->assertEquals(3100, $result['current_price'], "Current price: ");
        $this->assertEquals(3300, $result['lowest_price'], "Lowest price: ");
    }

    public function testCalculateLowestAttributesBeforeProductData() {

        $productLog = [
            array("id" => 6, "id_product" => 500, "id_product_attribute" => 2000, "price" => -200, "timestamp" => "2023-12-04 20:50:05"),
            array("id" => 7, "id_product" => 500, "id_product_attribute" => 2000, "price" => -700, "timestamp" => "2023-12-05 20:50:05"),
            array("id" => 8, "id_product" => 500, "id_product_attribute" => 2000, "price" => -900, "timestamp" => "2023-12-06 20:50:05"),
            array("id" => 8, "id_product" => 500, "id_product_attribute" => 0, "price" => 2600, "timestamp" => "2023-12-06 20:50:05"),
            array("id" => 8, "id_product" => 500, "id_product_attribute" => 0, "price" => 2000, "timestamp" => "2023-12-08 20:50:05"),
            array("id" => 8, "id_product" => 500, "id_product_attribute" => 2000, "price" => -600, "timestamp" => "2023-12-08 20:50:05"),
            array("id" => 8, "id_product" => 500, "id_product_attribute" => 2000, "price" => -700, "timestamp" => "2023-12-08 20:50:05"),

        ];

        $calculator = new LowestPriceCalculator();

        //SQL id_product = 500 AND (id_product_attribute = 2000 OR  id_product_attribute = 0) AND DATEDIFF < timestamp of last entry
        $result = $calculator->findLowestPrice($productLog, false);

        $this->assertEquals(1300, $result['current_price'], "Current price: ");
        $this->assertEquals(1400, $result['lowest_price'], "Lowest price: ");
    }

    public function testAllVariations() {

        $productLog = [
            array("id" => 6, "id_product" => 500, "id_product_attribute" => 2000, "price" => -200, "timestamp" => "2023-10-04 20:50:05"),
            array("id" => 7, "id_product" => 500, "id_product_attribute" => 0, "price" => 5000, "timestamp" => "2023-11-05 20:50:05"),
            array("id" => 8, "id_product" => 500, "id_product_attribute" => 2000, "price" => -750, "timestamp" => "2023-11-06 20:50:05"),
            array("id" => 8, "id_product" => 500, "id_product_attribute" => 0, "price" => 2600, "timestamp" => "2023-12-06 20:50:05"),
            array("id" => 8, "id_product" => 500, "id_product_attribute" => 0, "price" => 1600, "timestamp" => "2023-10-08 20:50:05"),
            array("id" => 8, "id_product" => 500, "id_product_attribute" => 0, "price" => 2300, "timestamp" => "2023-12-08 20:50:05"),
            array("id" => 8, "id_product" => 500, "id_product_attribute" => 0, "price" => 2000, "timestamp" => "2023-12-08 20:50:05"),
            array("id" => 8, "id_product" => 500, "id_product_attribute" => 2000, "price" => -300, "timestamp" => "2023-12-08 20:50:05"),
            array("id" => 8, "id_product" => 500, "id_product_attribute" => 2000, "price" => -400, "timestamp" => "2023-12-08 20:50:05"),
            array("id" => 8, "id_product" => 500, "id_product_attribute" => 2000, "price" => -300, "timestamp" => "2023-12-08 20:50:05"),
            array("id" => 8, "id_product" => 500, "id_product_attribute" => 2000, "price" => -1400, "timestamp" => "2023-12-08 20:50:05"),

        ];

        $calculator = new LowestPriceCalculator();
        $result = $calculator->findLowestPrice($productLog, true);

        $this->assertEquals(600, $result['current_price'], "Current price: ");
        $this->assertEquals(1600, $result['lowest_price'], "Lowest price: ");

    }

}
