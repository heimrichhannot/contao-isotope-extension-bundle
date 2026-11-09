# Changelog

All notable changes to this project will be documented in this file.

## [0.1.4] - unreleased
- Fixed: overbooking of stock caused by read-modify-write updates and the ignored result of the `postCheckout` stock check; stock is now decreased atomically
- Fixed: stock is compared numerically in SQL
- Fixed: stock changes now target the parent product if a variant inherits the stock (variant attributes or the variant's inherit field)
- Fixed: deleting an order in the backend no longer turns unmanaged products (empty stock) into managed ones
- Fixed: the backend product form no longer overwrites concurrent stock changes (change is applied as delta)
- Fixed: status changes now use the shop config of the order itself
- Changed: stock is reserved in `preCheckout` and released when an incomplete order is deleted
- Changed: deleting an order in the backend no longer changes the stock of completed orders, whatever their status; only status changes do (cancel an order first to get its stock back)
- Changed: new column `tl_iso_product_collection.stock_reservation` -> run `contao:migrate` (or update the database)
- Deprecated: `StockManager::validateStockCheckout()`, `PostCheckoutListener::validateStockCheckout()` (no longer registered, does nothing)
- Added: `doctrine/dbal` requirement (`executeStatement`/`fetchOne` are used)
- Added: MSC label `stockReservationFailed`
- Note: orders that are in the middle of checkout during the deployment are not deducted from the stock

## [0.1.2] - 2023-09-13
- Fixed: missing action template variable for products in ProductListExtendedModule

## [0.1.1] - 2023-04-03
- Added: support for php version 8

## [0.1.0] - 2021-06-07

- major refactoring
  - `tl_iso_product_data` has been removed -> data needs to be transferred to `tl_iso_product`
  - Booking plan has been removed -> will be in an additional bundle maybe in the future
  - Product editor and related fields have been removed
- changed sorting of products to category in `ModuleStockReport`
