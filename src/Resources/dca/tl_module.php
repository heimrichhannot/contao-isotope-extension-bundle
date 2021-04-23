<?php

use HeimrichHannot\IsotopeExtensionBundle\FrontendModule\IsoDirectCheckoutModule;

$dca = &$GLOBALS['TL_DCA']['tl_module'];

/**
 * Palettes
 */
$dca['palettes']['__selector__'][] = 'iso_useAgb';
$dca['palettes']['__selector__'][] = 'iso_useConsent';

$dca['palettes'][IsoDirectCheckoutModule::class] =
    '{title_legend},name,headline,type;' .
    '{config_legend},jumpTo,formHybridAsync,formHybridResetAfterSubmission,iso_direct_checkout_product_mode,iso_direct_checkout_products,nc_notification,iso_shipping_modules,iso_use_notes,iso_useAgb,iso_useConsent,formHybridCustomSubmit;' .
    '{template_legend},formHybridTemplate;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';

/**
 * Subpalettes
 */
$dca['subpalettes']['iso_useAgb'] = 'iso_agbText';
$dca['subpalettes']['iso_useConsent']   = 'iso_consentText';

/**
 * Fields
 */
$fields = [
    'iso_use_quantity'                  => [
        'exclude'   => true,
        'inputType' => 'checkbox',
        'eval'      => ['tl_class' => 'w50'],
        'sql'       => "char(1) NOT NULL default ''",
    ],
    'iso_direct_checkout_product_mode'  => [
        'exclude'   => true,
        'inputType' => 'select',
        'options'   => ['product', 'product_type'],
        'default'   => 'product',
        'reference' => &$GLOBALS['TL_LANG']['tl_module']['iso_direct_checkout_product_mode'],
        'eval'      => ['mandatory' => true, 'tl_class' => 'w50 clr', 'submitOnChange' => true],
        'sql'       => "varchar(64) NOT NULL default ''",
    ],
    'iso_direct_checkout_products'      => [
        'inputType' => 'multiColumnEditor',
        'exclude'   => true,
        'eval'      => [
            'tl_class'          => 'long clr',
            'multiColumnEditor' => [
                'palettes' => [
                    'default' => 'iso_direct_checkout_product,iso_use_quantity',
                ],
                'fields'   => [
                    'iso_direct_checkout_product' => [
                        'label'            => &$GLOBALS['TL_LANG']['tl_module']['iso_direct_checkout_product'],
                        'exclude'          => true,
                        'inputType'        => 'select',
                        'options_callback' => [\HeimrichHannot\IsotopeSubscriptionsBundle\DataContainer\ModuleContainer::class, 'getProducts'],
                        'eval'             => [
                            'mandatory'          => true,
                            'tl_class'           => 'long clr',
                            'chosen'             => true,
                            'includeBlankOption' => true,
                        ],
                    ],
                    'iso_use_quantity'            => [
                        'label'     => &$GLOBALS['TL_LANG']['tl_module']['iso_use_quantity'],
                        'exclude'   => true,
                        'inputType' => 'checkbox',
                        'eval'      => ['tl_class' => 'w50'],
                    ],
                ],
            ],
        ],
        'sql'       => "blob NULL",
    ],
    'iso_direct_checkout_product_types' => [
        'inputType' => 'multiColumnEditor',
        'exclude'   => true,
        'eval'      => [
            'tl_class'          => 'long clr',
            'multiColumnEditor' => [
                'palettes' => [
                    'default' => 'iso_direct_checkout_product_type,iso_use_quantity',
                ],
                'fields'   => [
                    'iso_direct_checkout_product_type' => [
                        'label'      => &$GLOBALS['TL_LANG']['tl_module']['iso_direct_checkout_product_type'],
                        'exclude'    => true,
                        'inputType'  => 'select',
                        'foreignKey' => 'tl_iso_producttype.name',
                        'eval'       => [
                            'mandatory'          => true,
                            'tl_class'           => 'long clr',
                            'chosen'             => true,
                            'includeBlankOption' => true,
                        ],
                        'sql'        => "int(10) unsigned NOT NULL default '0'",
                    ],
                    'iso_use_quantity'                 => [
                        'label'     => &$GLOBALS['TL_LANG']['tl_module']['iso_use_quantity'],
                        'exclude'   => true,
                        'inputType' => 'checkbox',
                        'eval'      => ['tl_class' => 'w50'],
                        'sql'       => "char(1) NOT NULL default ''",
                    ]
                ],
            ],
        ],
        'sql'       => "blob NULL",
    ],
    'iso_use_notes'                     => [
        'exclude'   => true,
        'inputType' => 'checkbox',
        'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr'],
        'sql'       => "char(1) NOT NULL default ''",
    ],
    'iso_useAgb'                 => [
        'exclude'   => true,
        'inputType' => 'checkbox',
        'eval'      => ['tl_class' => 'clr', 'submitOnChange' => true],
        'sql'       => "char(1) NOT NULL default ''",
    ],
    'iso_agbText'                => [
        'exclude'   => true,
        'search'    => true,
        'inputType' => 'textarea',
        'eval'      => ['rte' => 'tinyMCE', 'tl_class' => 'clr'],
        'sql'       => "text NULL",
    ],
    'iso_useConsent'                   => [
        'exclude'   => true,
        'inputType' => 'checkbox',
        'eval'      => ['tl_class' => 'clr', 'submitOnChange' => true],
        'sql'       => "char(1) NOT NULL default ''",
    ],
    'iso_consentText'                  => [
        'exclude'   => true,
        'search'    => true,
        'inputType' => 'textarea',
        'eval'      => ['rte' => 'tinyMCE', 'tl_class' => 'clr'],
        'sql'       => "text NULL",
    ],
];

$dca['fields'] = array_merge(is_array($dca['fields']) ? $dca['fields'] : [], $fields);
