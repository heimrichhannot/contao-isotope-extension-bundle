<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\DataContainer;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use HeimrichHannot\UtilsBundle\Dca\DcaUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;

class IsoProductDataContainer
{
    protected ModelUtil $modelUtil;
    protected DcaUtil   $dcaUtil;

    public function __construct(ModelUtil $modelUtil, DcaUtil $dcaUtil)
    {
        $this->modelUtil = $modelUtil;
        $this->dcaUtil = $dcaUtil;
    }

    /**
     * @Callback(table="tl_iso_product_data", target="config.onsubmit")
     */
    public function setDateAdded($dc)
    {
        $this->dcaUtil->setDateAdded($dc);
    }

    /**
     * @Callback(table="tl_iso_product_data", target="config.oncopy")
     */
    public function setDateAddedOnCopy($insertId, $dc)
    {
        $this->dcaUtil->setDateAddedOnCopy($insertId, $dc);
    }

    /**
     * @Callback(table="tl_iso_product_data", target="fields.stock.load")
     */
    public function loadStockFromProductData($value, $dc)
    {
        // caution: $dc is a product, not product data!
        return $this->loadFieldValueFromProductData($value, $dc->id, 'stock');
    }

    /**
     * @Callback(table="tl_iso_product_data", target="fields.initialStock.load")
     */
    public function loadInitialStockFromProductData($value, $dc)
    {
        // caution: $dc is a product, not product data!
        return $this->loadFieldValueFromProductData($value, $dc->id, 'initialStock');
    }

    /**
     * @Callback(table="tl_iso_product_data", target="fields.setQuantity.load")
     */
    public function loadSetQuantityFromProductData($value, $dc)
    {
        // caution: $dc is a product, not product data!
        return $this->loadFieldValueFromProductData($value, $dc->id, 'setQuantity');
    }

    protected function loadFieldValueFromProductData($value, int $product, string $field)
    {
        $productDataModel = $this->modelUtil->findOneModelInstanceBy('tl_iso_product_data', ['tl_iso_product_data.pid=?'], [$product]);

        if (null === $productDataModel) {
            return $value;
        }

        return $productDataModel->{$field};
    }
}
