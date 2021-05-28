<?php

$dca = &$GLOBALS['TL_DCA']['tl_iso_product'];

/**
 * List
 */
$dca['list']['label']['fields'] = array_merge($dca['list']['label']['fields'], ['stock', 'initialStock']);

/**
 * Fields
 */
$fields = [
    'initialStock'            => [
        'label'      => &$GLOBALS['TL_LANG']['tl_iso_product']['initialStock'],
        'inputType'  => 'text',
        'eval'       => ['mandatory' => true, 'tl_class' => 'w50', 'rgxp' => 'digit'],
        'attributes' => ['legend' => 'inventory_legend'],
        'sql'        => "varchar(255) NOT NULL default ''"
    ],
    'stock'                   => [
        'label'      => &$GLOBALS['TL_LANG']['tl_iso_product']['stock'],
        'inputType'  => 'text',
        'eval'       => ['mandatory' => true, 'tl_class' => 'w50', 'rgxp' => 'digit'],
        'attributes' => ['legend' => 'inventory_legend', 'fe_sorting' => true],
        'sql'        => "varchar(255) NOT NULL default ''",
    ],
    'setQuantity'             => [
        'label'      => &$GLOBALS['TL_LANG']['tl_iso_product']['setQuantity'],
        'inputType'  => 'text',
        'eval'       => ['mandatory' => true, 'tl_class' => 'w50', 'rgxp' => 'digit'],
        'attributes' => ['legend' => 'inventory_legend', 'fe_sorting' => true],
        'sql'        => "varchar(255) NOT NULL default ''",
    ],
    'releaseDate'             => [
        'label'      => &$GLOBALS['TL_LANG']['tl_iso_product']['releaseDate'],
        'exclude'    => true,
        'inputType'  => 'text',
        'default'    => time(),
        'eval'       => ['rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
        'attributes' => ['legend' => 'publish_legend', 'fe_sorting' => true],
        'sql'        => "varchar(10) NOT NULL default ''",
    ],
    'maxOrderSize'            => [
        'label'      => &$GLOBALS['TL_LANG']['tl_iso_product']['maxOrderSize'],
        'inputType'  => 'text',
        'eval'       => ['tl_class' => 'w50', 'rgxp' => 'digit'],
        'attributes' => ['legend' => 'inventory_legend'],
        'sql'        => "varchar(255) NOT NULL default ''",
    ],
    'overrideStockShopConfig' => [
        'label'      => &$GLOBALS['TL_LANG']['tl_iso_product']['overrideStockShopConfig'],
        'exclude'    => true,
        'inputType'  => 'checkbox',
        'eval'       => ['tl_class' => 'w50'],
        'attributes' => ['legend' => 'shipping_legend'],
        'sql'        => "char(1) NOT NULL default ''",
    ],
    'jumpTo'                  => [
        'label'      => &$GLOBALS['TL_LANG']['tl_iso_product']['jumpTo'],
        'exclude'    => true,
        'inputType'  => 'pageTree',
        'foreignKey' => 'tl_page.title',
        'eval'       => ['fieldType' => 'radio'],
        'sql'        => "int(10) unsigned NOT NULL default '0'",
        'attributes' => ['legend' => 'general_legend'],
        'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
    ],
    'addedBy'                 => [
        'label'      => &$GLOBALS['TL_LANG']['tl_iso_product']['addedBy'],
        'inputType'  => 'select',
        'exclude'    => true,
        'search'     => true,
        'default'    => FE_USER_LOGGED_IN ? \Contao\FrontendUser::getInstance()->id : \Contao\BackendUser::getInstance()->id,
        'foreignKey' => 'tl_member.username',
        'eval'       => ['doNotCopy' => true, 'mandatory' => true, 'chosen' => true, 'tl_class' => 'w50'],
        'relation'   => ['type' => 'hasOne', 'load' => 'eager'],
        'attributes' => ['fe_sorting' => true, 'fe_search' => true],
        'sql'        => "int(10) unsigned NOT NULL default '0'",
    ],
    'downloadCount'           => [
        'label'     => &$GLOBALS['TL_LANG']['tl_iso_product']['downloadCount'],
        'inputType' => 'text',
        'eval'      => ['tl_class' => 'w50', 'rgxp' => 'digit'],
        'sql'       => "int(10) unsigned NOT NULL default '0'",
    ],
    'relevance'               => [
        'label'     => &$GLOBALS['TL_LANG']['tl_iso_product']['relevance'],
        'inputType' => 'text',
        'eval'      => ['tl_class' => 'w50', 'rgxp' => 'digit'],
        'sql'       => "int(10) unsigned NOT NULL default '0'",
    ],
];

$dca['fields'] = array_merge(is_array($dca['fields']) ? $dca['fields'] : [], $fields);

\Contao\System::loadLanguageFile('tl_iso_config');
\Contao\Controller::loadDataContainer('tl_iso_config');

$dca['fields']['skipStockValidation']                                   = $GLOBALS['TL_DCA']['tl_iso_config']['fields']['skipStockValidation'];
$dca['fields']['skipStockValidation']['attributes']                     = ['legend' => 'shipping_legend'];
$dca['fields']['skipStockEdit']                                         = $GLOBALS['TL_DCA']['tl_iso_config']['fields']['skipStockEdit'];
$dca['fields']['skipStockEdit']['attributes']                           = ['legend' => 'shipping_legend'];
$dca['fields']['skipExemptionFromShippingWhenStockEmpty']               = $GLOBALS['TL_DCA']['tl_iso_config']['fields']['skipExemptionFromShippingWhenStockEmpty'];
$dca['fields']['skipExemptionFromShippingWhenStockEmpty']['attributes'] = ['legend' => 'shipping_legend'];

$dca['fields']['dateAdded']['sorting'] = true;
