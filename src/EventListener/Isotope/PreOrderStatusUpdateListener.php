<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\EventListener\Isotope;

use HeimrichHannot\IsotopeExtensionBundle\Manager\StockManager;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;
use HeimrichHannot\UtilsBundle\Database\DatabaseUtil;
use Isotope\Model\ProductCollection\Order;

class PreOrderStatusUpdateListener
{
    protected StockManager  $stockManager;
    protected ContainerUtil $containerUtil;
    protected DatabaseUtil  $databaseUtil;

    public function __construct(StockManager $stockManager, ContainerUtil $containerUtil, DatabaseUtil $databaseUtil)
    {
        $this->stockManager = $stockManager;
        $this->containerUtil = $containerUtil;
        $this->databaseUtil = $databaseUtil;
    }

    /**
     * Books the order's stock back when it changes into a stock-increasing status (e.g. new -> cancelled) and
     * takes it again (conditionally) when it changes out of one (e.g. cancelled -> new). The order's own shop
     * config defines the stock-increasing states.
     *
     * Known limit: if a later preOrderStatusUpdate hook cancels the transition, the stock change made here stays.
     *
     * @return bool true to cancel the status transition (not enough stock to leave the stock-increasing status)
     */
    public function updateStock(Order $order, $newsStatus)
    {
        // atm only for backend
        if ($this->containerUtil->isFrontend()) {
            return false;
        }

        $config = $this->stockManager->getOrderConfig($order);
        $wasIncreasing = $this->stockManager->isStockIncreaseOrderStatus($order->order_status, $config);
        $willIncrease = $this->stockManager->isStockIncreaseOrderStatus($newsStatus->id, $config);

        if (!$wasIncreasing && $willIncrease) {
            $this->stockManager->restockOrder($order);
        } elseif ($wasIncreasing && !$willIncrease && !$this->stockManager->unrestockOrder($order)) {
            // not enough stock for at least one item -> cancel the order status transition
            return true;
        }

        // don't cancel
        return false;
    }
}
