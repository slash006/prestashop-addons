DELIMITER $$

CREATE TRIGGER before_product_attribute_update
    BEFORE UPDATE ON ps_product_attribute
    FOR EACH ROW
BEGIN
    DECLARE currentLowestPrice DECIMAL(20,6);
    DECLARE basePrice DECIMAL(20,6);
    DECLARE lowestPriceTime DATETIME;
    DECLARE lastPriceTime DATETIME;

    SELECT price INTO basePrice FROM ps_product WHERE id_product = OLD.id_product;

    IF OLD.price <> NEW.price THEN
        IF NOT EXISTS (SELECT 1 FROM ps_price_log WHERE id_product = OLD.id_product AND id_product_attribute = OLD.id_product_attribute) THEN
            INSERT INTO ps_price_log (id_product, id_product_attribute, lowest_price, last_price, lowest_timestamp, last_timestamp)
            VALUES (OLD.id_product, OLD.id_product_attribute, basePrice + OLD.price, basePrice + NEW.price, NOW(), NOW());
    ELSE
    SELECT lowest_price, lowest_timestamp, last_timestamp INTO currentLowestPrice, lowestPriceTime, lastPriceTime FROM ps_price_log WHERE id_product = OLD.id_product AND id_product_attribute = OLD.id_product_attribute;

    IF OLD.price + basePrice < currentLowestPrice THEN
    UPDATE ps_price_log SET lowest_price = OLD.price + basePrice, lowest_timestamp = lastPriceTime WHERE id_product = OLD.id_product AND id_product_attribute = OLD.id_product_attribute;
END IF;

UPDATE ps_price_log SET last_price = NEW.price + basePrice, last_timestamp = NOW() WHERE id_product = OLD.id_product AND id_product_attribute = OLD.id_product_attribute;

END IF;
END IF;
END$$

DELIMITER ;
