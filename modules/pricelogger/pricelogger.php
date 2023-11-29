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
        $this->version = '1.0.12';
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
                VALUES (NEW.id_product, 0, OLD.price, NEW.price, NULL, NOW())
                ON DUPLICATE KEY UPDATE
                    previous_lowest_price = CASE
                        WHEN original_current_price_timestamp < NOW() - INTERVAL 30 DAY THEN NEW.price
                        WHEN NEW.price < current_price THEN current_price
                        ELSE previous_lowest_price
                    END,
                    previous_price_timestamp = CASE
                        WHEN original_current_price_timestamp < NOW() - INTERVAL 30 DAY THEN NOW()
                        WHEN NEW.price < current_price THEN current_price_timestamp
                        ELSE previous_price_timestamp
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

            SELECT current_price_timestamp INTO original_current_price_timestamp
            FROM {$tableName}
            WHERE id_product = NEW.id_product AND id_product_attribute = NEW.id_product_attribute
            ORDER BY current_price_timestamp DESC
            LIMIT 1;

            IF NEW.price <> OLD.price THEN
                INSERT INTO {$tableName} (id_product, id_product_attribute, previous_lowest_price, current_price, previous_price_timestamp, current_price_timestamp)
                VALUES (NEW.id_product, NEW.id_product_attribute, OLD.price, NEW.price, NULL, NOW())
                ON DUPLICATE KEY UPDATE
                    previous_lowest_price = CASE
                        WHEN original_current_price_timestamp < NOW() - INTERVAL 30 DAY THEN NEW.price
                        WHEN NEW.price < current_price THEN current_price
                        ELSE previous_lowest_price
                    END,
                    previous_price_timestamp = CASE
                        WHEN original_current_price_timestamp < NOW() - INTERVAL 30 DAY THEN NOW()
                        WHEN NEW.price < current_price THEN current_price_timestamp
                        ELSE previous_price_timestamp
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


    public function hookActionProductUpdate($params)
    {

    }


    public function hookDisplayProductPriceBlock($params)
    {
        if ($params['type'] == 'after_price') {

            $this->context->smarty->assign(array(
                'lastPriceChange' => null,
                'lowestPrice' => null,
            ));

            return $this->display(__FILE__, 'views/templates/hook/displayPrice.tpl');
        }
    }


}
