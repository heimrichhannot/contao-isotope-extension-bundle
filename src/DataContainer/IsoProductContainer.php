<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\DataContainer;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use HeimrichHannot\IsotopeExtensionBundle\Manager\ProductDataManager;
use HeimrichHannot\RequestBundle\Component\HttpFoundation\Request;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use Isotope\Model\Product;

class IsoProductContainer
{
    protected ModelUtil          $modelUtil;
    protected ContainerUtil      $containerUtil;
    protected Request            $request;
    protected ProductDataManager $productDataManager;

    public function __construct(ModelUtil $modelUtil, ContainerUtil $containerUtil, Request $request, ProductDataManager $productDataManager)
    {
        $this->modelUtil = $modelUtil;
        $this->containerUtil = $containerUtil;
        $this->request = $request;
        $this->productDataManager = $productDataManager;
    }

    /**
     * @Callback(table="tl_iso_product", target="config.onload")
     */
    public function updateRelevance(DataContainer $dc)
    {
        if ($this->containerUtil->isBackend()) {
            return;
        }

        if (null === ($product = $this->modelUtil->findOneModelInstanceBy('tl_iso_product', [
                'tl_iso_product.sku=?',
            ], [
                $this->request->getGet('auto_item'),
            ]))) {
            return;
        }

        ++$product->relevance;
        $product->save();
    }

    /**
     * Save product data fields to product data table -> no dca callback annotation for the callback
     * necessary since it's added in ProductDataManager::getProductDataFields().
     *
     * @param $value
     *
     * @return mixed
     */
    public function saveMetaFields($value, DataContainer $dc)
    {
        if ('tl_iso_product' === !$dc->table) {
            return $value;
        }

        if (!\array_key_exists($field = $dc->field, $this->productDataManager->getProductDataFields())) {
            return $value;
        }

        $productData = $this->productDataManager->getProductData($dc->id);
        $productData->$field = $value;
        $productData->tstamp = time();
        $productData->save();

        return $value;
    }
}