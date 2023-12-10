{assign var="taxRate" value=0.23}


{if isset($lastPrice)}
    <div class="previous-price-info">
        {assign var="prevPriceWithTax" value=$lastPrice * (1 + $taxRate)}
        <p class="previous-price"><del>{$prevPriceWithTax|number_format:2:',':' '|escape:'html':'UTF-8'}zł.</del></p>
    </div>
{/if}

{if isset($lowestPrice)}
    <div class="lowest-price-info">
        {assign var="priceWithTax" value=$lowestPrice * (1 + $taxRate)}
        <p>Najniższa cena w ostatnich 30 dniach: {$priceWithTax|number_format:2:',':' '|escape:'html':'UTF-8'}zł.</p>
    </div>
{/if}
