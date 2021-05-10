<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\EventListener\Isotope;

use Contao\Controller;
use Contao\Model;
use HeimrichHannot\IsotopeExtensionBundle\Manager\StockManager;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;

class UpdateItemInCollectionListener
{
    protected StockManager $stockManager;
    protected ModelUtil    $modelUtil;

    public function __construct(StockManager $stockManager, ModelUtil $modelUtil)
    {
        $this->stockManager = $stockManager;
        $this->modelUtil = $modelUtil;
    }

    public function __invoke(Model $item, array $set): array
    {
        if (null === ($product = $this->modelUtil->findModelInstanceByPk('tl_iso_product', $item->product_id))) {
            return $set;
        }

        if (!$this->stockManager->validateQuantity($product, $set['quantity'])) {
            Controller::reload();
        }

        return $set;
    }
}
