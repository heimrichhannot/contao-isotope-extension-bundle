# Contao Isotope Extension Bundle

This bundle offers additional functionality for the isotope ecommerce system.

## Migration from contao-isotope-bundle

see UPGRADE.md

## Features

### Stock management and validation (initialStock, stock, setQuantity, maxOrderSize)

- if these attributes have values, adding products to the cart or checking out is constrained by the stock left (depending on `setQuantity` if set)
- if the stock reaches 0, `shipping_exempt` on the product is set to true
- the stock validation (including setting of `shipping_exempt`) and the usage of sets can be configured in the shop config, the product type and the product (shop config has the lowest priority, product the highest)
- the usage of sets when computing the quantity to remove from the stock of a product can be configured in the shop config
- when an order is set to a stock-increasing status (e.g. cancelled), its stock is booked back; when it leaves that status, the stock is decreased again (configurable in the shop config)
- deleting an order does not change the stock (cancel it first to get its stock back)
- use the max order size attribute to restrict the order size

### Stock reservation

- the stock is reserved atomically when the checkout starts (`preCheckout`), so concurrent orders cannot overbook a product; if a product is no longer available or the max order size is exceeded, the checkout is aborted with a message (`stockEmpty`, `stockExceeded` or `maxOrderSizeExceeded`; `stockReservationFailed` only in rare cases, e.g. if the stock changed during the reservation)
- if a variant inherits the stock from its parent product (stock is no variant attribute, or it is listed in the variant's inherit field), the stock of the parent product is changed
- the reserved quantities are stored on the order in `tl_iso_product_collection.stock_reservation` (serialized map product id => quantity, e.g. `a:1:{i:42;i:3;}` = product 42, quantity 3; `a:0:{}` if nothing needed to be reserved); after adding the column run `contao:migrate`
- the reservation is released when an incomplete order is deleted (backend or Isotope's cron)
- completed orders keep their reservation (it is their booking): only status changes book it back or take it again (according to the shop config), deleting a completed order never changes the stock
- two concurrent submits of the same checkout with a changed cart are serialised by Symfony's session locking in practice

#### Known limitations

- locked orders that were never completed (`type='order' AND locked>0 AND order_status=0`) keep their reservation and are not shown in the backend order list. Find them with:

  ```sql
  SELECT id, tstamp, stock_reservation FROM tl_iso_product_collection
  WHERE type='order' AND locked>0 AND order_status=0 AND stock_reservation IS NOT NULL
    AND tstamp < UNIX_TIMESTAMP() - 86400;
  ```

  The `tstamp` filter leaves out checkouts that may still be in progress (e.g. a payment that is not confirmed yet): never book back orders that are still in flight.

  To book the stock back, unserialize `stock_reservation` (map product id => quantity, e.g. `a:1:{i:42;i:3;}` = product 42, quantity 3) and, for every entry, run

  ```sql
  UPDATE tl_iso_product SET stock = CAST(NULLIF(stock, '') AS SIGNED) + <qty> WHERE id = <product id> AND stock <> '';
  ```

  then set `stock_reservation = NULL` on the order.
- if a later `preCheckout` hook (e.g. subscriptions) cancels the checkout, the reservation stays on the draft order until the customer retries or Isotope's cron deletes it (`iso_orderTimeout`, default 7 days)
- if a later `preOrderStatusUpdate` hook cancels a status transition, the stock change made by this bundle stays
- backend product form: a stock change is applied as delta against the value displayed in the form; a window of a few milliseconds remains, and if two tabs edit the same product the one opened last wins
- backend product form: if another field fails validation, the stock change is already applied; resubmitting the form is safe (the same value is not applied again, a corrected value is applied as delta)
- undoing the backend delete of an incomplete order restores its reservation map without taking the stock again (incomplete orders are not shown in the backend order list)

### Additional fields for products

Adds various fields to isotope products.

### Frontend modules

#### Cart Link

- a link to the current cart containing a badge showing the current quantity

#### Extended Order Details

- adds member login check if the order is linked to a member
- adds some extra info to the template (see `OrderDetailsExtendedModule` for further info)

#### Extended Product Filter

- enables to filter for keywords or by status `shipping_exempt`
- enables sorting in alphabetical order and reverse alphabetical order

#### Extended Product List

- modifies the list, so that it can show the filter and sorting results

#### Product List Slider

- render products inside a content slider (uses [ganlanyuan/tiny-slider](https://github.com/ganlanyuan/tiny-slider))

#### Product Ranking

- a module for visualizing the development of sales for certain products

#### Stock Report

- displays the product stocks in the front end

## Installation

1. Install via composer (`composer require heimrichhannot/contao-isotope-extension-bundle`) or contao manager
1. Update your database

## Known issues

- stock isn't validated product variants at the moment (products only)
