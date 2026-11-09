<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\EventListener\Isotope;

use HeimrichHannot\IsotopeExtensionBundle\Manager\StockManager;
use Isotope\Model\ProductCollection;
use Isotope\Model\ProductCollection\Order;

/**
 * Books the stock reservation of a deleted, not completed order back.
 *
 * ProductCollection::delete() calls the postDeleteCollection hook after the row was deleted (also for
 * delete(true)), so the reservation map is only left in the model. The order must not be saved here, that
 * would create it anew. Deleting in the backend doesn't use Model::delete() (see the ondelete callback).
 */
class PostDeleteCollectionListener
{
    protected StockManager $stockManager;

    public function __construct(StockManager $stockManager)
    {
        $this->stockManager = $stockManager;
    }

    /**
     * @param int|string $pid the id the collection had before it was deleted
     */
    public function __invoke(ProductCollection $collection, $pid): void
    {
        if (!$collection instanceof Order || $collection->isCheckoutComplete() || (int) $pid <= 0) {
            return;
        }

        $map = $collection->stock_reservation;

        if (null === $map || '' === (string) $map) {
            return;
        }

        $this->stockManager->releaseReservationMap((int) $pid, (string) $map);
    }
}
