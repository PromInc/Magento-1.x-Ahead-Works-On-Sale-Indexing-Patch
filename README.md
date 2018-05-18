# Magento-1.x-Ahead-Works-On-Sale-Indexing-Patch
Efficiency improvements to the Ahead Works On Sale &amp; Product Labels module reindexing process

This is a patch to the Ahead Works On Sale + Product Labels for Magento 1.
https://ecommerce.aheadworks.com/magento-extensions/on-sale.html
This applies to versions up to 2.5.5 (latest release at the time of this patch)

## Issue
This module re-indexes it's rules for products in an order at the time an order is saved.  It loops through the products in the order, and for each item it deletes existing rules and then re-adds the rules for that product.

According to Ahead Works support this feature exists because rules can be set based on the quantity in stock for a product.  This upates the quantity count in Magento based on this order, and thus sets the rules accordingly.

However this creates a lot of additional deletes and writes to the database at a sensitive time such as order creation and can at times create deadlocks in the database depending on the frequency in which orders are placed.

## New Approach
This patch takes a new approach.  It utilizes the same logic, however moves it to a cron job instead.  The cron job looks for the orders that have been placed since the cron task last ran.  It looks at the unique products that were purchased from those orders and re-indexes just those products.

## NOTE on Performance
The cron job may run slow for stores with a large order history, as the query to check for orders since the last re-index has a large table to check.  Magento Enterprise Edition archives older orders, and thus creates a smaller table for it to check for order information.  So this cron task will inherently run better on Magneto Enterprise.

If you do not run AW OnSale rules based on quantity, the best performance would be to disable the cron job, thus not re-indexing the AW OnSale rules until saving/changing rules.
