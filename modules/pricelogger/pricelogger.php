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
        $this->version = '1.0.0';
        $this->author = 'slash006';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Price Logger');
        $this->description = $this->l('Logs each product price change, including variations.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('actionProductUpdate') &&
            $this->registerHook('displayProductAdditionalInfo') &&
            Db::getInstance()->execute('
                CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'price_log` (
                    `id_price_log` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `id_product` INT UNSIGNED NOT NULL,
                    `id_product_attribute` INT UNSIGNED DEFAULT NULL,
                    `price` DECIMAL(20,6) NOT NULL,
                    `date_upd` DATETIME NOT NULL,
                    PRIMARY KEY (`id_price_log`)
                ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;'
            );
    }

    public function uninstall()
    {
        return Db::getInstance()->execute('DROP TABLE `'._DB_PREFIX_.'price_log`') &&
            parent::uninstall();
    }

    public function hookActionProductUpdate($params)
    {
        $product = $params['product'];
        $id_product = (int)$product->id;

        // Handle single product price update
        $this->logPriceChange($id_product, null, (float)$product->price);

        // Handle product attribute (variation) price updates
        $combinations = $product->getAttributeCombinations();
        foreach ($combinations as $combination) {
            $id_product_attribute = (int)$combination['id_product_attribute'];
            $price = (float)Product::getPriceStatic($id_product, false, $id_product_attribute);
            $this->logPriceChange($id_product, $id_product_attribute, $price);
        }
    }

    public function hookDisplayProductAdditionalInfo($params)
    {
        $id_product = (int)$params['product']['id_product'];
        $lastPriceChange = $this->getLastPriceChange($id_product);

        $this->context->smarty->assign(array(
            'lastPriceChange' => $lastPriceChange,
        ));

        return $this->display(__FILE__, 'views/templates/hook/last_price_change.tpl');
    }

    private function getLastPriceChange($id_product)
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('price_log');
        $sql->where('id_product = ' . (int)$id_product);
        $sql->orderBy('date_upd DESC');
//        $sql->limit(1);

        return Db::getInstance()->getRow($sql);
    }

    private function logPriceChange($id_product, $id_product_attribute, $price)
    {
        Db::getInstance()->insert('price_log', array(
            'id_product' => $id_product,
            'id_product_attribute' => $id_product_attribute,
            'price' => $price,
            'date_upd' => date('Y-m-d H:i:s'),
        ));
    }
}
