# Contao Isotope Extension Bundle

This bundle offers additional functionality for the isotope ecommerce system.

## Features

### Stock management and validation (initialStock, stock, setQuantity, maxOrderSize)

- if these attributes have values, adding products to the cart or checking out is constrained by the stock left (depending on `setQuantity` if set)
- if the stock reaches 0, `shipping_exempt` on the product is set to true
- the stock validation (including setting of `shipping_exempt`) and the usage of sets can be configured in the shop config, the product type and the product (shop config has the lowest priority, product the highest)
- the usage of sets when computing the quantity to remove from the stock of a product can be configured in the shop config
- when removing an order or setting it to a certain status, the stock is decreased (configurable in the shop config)
- use the max order size attribute to restrict the order size

### Additional fields for products

Adds various fields to isotope products. These are organized in a new entity `tl_iso_product_data` so that we can use the model to save it.

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
