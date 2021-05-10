<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\EventListener\Isotope;

use HeimrichHannot\IsotopeExtensionBundle\Manager\StockManager;
use Isotope\Model\Product;
use Isotope\Model\ProductCollection;

class AddProductToCollectionListener
{
    protected StockManager $stockManager;

    public function __construct(StockManager $stockManager)
    {
        $this->stockManager = $stockManager;
    }

    public function __invoke(Product $product, int $quantity, ProductCollection $objProductCollection): int
    {
        if (!$this->stockManager->validateQuantity($product, $quantity, $objProductCollection->getItemForProduct($product))) {
            return 0;
        }

        unset($_SESSION['ISO_ERROR']);

        return $quantity;
    }
}
