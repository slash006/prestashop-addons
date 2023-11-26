<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class PriceLogger extends Module
{
    public function __construct()
    {
        $this->name = 'pricelogger';
        $this->tab = 'front_office_features';
        $this->version = '1.0.1';
        $this->author = 'slash006';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Price Logger (Omnibus compatibility)');
        $this->description = $this->l('Logs each product price change, including variations. Module compliant with the omnibus directive');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('actionProductUpdate') &&
            $this->registerHook('displayProductPriceBlock') &&
            Db::getInstance()->execute('
                CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'price_log` (
                    `id_price_log` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `id_product` INT UNSIGNED NOT NULL,
                    `id_product_attribute` INT UNSIGNED DEFAULT NULL,
                    `previous_price` DECIMAL(20,6) NOT NULL,
                    `lowest_price` DECIMAL(20,6) NOT NULL,
                    `previous_price_date` DATETIME NOT NULL,
                    `last_change_date` DATETIME NOT NULL,
                    PRIMARY KEY (`id_price_log`)
                ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;'
            );
    }

    public function uninstall()
    {
        return Db::getInstance()->execute('DROP TABLE `'._DB_PREFIX_.'price_log`') &&
            parent::uninstall();
    }

    public function hookDisplayProductPriceBlock($params)
    {
        if ($params['type'] == 'after_price') {
            $id_product = (int)$params['product']['id_product'];
            $id_product_attribute = $params['product']['id_product_attribute'] ?? null;

            $priceLog = $this->getCurrentPriceLogEntry($id_product, $id_product_attribute);
            $initialPrice = (float)Product::getPriceStatic($id_product, false, $id_product_attribute);
            $this->fillUpDefaultProductData($id_product);

            if ($priceLog) {
                $this->context->smarty->assign([
                    'previous_price' => $priceLog['previous_price'],
                    'lowest_price' => $priceLog['lowest_price'],
                    'previous_price_date' => $priceLog['previous_price_date'],
                    'last_change_date' => $priceLog['last_change_date'],
                ]);
            }

            return $this->display(__FILE__, 'views/templates/hook/displayPrice.tpl');
        }

    }

    public function fillUpDefaultProductData($productId) {

        $product = new Product($productId);

        $combinations = $product->getAttributeCombinations();
        foreach ($combinations as $combination) {
            $id_product_attribute = (int)$combination['id_product_attribute'];
            $combination_price = (float)Product::getPriceStatic($productId, false, $id_product_attribute);
            $this->updatePriceLog($productId, $id_product_attribute, $combination_price);
        }

    }

    public function hookActionProductUpdate($params)
    {
        $product = $params['product'];
        $id_product = (int)$product->id;
        $new_price = (float)$product->price;

        file_put_contents("product_attribute", print_r($params['product'], true));
//        $id_product_attribute = $params['product']['id_product_attribute'] ?? null;


        // Update for the main product
        $this->updatePriceLog($id_product, null, $new_price);

        // Update for product combinations
        $combinations = $product->getAttributeCombinations();
        foreach ($combinations as $combination) {
            $id_product_attribute = (int)$combination['id_product_attribute'];
            $combination_price = (float)Product::getPriceStatic($id_product, false, $id_product_attribute);
            $this->updatePriceLog($id_product, $id_product_attribute, $combination_price);
        }
    }

    /*    private function updatePriceLog($id_product, $id_product_attribute, $new_price)
        {
            // Retrieve the current entry from price_log
            $currentEntry = $this->getCurrentPriceLogEntry($id_product, $id_product_attribute);
            $currentTime = date('Y-m-d H:i:s');

            if ($currentEntry) {
                // Update logic for an existing entry
                if ($new_price < $currentEntry['lowest_price']) {
                    // Update if the new price is lower
                    Db::getInstance()->update('price_log', [
                        'previous_price' => $currentEntry['lowest_price'],
                        'lowest_price' => $new_price,
                        'previous_price_date' => $currentEntry['last_change_date'],
                        'last_change_date' => $currentTime
                    ], 'id_product = ' . (int)$id_product . ' AND id_product_attribute = ' . (int)$id_product_attribute);
                }
            } else {
                // Create a new entry if it does not exist
                Db::getInstance()->insert('price_log', [
                    'id_product' => $id_product,
                    'id_product_attribute' => $id_product_attribute,
                    'previous_price' => $new_price,
                    'lowest_price' => $new_price,
                    'previous_price_date' => $currentTime,
                    'last_change_date' => $currentTime
                ]);
            }
        }*/

    private function updatePriceLog($id_product, $id_product_attribute, $new_price)
    {
        $currentEntry = $this->getCurrentPriceLogEntry($id_product, $id_product_attribute);
        $currentTime = date('Y-m-d H:i:s');

        if ($currentEntry) {
            if ($new_price < $currentEntry['lowest_price']) {
                Db::getInstance()->update('price_log', [
                    'previous_price' => $currentEntry['lowest_price'],
                    'lowest_price' => $new_price,
                    'previous_price_date' => $currentEntry['last_change_date'],
                    'last_change_date' => $currentTime
                ], 'id_product = ' . (int)$id_product . ' AND id_product_attribute = ' . (int)$id_product_attribute);
            } else if ($new_price > $currentEntry['lowest_price']) {
                Db::getInstance()->update('price_log', [
                    'previous_price' => $new_price,
                    'lowest_price' => $currentEntry['lowest_price'],
                    'previous_price_date' => $currentEntry['last_change_date'],
                    'last_change_date' => $currentTime
                ], 'id_product = ' . (int)$id_product . ' AND id_product_attribute = ' . (int)$id_product_attribute);

            }
        } else {
//            $initialPrice = (float)Product::getPriceStatic($id_product, false, $id_product_attribute);

            Db::getInstance()->insert('price_log', [
                'id_product' => $id_product,
                'id_product_attribute' => $id_product_attribute,
                'previous_price' => $new_price,
                'lowest_price' => $new_price,
                'previous_price_date' => $currentTime,
                'last_change_date' => $currentTime
            ]);
        }
    }


    private function getCurrentPriceLogEntry($id_product, $id_product_attribute)
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('price_log');
        $sql->where('id_product = ' . (int)$id_product);
        if ($id_product_attribute !== null) {
            $sql->where('id_product_attribute = ' . (int)$id_product_attribute);
        }

        return Db::getInstance()->getRow($sql);
    }
}
