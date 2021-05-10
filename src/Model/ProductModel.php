<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\Model;

use Contao\Database;
use Contao\System;
use HeimrichHannot\IsotopeExtensionBundle\Manager\ProductDataManager;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use Isotope\Model\Attribute;
use Isotope\Model\Product\Standard;
use Isotope\Model\ProductType;

/**
 * Class ProductModel.
 *
 * @property int    $id
 * @property int    $pid
 * @property int    $gid
 * @property int    $tstamp
 * @property string $language
 * @property int    $dateAdded
 * @property int    $type
 * @property array  $pages
 * @property array  $orderPages
 * @property array  $inherit
 * @property bool   $fallback
 * @property string $alias
 * @property string $sku
 * @property string $name
 * @property string $teaser
 * @property string $description
 * @property string $meta_title
 * @property string $meta_description
 * @property string $meta_keywords
 * @property bool   $shipping_exempt
 * @property array  $images
 * @property bool   $protected
 * @property array  $groups
 * @property bool   $guests
 * @property array  $cssID
 * @property bool   $published
 * @property string $start
 * @property string $stop
 *
 * From Product data:
 * @property string $initialStock
 * @property string $stock
 * @property string $setQuantity
 * @property string $releaseDate
 */
class ProductModel extends Standard
{
    protected static $strTable = 'tl_iso_product';

    /**
     * @var \HeimrichHannot\IsotopeExtensionBundle\Manager\ProductDataManager|object
     */
    protected $productDataManager;
    /**
     * @var ProductDataModel
     */
    protected $productData;
    /**
     * Flag for saving product data.
     *
     * @var bool
     */
    protected $productDataChanged = false;
    /**
     * @var bool
     */
    protected $blankProduct = false;
    /**
     * @var BlankProductModel
     */
    protected $blankProductModel;

    public function __construct(Database\Result $result = null)
    {
        if (null === $result) {
            $this->blankProductModel = new BlankProductModel();
            $this->blankProduct = true;
            $this->arrRelations = $this->blankProductModel->arrRelations;
            $this->productData = new ProductDataModel();
        } else {
            parent::__construct($result);
        }

        $this->productDataManager = System::getContainer()->get(
            ProductDataManager::class
        );
    }

    public function __set($key, $value)
    {
        if (\array_key_exists($key, $this->getProductDataManager()->getProductDataFields()) && null !== $this->getProductData()) {
            $this->getProductData()->$key = $value;
            $this->productDataChanged = true;
        }

        if ($this->blankProduct) {
            $this->blankProductModel->$key = $value;
        } else {
            parent::__set($key, $value);
        }
    }

    public function __get($key)
    {
        if (\array_key_exists($key, $this->getProductDataManager()->getProductDataFields()) && null !== $this->getProductData()) {
            return $this->getProductData()->$key;
        }

        return parent::__get($key);
    }

    public function __isset($key)
    {
        if (\array_key_exists($key, $this->getProductDataManager()->getProductDataFields())) {
            return true;
        }

        return parent::__isset($key);
    }

    public function getStock(int $id)
    {
        return $this->getProductDataManager()->getProductData($id)->stock;
    }

    public function getInitialStock(int $id)
    {
        return $this->getProductDataManager()->getProductData($id)->initialStock;
    }

    /**
     * Return the product data for the current product.
     *
     * @return ProductDataModel
     */
    public function getProductData(bool $useCache = true)
    {
        if (!$this->productData || !$useCache) {
            $this->productData = $this->getProductDataManager()->getProductData($this);
        }

        return $this->productData;
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
     * Updates the model data with product data model data.
     * Attention: Only updates model instance data and do not save. You need to call save() yourself!
     *
     * @return $this
     */
    public function syncWithProductData()
    {
        $productData = $this->getProductData();
        $this->mergeRow($productData->row());
        $this->tstamp = time();

        return $this;
    }

    /**
     * @return Standard
     */
    public function setRow(array $data)
    {
        try {
            // set random type if creating new product to avoid error
            if ('0' === $data['type']) {
                $data['type'] = ProductType::findAll()->current()->id;
            }

            return parent::setRow($data);
        } catch (\UnderflowException $exception) {
            return $this;
        }
    }

    public function save()
    {
        if ($this->blankProduct) {
            $blankModel = $this->blankProductModel->save();
            $this->arrData['id'] = $blankModel->id;

            if ($this->productDataChanged) {
                $this->getProductData()->pid = $blankModel->id;
                $this->getProductData()->save();
                $this->productDataChanged = false;
            }

            return $blankModel;
        }

        if ($this->productDataChanged) {
            $this->getProductData()->save();
            $this->productDataChanged = false;
        }

        return parent::save();
    }

    /**
     * overwrite because otherwise wrong attributes would be marked as modified.
     *
     * @param string $strKey
     */
    public function markModified($strKey, string $type = null)
    {
        if ($type != $this->getType()->id) {
            $arrAttributes = (null === ($productType = System::getContainer()->get(ModelUtil::class)->findModelInstanceByPk('tl_iso_producttype', $type))) ? [] : $productType->getAttributes();
        } else {
            if ($this->isVariant()) {
                $arrAttributes = array_diff(
                    $this->getType()->getVariantAttributes(),
                    $this->getInheritedFields(),
                    Attribute::getCustomerDefinedFields()
                );
            } else {
                $arrAttributes = array_diff($this->getType()->getAttributes(), Attribute::getCustomerDefinedFields());
            }
        }

        if (!\in_array($strKey, $arrAttributes, true)
            && '' !== (string) $GLOBALS['TL_DCA'][static::$strTable]['fields'][$strKey]['attributes']['legend']
        ) {
            return;
        }

        if (!isset($this->arrModified[$strKey])) {
            $this->arrModified[$strKey] = $this->arrData[$strKey];
        }
    }
}
