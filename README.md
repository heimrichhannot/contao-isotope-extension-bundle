### TODO: skip CheckoutPlus

# Contao Isotope Extension Bundle

This bundle offers additional functionality for the isotope ecommerce system.

## Features

### TODO SetQuantity and stock

- if these attributes have values, adding products to the cart or checking out is constrained by the stock left (depending on `setQuantity` if set)
- if the stock reaches 0, `shipping_exempt` on the product is set to true
- the stock validation (including setting of `shipping_exempt`) and the usage of sets can be configured in the shop config, the product type and the product (shop config has the lowest priority, product the highest)
- the usage of sets when computing the quantity to remove from the stock of a product can be configured in the shop config
- when removing an order or setting it to a certain status, the stock is decreased (configurable in the shop config)

### TODO Order report & Stock report

- enables to show orders and orderdetails in the front end
- enables to show the product stock in the front end

### TODO: ProductFilterPlus

- enables to filter for keywords or by status `shipping_exempt`
- enables sorting in alphabetical order and reverse alphabetical order

### TODO: ProductListPlus

- modifies the list, that it can show the filter and sorting results

### TODO: ProductListSlick -> tinyslider

- render products inside a slick content slider

### TODO CartLink

- a link to the current cart containing a badge showig the current quantity

### TODO ProductRanking

- a module for visualizing the development of sales for certain products

### Misc

- adds new possible attributes to products: `stock`, `initialStock`, `setQuantity`, `maxOrderSize`, `releaseDate`
- adds a new attribute type: `youtube`

## Installation

1. Install via composer (`composer require heimrichhannot/contao-isotope-bundle`) or contao manager
1. Update your database

## Known issues

- stock isn't validated product variants at the moment (products only)
