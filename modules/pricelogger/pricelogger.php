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
                    id_product INT NOT NULL,
                    id_product_attribute INT NOT NULL DEFAULT 0,
                    previous_lowest_price DECIMAL(20,6),
                    current_price DECIMAL(20,6),
                    previous_price_timestamp DATETIME,
                    current_price_timestamp DATETIME,
                    PRIMARY KEY (id_product, id_product_attribute)
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
            DECLARE original_current_price_timestamp TIMESTAMP;

            SELECT current_price_timestamp INTO original_current_price_timestamp
            FROM {$tableName}
            WHERE id_product = NEW.id_product
            ORDER BY current_price_timestamp DESC
            LIMIT 1;

            IF NEW.price <> OLD.price THEN
                INSERT INTO {$tableName} (id_product, id_product_attribute, previous_lowest_price, current_price, previous_price_timestamp, current_price_timestamp)
                VALUES (NEW.id_product, 0, OLD.price, NEW.price, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    previous_lowest_price = CASE
                        WHEN original_current_price_timestamp < NOW() - INTERVAL 30 DAY THEN OLD.price
                        ELSE current_price
                    END,
                    previous_price_timestamp = CASE
                        WHEN original_current_price_timestamp < NOW() - INTERVAL 30 DAY THEN previous_price_timestamp
                        ELSE current_price_timestamp
                    END,
                    current_price_timestamp = CASE
                        WHEN original_current_price_timestamp < NOW() - INTERVAL 30 DAY THEN NOW()
                        WHEN NEW.price < current_price THEN NOW()
                        ELSE original_current_price_timestamp
                    END,
                    current_price = CASE
                        WHEN original_current_price_timestamp < NOW() - INTERVAL 30 DAY THEN NEW.price
                        WHEN NEW.price < current_price THEN NEW.price
                        ELSE current_price
                    END;
            END IF;
        END",

            // Trigger for ps_product_attribute
            "CREATE TRIGGER after_product_attribute_update
        AFTER UPDATE ON " . _DB_PREFIX_ . "product_attribute
        FOR EACH ROW
        BEGIN
            DECLARE original_current_price_timestamp TIMESTAMP;
            
            DECLARE new_price DECIMAL(20, 6);
            DECLARE old_price DECIMAL(20, 6);

            SET new_price = NEW.price;
            SET old_price = OLD.price;

            SELECT current_price_timestamp INTO original_current_price_timestamp
            FROM {$tableName}
            WHERE id_product = NEW.id_product AND id_product_attribute = NEW.id_product_attribute
            ORDER BY current_price_timestamp DESC
            LIMIT 1;

            IF NEW.price <> OLD.price THEN
                INSERT INTO {$tableName} (id_product, id_product_attribute, previous_lowest_price, current_price, previous_price_timestamp, current_price_timestamp)
                VALUES (NEW.id_product, NEW.id_product_attribute, old_price, new_price, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    previous_lowest_price = CASE
                        WHEN original_current_price_timestamp < NOW() - INTERVAL 30 DAY THEN old_price
                        ELSE current_price
                    END,
                    previous_price_timestamp = CASE
                        WHEN original_current_price_timestamp < NOW() - INTERVAL 30 DAY THEN previous_price_timestamp
                        ELSE current_price_timestamp
                    END,
                    current_price_timestamp = CASE
                        WHEN original_current_price_timestamp < NOW() - INTERVAL 30 DAY THEN NOW()
                        WHEN new_price < current_price THEN NOW()
                        ELSE original_current_price_timestamp
                    END,
                    current_price = CASE
                        WHEN original_current_price_timestamp < NOW() - INTERVAL 30 DAY THEN new_price
                        WHEN new_price < current_price THEN new_price
                        ELSE current_price
                    END;
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
        $tableName = self::TABLE_NAME;

        $sql = new DbQuery();
        $sql->select('previous_lowest_price, current_price, previous_price_timestamp, current_price_timestamp');
        $sql->from($tableName);
        $sql->where('id_product = ' . (int)$id_product);
        $sql->where('id_product_attribute = 0');
        $result = Db::getInstance()->getRow($sql);

        if ($result === false || $result['previous_lowest_price'] === null) {
            $product = new Product($id_product);
            return $product->price;
        }

        return $result['previous_lowest_price'];
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
            }

            $lowestPrice = $this->getPreviousLowestProductPrice($id_product, $id_product_attribute);
            $this->context->smarty->assign(array(
                'lowestPrice' => $lowestPrice ? $lowestPrice : null,
            ));

            return $this->display(__FILE__, 'views/templates/hook/last_price_change.tpl');
        }

    }


}
