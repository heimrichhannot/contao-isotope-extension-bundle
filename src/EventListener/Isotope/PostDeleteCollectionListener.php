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
 * Isotope calls the hook even if the DELETE affected no row (already deleted elsewhere): Contao's
 * Model::delete() only resets the model's id if it deleted the row, so a set id means nothing to release.
 * Accepted residual: the in-memory map is trusted, so a re-reservation of the same draft by another request
 * after this model was loaded is not seen. In the opposite direction a stale map can book back stock that the
 * other request's own release already returned (double restock). Both need a draft to be deleted (e.g. by the
 * cron) while a checkout retry of it is running; negligible.
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

        if (null !== $collection->id) {
            // this call deleted nothing (Model::delete() resets the id only if a row was deleted)
            return;
        }

        $map = $collection->stock_reservation;

        if (null === $map || '' === (string) $map) {
            return;
        }

        $this->stockManager->releaseReservationMap((int) $pid, (string) $map);
    }
}
