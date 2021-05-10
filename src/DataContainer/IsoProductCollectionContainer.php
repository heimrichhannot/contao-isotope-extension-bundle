<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\DataContainer;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\StringUtil;
use HeimrichHannot\IsotopeExtensionBundle\Manager\ProductDataManager;
use HeimrichHannot\IsotopeExtensionBundle\Manager\StockManager;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;

class IsoProductCollectionContainer
{
    protected ModelUtil          $modelUtil;
    protected StockManager       $stockManager;
    protected ProductDataManager $productDataManager;

    public function __construct(ModelUtil $modelUtil, StockManager $stockManager, ProductDataManager $productDataManager)
    {
        $this->modelUtil = $modelUtil;
        $this->stockManager = $stockManager;
        $this->productDataManager = $productDataManager;
    }

    /**
     * Increase stock after deleting an order.
     *
     * @Callback(table="tl_iso_product_collection", target="config.ondelete")
     */
    public function increaseStock(DataContainer $dc)
    {
        if (null === ($order = $this->modelUtil->findModelInstanceByPk('tl_iso_product_collection', $dc->id))) {
            return;
        }

        if (null === ($items = $this->modelUtil->findModelInstancesBy('tl_iso_product_collection_item', [
                'tl_iso_product_collection_item.pid=?',
            ], [$order->id]))) {
            return;
        }

        $config = $order->getRelated('config_id');

        // if the order had already been set to a stock increasing state,
        // the stock doesn't need to be increased again
        if (\in_array($order->order_status, StringUtil::deserialize($config->stockIncreaseOrderStates, true))) {
            return;
        }

        foreach ($items as $item) {
            $productData = $this->productDataManager->getProductData($item->product_id);
            $totalStockQuantity = $this->stockManager->getTotalCartQuantity(
                $item->quantity, $productData, null, null, $config
            );

            if ($totalStockQuantity) {
                $productData->stock += $totalStockQuantity;
                $productData->save();
            }
        }
    }
}
