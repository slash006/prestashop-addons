{*
{if isset($lastPriceChange) && $lastPriceChange}
    <div class="price-logger-info">
        <p>Ostatnia zmiana: {$lastPriceChange.price|escape:'html':'UTF-8'} ({$lastPriceChange.date_upd|escape:'html':'UTF-8'})</p>
    </div>
{/if}
*}

{if isset($lowestPrice)}
    <div class="lowest-price-info">
        <p>Najniższa cena w ostatnich 30 dniach: {$lowestPrice|escape:'html':'UTF-8'}</p>
    </div>
{/if}
