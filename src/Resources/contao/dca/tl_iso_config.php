<?php

$dca = &$GLOBALS['TL_DCA']['tl_iso_config'];

/**
 * Palettes
 */
$dca['palettes']['default'] = str_replace('{analytics_legend}', '{stock_legend},skipSets,skipStockValidation,skipStockEdit,skipExemptionFromShippingWhenStockEmpty,stockIncreaseOrderStates;{analytics_legend}', $dca['palettes']['default']);

/**
 * Fields
 */
$fields = [
    'skipStockValidation' => [
        'exclude'   => true,
        'inputType' => 'checkbox',
        'eval'      => ['tl_class' => 'w50'],
        'sql'       => "char(1) NOT NULL default ''",
    ],
    'skipStockEdit' => [
        'exclude'   => true,
        'inputType' => 'checkbox',
        'eval'      => ['tl_class' => 'w50'],
        'sql'       => "char(1) NOT NULL default ''",
    ],
    'skipExemptionFromShippingWhenStockEmpty' => [
        'exclude'   => true,
        'inputType' => 'checkbox',
        'eval'      => ['tl_class' => 'w50'],
        'sql'       => "char(1) NOT NULL default ''",
    ],
    'skipSets' => [
        'exclude'   => true,
        'inputType' => 'checkbox',
        'eval'      => ['tl_class' => 'w50'],
        'sql'       => "char(1) NOT NULL default ''",
    ],
    'stockIncreaseOrderStates' => [
        'exclude'          => true,
        'inputType'        => 'select',
        'eval'             => ['chosen' => true, 'multiple' => true, 'tl_class' => 'w50'],
        'sql'              => "blob NULL",
    ],
];

$dca['fields'] = array_merge(is_array($dca['fields']) ? $dca['fields'] : [], $fields);
