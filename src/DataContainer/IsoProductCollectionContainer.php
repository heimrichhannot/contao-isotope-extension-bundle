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
     * Releases the stock reservation of a draft order that is deleted in the backend.
     *
     * Deleting an order is housekeeping, only its status decides about the stock: completed orders are never
     * booked back on delete, whatever their status (cancel them first to get their stock back). Drafts that
     * reserved stock in preCheckout release it, atomically (see StockManager::releaseDeletedDraftReservation()).
     *
     * @Callback(table="tl_iso_product_collection", target="config.ondelete")
     */
    public function releaseReservation(DataContainer $dc): void
    {
        $order = $this->modelUtil->findModelInstanceByPk('tl_iso_product_collection', $dc->id);

        if (!$order instanceof Order) {
            return;
        }

        $this->stockManager->releaseDeletedDraftReservation($order);
    }
}
