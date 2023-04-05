# Changelog

All notable changes to this project will be documented in this file.

## [0.1.1] - 2023-04-03
- Added: support for php version 8

## [0.1.0] - 2021-06-07

- major refactoring
  - `tl_iso_product_data` has been removed -> data needs to be transferred to `tl_iso_product`
  - Booking plan has been removed -> will be in an additional bundle maybe in the future
  - Product editor and related fields have been removed
- changed sorting of products to category in `ModuleStockReport`
