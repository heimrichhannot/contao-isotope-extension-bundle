<?php

/**
 * Isotope Hooks
 */
$GLOBALS['ISO_HOOKS']['preCheckout']['huhIsotopeExtensionBundle_validateStockCheckout']                    = [
    \HeimrichHannot\IsotopeExtensionBundle\EventListener\Isotope\PreCheckoutListener::class,
    'validateStockCheckout'
];
$GLOBALS['ISO_HOOKS']['postCheckout']['huhIsotopeExtensionBundle_validateStockCheckout']                   = [
    \HeimrichHannot\IsotopeExtensionBundle\EventListener\Isotope\PostCheckoutListener::class,
    'validateStockCheckout'
];
$GLOBALS['ISO_HOOKS']['addProductToCollection']['huhIsotopeExtensionBundle_validateStockCollectionAdd']    = [
    \HeimrichHannot\IsotopeExtensionBundle\EventListener\Isotope\AddProductToCollectionListener::class,
    '__invoke'
];
$GLOBALS['ISO_HOOKS']['postCheckout']['huhIsotopeExtensionBundle_sendOrderNotification']                   = [
    \HeimrichHannot\IsotopeExtensionBundle\EventListener\Isotope\PostCheckoutListener::class,
    'sendOrderNotification'
];
$GLOBALS['ISO_HOOKS']['postCheckout']['huhIsotopeExtensionBundle_setSetQuantity']                          = [
    \HeimrichHannot\IsotopeExtensionBundle\EventListener\Isotope\PostCheckoutListener::class,
    'setSetQuantity'
];
$GLOBALS['ISO_HOOKS']['updateItemInCollection']['huhIsotopeExtensionBundle_validateStockCollectionUpdate'] = [
    HeimrichHannot\IsotopeExtensionBundle\EventListener\Isotope\UpdateItemInCollectionListener::class,
    '__invoke'
];
$GLOBALS['ISO_HOOKS']['buttons'][]                                                                         = [
    \HeimrichHannot\IsotopeExtensionBundle\EventListener\Isotope\ButtonsListener::class,
    'addDownloadSingleProductButton'
];
// TODO - really needed!?
//$GLOBALS['ISO_HOOKS']['buttons'][]                                               = ['HeimrichHannot\IsotopeExtensionBundle\Backend\IsotopePlus', 'defaultButtons'];
$GLOBALS['ISO_HOOKS']['preOrderStatusUpdate']['huhIsotopeExtensionBundle_updateStock'] = [
    \HeimrichHannot\IsotopeExtensionBundle\EventListener\Isotope\PreOrderStatusUpdateListener::class,
    '__invoke'
];
$GLOBALS['ISO_HOOKS']['preCheckout']['huhIsotopeExtensionBundle_modifyShippingPrice']  = [
    \HeimrichHannot\IsotopeExtensionBundle\EventListener\Isotope\PreCheckoutListener::class,
    'modifyShippingPrice'
];

/**
 * Frontend modules
 */
$GLOBALS['FE_MOD']['isotope_extension_bundle'] = [
    \HeimrichHannot\IsotopeExtensionBundle\FrontendModule\ProductFilterExtendedModule::TYPE => 'HeimrichHannot\IsotopeExtensionBundle\FrontendModule\ProductFilterExtendedModule',
    \HeimrichHannot\IsotopeExtensionBundle\FrontendModule\OrderDetailsExtendedModule::TYPE  => 'HeimrichHannot\IsotopeExtensionBundle\FrontendModule\OrderDetailsExtendedModule',
    \HeimrichHannot\IsotopeExtensionBundle\FrontendModule\ProductListExtendedModule::TYPE   => 'HeimrichHannot\IsotopeExtensionBundle\FrontendModule\ProductListExtendedModule',
    \HeimrichHannot\IsotopeExtensionBundle\FrontendModule\DirectCheckoutModule::TYPE        => 'HeimrichHannot\IsotopeExtensionBundle\FrontendModule\DirectCheckoutModule',
    \HeimrichHannot\IsotopeExtensionBundle\FrontendModule\ProductListSliderModule::TYPE     => 'HeimrichHannot\IsotopeExtensionBundle\FrontendModule\ProductListSliderModule',
];

/**
 * CSS
 */
// TODO: needed???
//if (System::getContainer()->get('huh.utils.container')->isBackend()) {
//    $GLOBALS['TL_CSS'][] = 'bundles/heimrichhannotcontaoisotope/css/backend.css|static';
//}

/**
 * JS
 */
// TODO: needed?
//if (System::getContainer()->get('huh.utils.container')->isFrontend()) {
//    $GLOBALS['TL_JAVASCRIPT']['tablesorter']               = 'assets/components/tablesorter/js/tablesorter.min.js|static';
//    $GLOBALS['TL_JAVASCRIPT']['huh_contao-isotope-bundle'] = 'bundles/heimrichhannotcontaoisotope/js/contao.isotope-bundle.min.js|static';
//}
//if (\Contao\System::getContainer()->get('huh.utils.container')->isBackend()) {
//    $GLOBALS['TL_JAVASCRIPT']['huh_isotope_backend'] = 'bundles/heimrichhannotcontaoisotope/js/huh.isotope.backend.js|static';
//}

/**
 * Models
 */
$GLOBALS['TL_MODELS']['tl_iso_product']                 = \HeimrichHannot\IsotopeExtensionBundle\Model\ProductModel::class;
$GLOBALS['TL_MODELS']['tl_iso_product_data']            = \HeimrichHannot\IsotopeExtensionBundle\Model\ProductDataModel::class;
$GLOBALS['TL_MODELS']['tl_iso_product_collection_item'] = \HeimrichHannot\IsotopeExtensionBundle\Model\ProductCollectionItemModel::class;
