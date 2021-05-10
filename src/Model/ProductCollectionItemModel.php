<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\Model;

use Contao\System;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use Isotope\Model\ProductCollectionItem;

class ProductCollectionItemModel extends ProductCollectionItem
{
    protected $productCache;

    public function findByItem($id, array $options = [])
    {
        return System::getContainer()->get(ModelUtil::class)->findModelInstancesBy(
            static::$strTable,
            [static::$strTable.'.product_id=?'],
            [$id],
            $options
        );
    }

    public function getProduct($noCache = false)
    {
        if (!$this->productCache || $noCache) {
            $this->productCache = ProductModel::findByPk($this->product_id);
        }

        return $this->productCache;
    }
}
