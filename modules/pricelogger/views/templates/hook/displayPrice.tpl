{assign var="taxRate" value=0.23}


{if isset($previous_price)}
    <div class="previous-price-info">
        {assign var="prevPriceWithTax" value=$previous_price * (1 + $taxRate)}
{*            <p class="previous-price"><del>{$prevPriceWithTax|number_format:2:',':' '|escape:'html':'UTF-8'}</del></p>*}
        <p>Najniższa cena w ostatnich 30 dniach: {$prevPriceWithTax|number_format:2:',':' '|escape:'html':'UTF-8'}zł.</p>
    </div>
{/if}
{*
{if isset($lowest_price)}
    <div class="lowest-price-info">
        {assign var="priceWithTax" value=$lowest_price * (1 + $taxRate)}
        <p>Najniższa cena w ostatnich 30 dniach: {$priceWithTax|number_format:2:',':' '|escape:'html':'UTF-8'}zł.</p>
    </div>
{/if}*}
