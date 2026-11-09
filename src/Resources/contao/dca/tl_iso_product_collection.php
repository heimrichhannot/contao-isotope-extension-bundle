<?php

$dca = &$GLOBALS['TL_DCA']['tl_iso_product_collection'];

/**
 * Fields
 */
// serialized map of reserved stock (product id of the stock row => reserved quantity), managed by the StockManager
// via compare-and-swap SQL updates only
$dca['fields']['stock_reservation'] = [
    'eval' => ['doNotCopy' => true],
    'sql'  => 'blob NULL',
];
