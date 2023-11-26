{*
{if isset($lastPriceChange) && $lastPriceChange}
    <div class="price-logger-info">
        <p>Ostatnia zmiana: {$lastPriceChange.price|escape:'html':'UTF-8'} ({$lastPriceChange.date_upd|escape:'html':'UTF-8'})</p>
    </div>
{/if}
*}

{assign var="taxRate" value=0.23}


{if isset($previousPrice)}
    <div class="previous-price-info">
        {assign var="prevPriceWithTax" value=$previousPrice * (1 + $taxRate)}
        <p class="previous-price"><del>{$prevPriceWithTax|number_format:2:',':' '|escape:'html':'UTF-8'}</del></p>
    </div>
{/if}

{if isset($lowestPrice)}
    <div class="lowest-price-info">
        {assign var="priceWithTax" value=$lowestPrice * (1 + $taxRate)}
        <p>Najniższa cena w ostatnich 30 dniach: {$priceWithTax|number_format:2:',':' '|escape:'html':'UTF-8'}zł.</p>
    </div>
{/if}
