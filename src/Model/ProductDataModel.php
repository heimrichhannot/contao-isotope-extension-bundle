<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\Model;

use Contao\Model;
use Contao\System;
use HeimrichHannot\IsotopeExtensionBundle\Manager\ProductDataManager;

/**
 * Class ProductDataModel.
 *
 * @property int         $id
 * @property int         $pid
 * @property int         $tstamp
 * @property int         $dateAdded
 * @property string      $initialStock
 * @property string      $stock
 * @property string      $setQuantity
 * @property string      $releaseDate
 * @property string      $maxOrderSize
 * @property string|bool $overrideStockShopConfig
 * @property int         $jumpTo
 * @property int         $addedBy
 * @property string      $uploadedFiles
 * @property string      $uploadedDownloadFiles
 * @property string      $tag
 * @property string      $licence
 * @property string|bool $createMultiImageProduct
 * @property int         $downloadCount
 */
class ProductDataModel extends Model
{
    protected static $strTable = 'tl_iso_product_data';

    /**
     * @var ProductModel
     */
    protected $productModel;
    /**
     * @var ProductDataManager
     */
    protected $productDataManager;

    /**
     * Returns the product model for the current product data instance.
     *
     * @return ProductModel
     */
    public function getProductModel(bool $useCache = true)
    {
        if (!$this->productModel || false === $useCache) {
            $this->productModel = ProductModel::findByPk($this->pid);
        }

        return $this->productModel;
    }

    /**
     * Returns the ProductDataManager.
     *
     * @return \HeimrichHannot\IsotopeExtensionBundle\Manager\ProductDataManager|object
     */
    public function getProductDataManager()
    {
        if (!$this->productDataManager) {
            $this->productDataManager = System::getContainer()->get(
                ProductDataManager::class
            );
        }

        return $this->productDataManager;
    }

    /**
     * Updates the model data with product model data.
     * Attention: Only updates model instance data and do not save. You need to call save() yourself!
     *
     * @return $this
     */
    public function syncWithProduct()
    {
        $product = $this->getProductModel();

        if (null === $product) {
            return $this;
        }

        $data = $product->row();
        // unset id and pid to avoid overwriting
        unset($data['id'], $data['pid']);

        $this->mergeRow($data);
        $this->tstamp = time();

        return $this;
    }
}
