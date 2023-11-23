{if isset($lastPriceChange) && $lastPriceChange}
    <div class="price-logger-info">
        <p>Ostatnia zmiana ceny: {$lastPriceChange.price|escape:'html':'UTF-8'}</p>
        <p>Data zmiany: {$lastPriceChange.date_upd|escape:'html':'UTF-8'}</p>
    </div>
{/if}
