<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\DataContainer;

use Contao\CoreBundle\ServiceAnnotation\Callback;

class IsoConfigContainer
{
    /**
     * @Callback(table="tl_iso_config", target="fields.stockIncreaseOrderStates.options")
     */
    public function getOrderStates()
    {
        $arrOptions = [];

        if (null !== ($objOrderStatus = \Isotope\Model\OrderStatus::findAll())) {
            while ($objOrderStatus->next()) {
                $arrOptions[$objOrderStatus->id] = $objOrderStatus->name;
            }
        }

        return $arrOptions;
    }
}
