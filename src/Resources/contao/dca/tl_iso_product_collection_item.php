<?php

$dca = &$GLOBALS['TL_DCA']['tl_iso_product_collection_item'];

/**
 * Fields
 */
$dca['fields']['setQuantity'] = [
    'inputType' => 'text',
    'eval'      => ['tl_class' => 'w50', 'rgxp' => 'digit'],
    'sql'       => "varchar(255) NOT NULL default ''",
];
