<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\DataContainer;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use HeimrichHannot\IsotopeExtensionBundle\Manager\StockManager;
use HeimrichHannot\UtilsBundle\Database\DatabaseUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use Isotope\Model\ProductCollection\Order;

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
     * Books the stock of completed or reserved orders back (atomically, see StockManager::restockDeletedOrder(),
     * which deletes the order row itself so the stock is booked back only once). Orders in a stock-increasing
     * status (e.g. cancelled) already got their stock back.
     *
     * @Callback(table="tl_iso_product_collection", target="config.ondelete")
     */
    public function increaseStock(DataContainer $dc)
    {
        $order = $this->modelUtil->findModelInstanceByPk('tl_iso_product_collection', $dc->id);

        if (!$order instanceof Order) {
            return;
        }

        if ($this->stockManager->isStockIncreaseOrderStatus($order->order_status, $this->stockManager->getOrderConfig($order))) {
            return;
        }

        $this->stockManager->restockDeletedOrder($order);
    }
}
