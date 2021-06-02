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
use HeimrichHannot\IsotopeExtensionBundle\Manager\StockManager;
use HeimrichHannot\UtilsBundle\Database\DatabaseUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;

class IsoProductCollectionContainer
{
    protected ModelUtil    $modelUtil;
    protected StockManager $stockManager;
    protected DatabaseUtil $databaseUtil;

    public function __construct(ModelUtil $modelUtil, StockManager $stockManager, DatabaseUtil $databaseUtil)
    {
        $this->modelUtil = $modelUtil;
        $this->stockManager = $stockManager;
        $this->databaseUtil = $databaseUtil;
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
            if (null === ($product = $this->modelUtil->findModelInstanceByPk('tl_iso_product', $item->product_id))) {
                continue;
            }

            $totalStockQuantity = $this->stockManager->getTotalCartQuantity(
                $item->quantity, $product, null, null, $config
            );

            if ($totalStockQuantity) {
                $this->databaseUtil->update('tl_iso_product', [
                    'stock' => $product->stock + $totalStockQuantity,
                ], 'tl_iso_product.id=?', [$product->id]);
            }
        }
    }
}
