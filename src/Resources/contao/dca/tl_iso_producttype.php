<?php

$dca = &$GLOBALS['TL_DCA']['tl_iso_producttype'];

/**
 * Palettes
 */
$dca['palettes']['__selector__'][] = 'sendOrderNotification';
$dca['palettes']['__selector__'][] = 'overrideStockShopConfig';

$dca['palettes']['standard'] = str_replace(['{description_legend:hide}', 'shipping_exempt'], ['{email_legend},sendOrderNotification;{description_legend:hide}', 'shipping_exempt,overrideStockShopConfig'], $dca['palettes']['standard']);

/**
 * Subpalettes
 */
$dca['subpalettes']['sendOrderNotification']   = 'orderNotification,removeOtherProducts';
$dca['subpalettes']['overrideStockShopConfig'] = 'skipStockValidation,skipStockEdit,skipExemptionFromShippingWhenStockEmpty';

/**
 * Fields
 */
$fields = [
    'sendOrderNotification'   => [
        'exclude'   => true,
        'inputType' => 'checkbox',
        'eval'      => ['tl_class' => 'w50', 'submitOnChange' => true],
        'sql'       => "char(1) NOT NULL default ''",
    ],
    'orderNotification'       => [
        'exclude'          => true,
        'inputType'        => 'select',
        'eval'             => ['includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50', 'mandatory' => true],
        'sql'              => "int(10) unsigned NOT NULL default '0'",
    ],
    'removeOtherProducts'     => [
        'exclude'   => true,
        'inputType' => 'checkbox',
        'eval'      => ['tl_class' => 'w50'],
        'sql'       => "char(1) NOT NULL default ''",
    ],
    'overrideStockShopConfig' => [
        'exclude'   => true,
        'inputType' => 'checkbox',
        'eval'      => ['tl_class' => 'w50', 'submitOnChange' => true],
        'sql'       => "char(1) NOT NULL default ''",
    ]
];

$dca['fields'] = array_merge(is_array($dca['fields']) ? $dca['fields'] : [], $fields);

\Contao\Controller::loadDataContainer('tl_iso_config');
\Contao\System::loadLanguageFile('tl_iso_config');

$dca['fields']['skipStockValidation']                     = $GLOBALS['TL_DCA']['tl_iso_config']['fields']['skipStockValidation'];
$dca['fields']['skipStockEdit']                           = $GLOBALS['TL_DCA']['tl_iso_config']['fields']['skipStockEdit'];
$dca['fields']['skipExemptionFromShippingWhenStockEmpty'] = $GLOBALS['TL_DCA']['tl_iso_config']['fields']['skipExemptionFromShippingWhenStockEmpty'];
