DELIMITER $$

CREATE TRIGGER before_product_update
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
END$$

DELIMITER ;
