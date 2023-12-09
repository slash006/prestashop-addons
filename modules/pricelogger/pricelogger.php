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
        $sql = 'CREATE TABLE IF NOT EXISTS `' . $tableName . '` (
            `id_product` int(11) NOT NULL,
            `id_product_attribute` int(11) NOT NULL,
            `lowest_price` decimal(20,6) NOT NULL,
            `last_price` decimal(20,6) NOT NULL,
            `lowest_timestamp` datetime NOT NULL,
            `last_timestamp` datetime NOT NULL,
            PRIMARY KEY (`id_product`, `id_product_attribute`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;';

        return Db::getInstance()->execute($sql);

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

        /*        $tableName = _DB_PREFIX_ . self::TABLE_NAME;
                $product = file_get_contents($this->local_path.'/sql/trigger_before_product_update.sql');*/

        $sql = [
            // Trigger for ps_product
            "CREATE TRIGGER before_product_update
    BEFORE UPDATE ON ps_product
    FOR EACH ROW
BEGIN
    DECLARE currentLowestPrice DECIMAL(20,6);
    DECLARE newProductPrice DECIMAL(20,6);
    DECLARE oldProductPrice DECIMAL(20,6);
    DECLARE attributePrice DECIMAL(20,6);
    DECLARE lowestPriceTime DATETIME;
    DECLARE lastPriceTime DATETIME;
    DECLARE attributeID INT;
    DECLARE attributesFound INT DEFAULT 0;

    DECLARE done INT DEFAULT FALSE;
    DECLARE attributeCursor CURSOR FOR SELECT id_product_attribute, price FROM ps_product_attribute WHERE id_product = OLD.id_product;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    IF OLD.price <> NEW.price THEN
        SET oldProductPrice = OLD.price;
    OPEN attributeCursor;
    read_loop: LOOP
            FETCH attributeCursor INTO attributeID, attributePrice;
            IF done THEN
                LEAVE read_loop;
END IF;
SET attributesFound = 1;

            SET newProductPrice = NEW.price + attributePrice;

            IF NOT EXISTS (SELECT 1 FROM ps_price_log WHERE id_product = OLD.id_product AND id_product_attribute = attributeID) THEN
                INSERT INTO ps_price_log (id_product, id_product_attribute, lowest_price, last_price, lowest_timestamp, last_timestamp)
                VALUES (OLD.id_product, attributeID, oldProductPrice + attributePrice, newProductPrice, NOW(), NOW());
ELSE

    SELECT lowest_price, lowest_timestamp, last_timestamp INTO currentLowestPrice, lowestPriceTime, lastPriceTime FROM ps_price_log WHERE id_product = OLD.id_product AND id_product_attribute = attributeID;
    
    IF DATEDIFF(lastPriceTime, lowestPriceTime) > 30 THEN
                UPDATE ps_price_log SET lowest_price = last_price, lowest_timestamp = NOW() WHERE id_product = OLD.id_product AND id_product_attribute = attributeID;
            END IF;
    
    IF oldProductPrice + attributePrice < currentLowestPrice THEN
        UPDATE ps_price_log SET lowest_price = oldProductPrice + attributePrice, lowest_timestamp = NOW() WHERE id_product = OLD.id_product AND id_product_attribute = attributeID;
    END IF;

    UPDATE ps_price_log SET last_price = newProductPrice, last_timestamp = NOW() WHERE id_product = OLD.id_product AND id_product_attribute = attributeID;
END IF;

END LOOP;
CLOSE attributeCursor;

IF attributesFound = 0 THEN
            IF NOT EXISTS (SELECT 1 FROM ps_price_log WHERE id_product = OLD.id_product AND id_product_attribute = 0) THEN
                INSERT INTO ps_price_log (id_product, id_product_attribute, lowest_price, last_price, lowest_timestamp, last_timestamp)
                VALUES (OLD.id_product, 0, oldProductPrice, NEW.price, NOW(), NOW());
ELSE
UPDATE ps_price_log SET last_price = NEW.price, last_timestamp = NOW() WHERE id_product = OLD.id_product AND id_product_attribute = 0;
END IF;
END IF;
END IF;
END;
",

            // Trigger for ps_product_attribute
            "CREATE TRIGGER before_product_attribute_update
BEFORE UPDATE ON ps_product_attribute
FOR EACH ROW
BEGIN
    DECLARE currentLowestPrice DECIMAL(20,6);
    DECLARE basePrice DECIMAL(20,6);
    DECLARE lowestPriceTime DATETIME;
    DECLARE lastPriceTime DATETIME;

    SELECT price INTO basePrice FROM ps_product WHERE id_product = OLD.id_product;

    IF OLD.price <> NEW.price THEN
        SELECT lowest_price, lowest_timestamp, last_timestamp INTO currentLowestPrice, lowestPriceTime, lastPriceTime FROM ps_price_log WHERE id_product = OLD.id_product AND id_product_attribute = OLD.id_product_attribute;

        IF NOT EXISTS (SELECT 1 FROM ps_price_log WHERE id_product = OLD.id_product AND id_product_attribute = OLD.id_product_attribute) THEN
            INSERT INTO ps_price_log (id_product, id_product_attribute, lowest_price, last_price, lowest_timestamp, last_timestamp)
            VALUES (OLD.id_product, OLD.id_product_attribute, basePrice + OLD.price, basePrice + NEW.price, NOW(), NOW());
        ELSE
            IF DATEDIFF(lastPriceTime, lowestPriceTime) > 30 THEN
                SET currentLowestPrice = NEW.price + basePrice;
                SET lowestPriceTime = lastPriceTime;
            END IF;

            IF OLD.price + basePrice < currentLowestPrice THEN
                UPDATE ps_price_log SET lowest_price = OLD.price + basePrice, lowest_timestamp = lowestPriceTime WHERE id_product = OLD.id_product AND id_product_attribute = OLD.id_product_attribute;
            END IF;

            UPDATE ps_price_log SET last_price = NEW.price + basePrice, last_timestamp = NOW() WHERE id_product = OLD.id_product AND id_product_attribute = OLD.id_product_attribute;
        END IF;
    END IF;
END
;
"
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
            "DROP TRIGGER IF EXISTS before_product_update",

            // Remove trigger for ps_product_attribute
            "DROP TRIGGER IF EXISTS before_product_attribute_update"
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

    public function getLowestLastPricePairForProduct($id_product, $id_product_attribute = 0) {

        $tableName = self::TABLE_NAME;

        $query = new DbQuery();
        $query->select('lowest_price, last_price');
        $query->from($tableName);
        $query->where('id_product = '.(int)$id_product.' AND id_product_attribute = '.(int)$id_product_attribute);
        $priceData = Db::getInstance()->getRow($query);


        return $priceData;

    }

    public function hookDisplayProductPriceBlock($params)
    {

        if ($params['type'] == 'after_price') {
            $id_product = (int)$params['product']['id_product'];

            if (isset($params['product']['id_product_attribute'])) {
                $id_product_attribute = (int)$params['product']['id_product_attribute'];

                $price = $this->getLowestLastPricePairForProduct($id_product, $id_product_attribute);

            }

            $this->context->smarty->assign(array(
                'lowestPrice' => $price["lowest_price"] ?: null,
//                'lastPrice' => $price["last_price"] ?: null,
            ));

            return $this->display(__FILE__, 'views/templates/hook/last_price_change.tpl');
        }

    }


}
