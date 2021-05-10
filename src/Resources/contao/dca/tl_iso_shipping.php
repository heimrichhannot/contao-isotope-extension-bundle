<?php

$dca = &$GLOBALS['TL_DCA']['tl_iso_shipping'];

$dca['palettes']['flat']  = str_replace('product_types_condition', 'product_types_condition,skipProducts', $dca['palettes']['flat']);

$dca['fields']['skipProducts'] = [
    'exclude'          => true,
    'inputType'        => 'select',
    'eval'             => ['multiple' => true, 'size' => 8, 'chosen' => true, 'tl_class' => 'clr w50 w50h'],
    'sql'              => "blob NULL",
];
