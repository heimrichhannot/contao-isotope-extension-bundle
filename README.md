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
- when removing an order or setting it to a certain status, the stock is decreased (configurable in the shop config)
- use the max order size attribute to restrict the order size

### Stock reservation

- the stock is reserved atomically when the checkout starts (`preCheckout`), so concurrent orders cannot overbook a product; if a product is no longer available, the checkout is aborted and the message `stockReservationFailed` is shown
- the reserved quantities are stored on the order in `tl_iso_product_collection.stock_reservation` (serialized map product id => quantity); after adding the column run `contao:migrate`
- the reservation is released when an incomplete order is deleted (backend or Isotope's cron) and re-applied or released on status changes according to the shop config
- completed orders keep their reservation (it is their booking)

#### Known limitations

- locked orders that were never completed (`type='order' AND locked>0 AND order_status=0`) keep their reservation and are not shown in the backend order list. Find them with:

  ```sql
  SELECT id, tstamp, stock_reservation FROM tl_iso_product_collection
  WHERE type='order' AND locked>0 AND order_status=0 AND stock_reservation IS NOT NULL;
  ```

  To book the stock back, unserialize `stock_reservation` (map product id => quantity) and, for every entry, run

  ```sql
  UPDATE tl_iso_product SET stock = CAST(stock AS SIGNED) + <qty> WHERE id = <product id> AND stock <> '';
  ```

  then set `stock_reservation = NULL` on the order.
- if a later `preCheckout` hook (e.g. subscriptions) cancels the checkout, the reservation stays on the draft order until the customer retries or Isotope's cron deletes it (`iso_orderTimeout`, default 7 days)
- backend product form: a stock change is applied as delta against the value displayed in the form; a window of a few milliseconds remains, and if two tabs edit the same product the one opened last wins
- a concurrent status change and backend delete of the same order may restock twice
- undoing a backend order delete restores the reservation map without taking the stock again

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
