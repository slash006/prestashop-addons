<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class PriceLogger extends Module
{

    const TABLE_NAME = 'price_log';

    public function __construct()
    {
        $this->name = 'pricelogger';
        $this->tab = 'front_office_features';
        $this->version = '1.0.2';
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
        // Register hooks and install module, database and triggers
        return parent::install() &&
            $this->registerHook('actionProductUpdate') &&
            $this->registerHook('displayProductAdditionalInfo') &&
            $this->registerHook('displayProductPriceBlock') &&
            $this->installDb() &&
            $this->installTriggers();
    }

    public function uninstall()
    {
        return $this->uninstallDb() &&
            parent::uninstall();
    }

    protected function installDb()
    {
        $tableName = _DB_PREFIX_ . self::TABLE_NAME;
        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
                    id INT NOT NULL AUTO_INCREMENT,
                    id_product INT NOT NULL,
                    id_product_attribute INT NOT NULL DEFAULT 0,
                    price DECIMAL(20,6),
                    added_timestamp DATETIME,
                    PRIMARY KEY (id)
                );";

        return Db::getInstance()->execute($sql);
    }

    protected function uninstallDb()
    {

        $this->uninstallTriggers();
        $tableName = _DB_PREFIX_ . self::TABLE_NAME;
        $sql = "DROP TABLE IF EXISTS $tableName;";
        return Db::getInstance()->execute($sql);
    }

    protected function installTriggers()
    {
        $this->uninstallTriggers();

        $tableName = _DB_PREFIX_ . self::TABLE_NAME;

        $sql = [
            // Trigger for ps_product
            "CREATE TRIGGER after_product_update
        AFTER UPDATE ON " . _DB_PREFIX_ . "product
        FOR EACH ROW
        BEGIN

            IF NEW.price <> OLD.price THEN
                INSERT INTO {$tableName} (id_product, id_product_attribute, price, added_timestamp)
                VALUES (NEW.id_product, 0, OLD.price, NOW());
                
                INSERT INTO {$tableName} (id_product, id_product_attribute, price, added_timestamp)
                VALUES (NEW.id_product, 0, NEW.price, NOW());
                
            END IF;
        END",

            // Trigger for ps_product_attribute
            "CREATE TRIGGER after_product_attribute_update
        AFTER UPDATE ON " . _DB_PREFIX_ . "product_attribute
        FOR EACH ROW
        BEGIN
            IF NEW.price <> OLD.price THEN

                INSERT INTO {$tableName} (id_product, id_product_attribute, price, added_timestamp)
                VALUES (NEW.id_product, NEW.id_product_attribute, OLD.price, NOW());
                
                INSERT INTO {$tableName} (id_product, id_product_attribute, price, added_timestamp)
                VALUES (NEW.id_product, NEW.id_product_attribute, NEW.price, NOW());
                
            END IF;
        END"
        ];

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    protected function uninstallTriggers()
    {
        $sql = [
            // Remove trigger for ps_product
            "DROP TRIGGER IF EXISTS after_product_update",

            // Remove trigger for ps_product_attribute
            "DROP TRIGGER IF EXISTS after_product_attribute_update"
        ];

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }


    public function getBaseProductPrice($id_product) {

        $product = new Product($id_product);
        return $product->price;
    }


    public function getLowestPriceBeforeLastPromotion($id_product, $id_product_attribute = 0) {
        $db = Db::getInstance();

        $queryForLastPromotionDate = new DbQuery();
        $queryForLastPromotionDate->select('current_price_timestamp, previous_lowest_price');
        $queryForLastPromotionDate->from('price_log');
        $queryForLastPromotionDate->where('id_product = ' . (int)$id_product);
        $queryForLastPromotionDate->where('id_product_attribute = ' . (int)$id_product_attribute);
        $queryForLastPromotionDate->orderBy('current_price_timestamp DESC');

        $result = $db->getRow($queryForLastPromotionDate);

        $lastPromotionDate = $result["current_price_timestamp"];

        $queryForLowestPrice = new DbQuery();
        $queryForLowestPrice->select('MIN(current_price) as lowest_price');
        $queryForLowestPrice->from('price_log');
        $queryForLowestPrice->where('id_product = ' . (int)$id_product);
        $queryForLowestPrice->where('id_product_attribute = ' . (int)$id_product_attribute);
        $queryForLowestPrice->where('previous_price_timestamp < \'' . pSQL($lastPromotionDate) . '\'');
        $queryForLowestPrice->where('previous_price_timestamp >= DATE_SUB(\'' . pSQL($lastPromotionDate) . '\', INTERVAL 30 DAY)');
        $lowestPrice = $db->getValue($queryForLowestPrice);


        if($lowestPrice)
            return (float)$lowestPrice;

        if($result["previous_lowest_price"])
            return $result["previous_lowest_price"];

        return $this->getBaseProductPrice($id_product);

    }

    public function getPreviousLowestProductPrice($id_product, $id_product_attribute) {

        $tableName = self::TABLE_NAME;

        $basePrice = $this->getBaseProductPrice($id_product);

        if($id_product_attribute === 0)
            return $basePrice;

        $sql = new DbQuery();
        $sql->select('previous_lowest_price');
        $sql->from($tableName);
        $sql->where('id_product = ' . (int)$id_product);
        $sql->where('id_product_attribute = ' . (int)$id_product_attribute);
        $attributePriceChange = Db::getInstance()->getValue($sql);


        return $basePrice + $attributePriceChange;
    }

    public function hookDisplayProductPriceBlock($params)
    {

        if ($params['type'] == 'after_price') {
            $id_product = (int)$params['product']['id_product'];
            $id_product_attribute = null;

            if (isset($params['product']['id_product_attribute'])) {
                $id_product_attribute = (int)$params['product']['id_product_attribute'];

                $baseProductLowestPrice = $this->getLowestPriceBeforeLastPromotion($id_product, 0);
                $lowestPrice = $baseProductLowestPrice + $this->getLowestPriceBeforeLastPromotion($id_product, $id_product_attribute);
            }

            else {
                $lowestPrice = $this->getLowestPriceBeforeLastPromotion($id_product, $id_product_attribute);
            }


            $this->context->smarty->assign(array(
                'lowestPrice' => $lowestPrice ?: null,
            ));

            return $this->display(__FILE__, 'views/templates/hook/last_price_change.tpl');
        }

    }


}
