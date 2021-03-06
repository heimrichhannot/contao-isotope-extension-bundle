<?php

$dca = &$GLOBALS['TL_DCA']['tl_module'];

/**
 * Palettes
 */
$dca['palettes']['__selector__'][] = 'iso_useAgb';
$dca['palettes']['__selector__'][] = 'iso_useConsent';

$dca['palettes'][\HeimrichHannot\IsotopeExtensionBundle\FrontendModule\ProductFilterExtendedModule::TYPE] =
    '{title_legend},name,headline,type;' .
    '{config_legend},iso_category_scope,iso_list_where,iso_enableLimit,iso_filterFields,iso_filterHideSingle,iso_searchFields,iso_searchAutocomplete,iso_sortingFields,iso_listingSortField,iso_listingSortDirection;' .
    '{template_legend},customTpl,iso_filterTpl,iso_includeMessages,iso_hide_list;{redirect_legend},jumpTo;' .
    '{reference_legend:hide},defineRoot;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';

$dca['palettes']['iso_productlist'] = str_replace('{config_legend}', '{config_legend},iso_description', $dca['palettes']['iso_productlist']);

$dca['palettes'][\HeimrichHannot\IsotopeExtensionBundle\FrontendModule\ProductListExtendedModule::TYPE] =
    '{title_legend},name,headline,type;' .
    '{config_legend},iso_description,numberOfItems,perPage,iso_category_scope,iso_list_where,iso_filterModules,iso_price_filter,iso_newFilter,iso_producttype_filter,iso_listingSortField,iso_listingSortDirection;' .
    '{redirect_legend},iso_addProductJumpTo,iso_jump_first;{reference_legend:hide},defineRoot;' .
    '{template_legend:hide},customTpl,iso_list_layout,iso_gallery,iso_cols,iso_use_quantity,iso_hide_list,iso_includeMessages,iso_emptyMessage,iso_emptyFilter,iso_buttons;' .
    '{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';

$dca['palettes'][\HeimrichHannot\IsotopeExtensionBundle\Controller\FrontendModule\IsoCartLinkModuleController::TYPE] =
    '{title_legend},name,headline,type;{config_legend},jumpTo,customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';

$dca['palettes'][\HeimrichHannot\IsotopeExtensionBundle\FrontendModule\DirectCheckoutModule::TYPE] =
    '{title_legend},name,headline,type;' .
    '{config_legend},iso_config_id,jumpTo,formHybridAsync,formHybridResetAfterSubmission,iso_direct_checkout_product_mode,iso_direct_checkout_products,nc_notification,iso_shipping_modules,iso_use_notes,iso_useAgb,iso_useConsent,formHybridCustomSubmit;' .
    '{template_legend},formHybridTemplate;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';

$dca['palettes'][\HeimrichHannot\IsotopeExtensionBundle\Controller\FrontendModule\IsoProductRankingModuleController::TYPE] =
    '{title_legend},name,headline,type;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';

$dca['palettes'][\HeimrichHannot\IsotopeExtensionBundle\FrontendModule\OrderDetailsExtendedModule::TYPE] = str_replace(
    'iso_loginRequired',
    'iso_loginRequired,iso_show_all_orders',
    $dca['palettes']['iso_orderdetails']
);

$dca['palettes'][\HeimrichHannot\IsotopeExtensionBundle\FrontendModule\ProductListSliderModule::TYPE] = str_replace(
    'iso_description', 'tinySliderConfig,iso_description', $dca['palettes'][\HeimrichHannot\IsotopeExtensionBundle\FrontendModule\ProductListExtendedModule::TYPE]
);

/**
 * Subpalettes
 */
$dca['subpalettes']['iso_useAgb']     = 'iso_agbText';
$dca['subpalettes']['iso_useConsent'] = 'iso_consentText';
$dca['subpalettes']['iso_useAgb']     = 'iso_agbText';
$dca['subpalettes']['iso_useConsent'] = 'iso_consentText';

/**
 * Fields
 */
$fields = [
    // order details
    'iso_show_all_orders'               => [
        'exclude'   => true,
        'inputType' => 'checkbox',
        'eval'      => ['tl_class' => 'w50'],
        'sql'       => "char(1) NOT NULL default ''",
    ],
    // filter, list
    'iso_price_filter'                  => [
        'exclude'   => true,
        'inputType' => 'select',
        'options'   => ['paid', 'free'],
        'reference' => &$GLOBALS['TL_LANG']['tl_module']['iso_price_filter'],
        'eval'      => ['tl_class' => 'w50 clr', 'includeBlankOption' => true],
        'sql'       => "varchar(64) NOT NULL default ''",
    ],
    'iso_producttype_filter'            => [
        'exclude'    => true,
        'inputType'  => 'select',
        'foreignKey' => 'tl_iso_producttype.name',
        'eval'       => ['tl_class' => 'clr', 'multiple' => true, 'chosen' => true, 'style' => 'width: 100%'],
        'sql'        => "blob NULL",
    ],
    'iso_description'                   => [
        'exclude'   => true,
        'search'    => true,
        'inputType' => 'textarea',
        'eval'      => ['rte' => 'tinyMCE', 'tl_class' => 'clr'],
        'sql'       => "text NULL",
    ],
    // direct checkout
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
                        'options_callback' => [\HeimrichHannot\IsotopeExtensionBundle\DataContainer\ModuleContainer::class, 'getProductsAsOptions'],
                        'eval'             => [
                            'mandatory'          => true,
                            'tl_class'           => 'long clr',
                            'chosen'             => true,
                            'includeBlankOption' => true,
                            'groupStyle'         => 'width: 97%;'
                        ],
                    ],
                    'iso_use_quantity'            => [
                        'label'     => &$GLOBALS['TL_LANG']['tl_module']['iso_use_quantity'],
                        'exclude'   => true,
                        'inputType' => 'checkbox',
                        'eval'      => [
                            'tl_class'   => 'w50',
                        ],
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
                            'groupStyle'         => 'width: 97%;'

                        ],
                        'sql'        => "int(10) unsigned NOT NULL default '0'",
                    ],
                    'iso_use_quantity'                 => [
                        'label'     => &$GLOBALS['TL_LANG']['tl_module']['iso_use_quantity'],
                        'exclude'   => true,
                        'inputType' => 'checkbox',
                        'eval'      => [
                            'tl_class'   => 'w50',
                        ],
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
        'eval'      => ['tl_class' => 'clr'],
        'sql'       => "char(1) NOT NULL default ''",
    ],
    'iso_useAgb'                        => [
        'exclude'   => true,
        'inputType' => 'checkbox',
        'eval'      => ['tl_class' => 'clr', 'submitOnChange' => true],
        'sql'       => "char(1) NOT NULL default ''",
    ],
    'iso_agbText'                       => [
        'exclude'   => true,
        'search'    => true,
        'inputType' => 'textarea',
        'eval'      => ['rte' => 'tinyMCE', 'tl_class' => 'clr'],
        'sql'       => "text NULL",
    ],
    'iso_useConsent'                    => [
        'exclude'   => true,
        'inputType' => 'checkbox',
        'eval'      => ['tl_class' => 'clr', 'submitOnChange' => true],
        'sql'       => "char(1) NOT NULL default ''",
    ],
    'iso_consentText'                   => [
        'exclude'   => true,
        'search'    => true,
        'inputType' => 'textarea',
        'eval'      => ['rte' => 'tinyMCE', 'tl_class' => 'clr'],
        'sql'       => "text NULL",
    ],
];

$dca['fields'] = array_merge(is_array($dca['fields']) ? $dca['fields'] : [], $fields);

$dca['fields']['jumpTo']['eval']['tl_class'] = 'w50 autoheight';
