<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\EventListener\Contao;

use HeimrichHannot\IsotopeExtensionBundle\Manager\ProductDataManager;

class LoadDataContainerListener
{
    protected ProductDataManager $productDataManager;

    public function __construct(ProductDataManager $productDataManager)
    {
        $this->productDataManager = $productDataManager;
    }

    public function __invoke(string $table): void
    {
        if ('tl_iso_product' !== $table) {
            return;
        }

        $GLOBALS['TL_DCA'][$table]['fields'] = array_merge(
            $GLOBALS['TL_DCA'][$table]['fields'],
            $this->productDataManager->getProductDataFields(true)
        );
    }
}
