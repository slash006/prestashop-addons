# Free Module for PrestaShop 1.7.X Based Stores

## Description
pricelogger is a free module for displaying the lowest price within 30 days of a promotion, ensuring compliance with the EU Omnibus Directive.

## Technical Information
The module installs MySQL triggers responsible for monitoring product and attribute prices. If your server does not support such technology, the module will not work.

## Roadmap
Currently, only the Polish language and a VAT rate of 23% are supported. The module is based on prices excluding taxes, and the applicable tax can be changed in the template itself - `last_price_change.tpl`. This will be added as a configurable parameter in future versions.

## License
This module is released under the MIT License.

