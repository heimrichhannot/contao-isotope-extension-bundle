<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\Manager;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Model\Collection;
use HeimrichHannot\IsotopeExtensionBundle\Model\ProductDataModel;
use HeimrichHannot\IsotopeExtensionBundle\Model\ProductModel;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use Isotope\Interfaces\IsotopeProduct;

class ProductDataManager
{
    /**
     * @var array
     */
    protected $productDataFields;

    /**
     * @var ProductDataModel[]
     */
    protected $productDataModelCache = [];

    protected ModelUtil       $modelUtil;
    protected ContaoFramework $framework;

    public function __construct(ModelUtil $modelUtil, ContaoFramework $framework)
    {
        $this->modelUtil = $modelUtil;
        $this->framework = $framework;
    }

    /**
     * Returns all product data fields.
     *
     * @return array
     */
    public function getProductDataFields(bool $useCache = true)
    {
        if (!$this->productDataFields || false === $useCache) {
            $table = ProductDataModel::getTable();
            $this->framework->getAdapter(Controller::class)->loadDataContainer($table);
            $fields = $GLOBALS['TL_DCA'][$table]['fields'];
            $metaFields = [];

            foreach ($fields as $key => $field) {
                if (true !== $field['eval']['skipProductPalette']) {
                    if (isset($field['sql']) && !empty($field['sql']) && !isset($field['eval']['skipPrepareForSave'])) {
                        $field['save_callback'][] = ['huh.isotope.listener.callback.product', 'saveMetaFields'];
                    }
                    $metaFields[$key] = $field;
                }
            }
            $this->productDataFields = $metaFields;
        }

        return $this->productDataFields;
    }

    /**
     * Returns the product data for a product.
     * If no product data is available, a new instance will be returned.
     *
     * @param IsotopeProduct|ProductModel|int $product The product model or id
     *
     * @return ProductDataModel
     */
    public function getProductData($product)
    {
        $pid = is_numeric($product) ? (int) $product : (int) $product->id;

        if (\array_key_exists($pid, $this->productDataModelCache)) {
            return $this->productDataModelCache[$pid];
        }

        $productData = $this->framework->getAdapter(ProductDataModel::class)->findOneBy('pid', $pid);

        if (null === $productData) {
            $productData = new ProductDataModel();
            $productData->pid = $pid;
            $productData->syncWithProduct();
            $productData->dateAdded = $productData->tstamp = time();
        } else {
            $this->productDataModelCache[$productData->pid] = $productData;
        }

        return $productData;
    }

    /**
     * Returns all product models where bypassing findAll method from isotope TypeAgent.
     *
     * @return Collection|ProductModel[]
     */
    public function getAllProducts(string $where = '')
    {
        $table = ProductModel::getTable();
        /** @var Database $db */
        $db = $this->framework->createInstance(Database::class);
        $result = $db->query("SELECT * FROM $table $where");

        return $this->framework->getAdapter(Collection::class)->createFromDbResult($result, $table);
    }
}
