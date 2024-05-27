<div class="product-groups">
    {$productID = $product.id_product}
    {foreach from=$productGroups item=group}
        <div class="product-group">
            {foreach from=$group.products item=product}
                <div class="product-row">
                    <a href="{$product.url}">
                        <img class="thumbnail{if $productID == $product.id_product} selected{/if}"
                            src="{$product.cover.bySize.home_default.url}" alt="{$product.name}" title="{$product.name}" />
                    </a>
                </div>
            {/foreach}
        </div>
    {/foreach}
</div>