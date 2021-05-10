<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\DataContainer;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use Isotope\Frontend\ProductAction\Registry;

class ModuleContainer
{
    /**
     * @var ModelUtil
     */
    protected ModelUtil       $modelUtil;
    protected ContaoFramework $framework;

    public function __construct(ModelUtil $modelUtil, ContaoFramework $framework)
    {
        $this->modelUtil = $modelUtil;
        $this->framework = $framework;
    }

    /**
     * @Callback(table="tl_module", target="config.onload")
     */
    public function modifyPalette($dc)
    {
        if (null === ($module = $this->modelUtil->findModelInstanceByPk('tl_module', $dc->id))) {
            return;
        }

        // TODO: remove?
//        $dc->objModule = $module;

        $dca = &$GLOBALS['TL_DCA']['tl_module'];

        if ('iso_direct_checkout' === $module->type) {
            if ('product_type' == $module->iso_direct_checkout_product_mode) {
                $dca['palettes']['iso_direct_checkout'] = str_replace('iso_direct_checkout_products,', 'iso_direct_checkout_product_types,iso_listingSortField,iso_listingSortDirection,', $dca['palettes']['iso_direct_checkout']);

                // fix field labels
                $dca['fields']['iso_listingSortField']['label'] = &$GLOBALS['TL_LANG']['tl_module']['iso_direct_checkout_listingSortField'];
                $dca['fields']['iso_listingSortDirection']['label'] = &$GLOBALS['TL_LANG']['tl_module']['iso_direct_checkout_listingSortDirection'];
            }

            $dca['fields']['iso_shipping_modules']['inputType'] = 'select';
            $dca['fields']['iso_shipping_modules']['eval']['includeBlankOption'] = true;
            $dca['fields']['iso_shipping_modules']['eval']['multiple'] = false;
            $dca['fields']['iso_shipping_modules']['eval']['tl_class'] = 'w50';

            $dca['fields']['formHybridTemplate']['default'] = 'formhybrid_direct_checkout';
        }
    }

    public static function getProductsAsOptions()
    {
        $products = \Isotope\Model\Product::findPublished();

        if (null === $products) {
            return [];
        }

        $productTypeLabels = [];
        $options = [];

        while ($products->next()) {
            // check for label cache
            if (isset($productTypeLabels[$products->type])) {
                $productTypeLabel = $productTypeLabels[$products->type];
            } else {
                if (null !== ($objProductType = \Isotope\Model\ProductType::findByPk($products->type))) {
                    $productTypeLabel = $objProductType->name;
                    $productTypeLabels[$objProductType->id] = $objProductType->name;
                }
            }

            $options[$products->id] = $productTypeLabel.' - '.$products->name;
        }

        asort($options);

        return $options;
    }

    /**
     * @Callback(table="tl_module", target="fields.iso_buttons.options")
     */
    public function getButtonsAsOptions()
    {
        $options['downloadSingleProduct'] = $GLOBALS['TL_LANG']['MSC']['buttonLabel']['downloadSingleProduct'];

        foreach (Registry::all() as $action) {
            $options[$action->getName()] = $action->getLabel();
        }

        return $options;
    }
}
