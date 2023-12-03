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

    public function calculateLowestPrice($productData)
    {

        $productData = $this->filterRecentEntries($productData);
        $currentPrice = end($productData)["price"];
        $lowestPrice = $this->getBaseProductPrice();
        $i = 0;

        foreach ($productData as $element) {
            $i++;
            if ($i === count($productData))
                continue;

            if ($element['id_product_attribute'] !== 0)
                continue;

            if ($element['price'] < $lowestPrice) {
                $lowestPrice = $element['price'];

            }
        }


        return array(
            "lowest_price" => $lowestPrice,
            "current_price" => $currentPrice,
            "current_attribute_price" => null
        );

    }

    public function findLowestPriceForAttribute($productLog, $targetAttributeId) {
        $maxDiscount = null;
        $lowestBasePrice = PHP_INT_MAX;
        $lowestPriceWithDiscount = PHP_INT_MAX;
        $productLog = $this->filterRecentEntries($productLog);

        foreach ($productLog as $entry) {
            if ($entry['id_product_attribute'] == $targetAttributeId) {
                if ($maxDiscount === null || $entry['price'] < $maxDiscount) {
                    $maxDiscount = $entry['price'];
                }
            }
            if ($entry['id_product_attribute'] == 0 && $entry['price'] < $lowestBasePrice) {
                $lowestBasePrice = $entry['price'];
            }
        }

        $foundMaxDiscount = false;
        foreach (array_reverse($productLog) as $entry) {
            if ($entry['id_product_attribute'] == $targetAttributeId && $entry['price'] == $maxDiscount) {
                $foundMaxDiscount = true;
            }

            if ($foundMaxDiscount && $entry['id_product_attribute'] == 0) {
                $lowestPriceWithDiscount = $entry['price'] + $maxDiscount;
                break;
            }
        }

        return min($lowestBasePrice, $lowestPriceWithDiscount);
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

        //SQL id_product = 500 AND (id_product_attribute = 200 OR  id_product_attribute = 0)
        $result = $calculator->calculateLowestPrice($productLog);

        $this->assertEquals(3300, $result['current_price']);
        $this->assertEquals(2900, $result['lowest_price']);
        $this->assertEquals(2800, $calculator->findLowestPriceForAttribute($productLog, 2000));
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
            array("id" => 8, "id_product" => 500, "id_product_attribute" => 2020, "price" => -1900, "timestamp" => "2023-12-06 20:50:05"),
            array("id" => 9, "id_product" => 500, "id_product_attribute" => 0, "price" => 4000, "timestamp" => "2023-12-07 15:00:05"),

        ];

        $calculator = new LowestPriceCalculator();

        //SQL id_product = 500 AND (id_product_attribute = 200 OR  id_product_attribute = 0)
        $result = $calculator->calculateLowestPrice($productLog);

        $this->assertEquals(4000, $result['current_price']);
        $this->assertEquals(1750, $result['lowest_price']);
        $this->assertEquals(1750, $calculator->findLowestPriceForAttribute($productLog, 2000));
    }

}
