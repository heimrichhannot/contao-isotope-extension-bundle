<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\DataContainer;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\StringUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;

class IsoShippingContainer
{
    protected ModelUtil $modelUtil;

    public function __construct(ModelUtil $modelUtil)
    {
        $this->modelUtil = $modelUtil;
    }

    /**
     * @Callback(table="tl_iso_shipping", target="fields.skipProducts.options")
     */
    public function getProductsByType(DataContainer $dc)
    {
        if (!$dc->activeRecord->product_types) {
            return [];
        }

        $types = StringUtil::deserialize($dc->activeRecord->product_types, true);
        $options = [];

        if (null === ($products = $this->modelUtil->callModelMethod('tl_iso_product', 'findPublishedBy', ['tl_iso_product.type IN (?)'], [implode(',', $types)]))) {
            return $options;
        }

        foreach ($products as $product) {
            $options[$product->id] = $product->name;
        }

        return $options;
    }
}
