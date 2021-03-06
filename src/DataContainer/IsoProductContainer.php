<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\DataContainer;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use HeimrichHannot\RequestBundle\Component\HttpFoundation\Request;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;
use HeimrichHannot\UtilsBundle\Database\DatabaseUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use Isotope\Model\Product;

class IsoProductContainer
{
    protected ModelUtil     $modelUtil;
    protected ContainerUtil $containerUtil;
    protected Request       $request;
    protected DatabaseUtil  $databaseUtil;

    public function __construct(ModelUtil $modelUtil, ContainerUtil $containerUtil, Request $request, DatabaseUtil $databaseUtil)
    {
        $this->modelUtil = $modelUtil;
        $this->containerUtil = $containerUtil;
        $this->request = $request;
        $this->databaseUtil = $databaseUtil;
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

        $this->databaseUtil->update('tl_iso_product', [
            'relevance' => $product->relevance + 1,
        ], 'tl_iso_product.id=?', [$product->id]);
    }
}
