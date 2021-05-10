<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\FrontendModule;

use Contao\Module;
use Contao\System;
use HeimrichHannot\IsotopeExtensionBundle\Form\DirectCheckoutForm;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;
use Isotope\Module\Checkout;

class DirectCheckoutModule extends Checkout
{
    const TYPE = 'iso_direct_checkout';

    protected $strTemplate = 'mod_iso_direct_checkout';

    public function generate()
    {
        if (System::getContainer()->get(ContainerUtil::class)->isBackend()) {
            $objTemplate = new \BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ISOTOPE ECOMMERCE: DIRECT CHECKOUT ###';

            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;

            return $objTemplate->parse();
        }

        return Module::generate();
    }

    protected function compile()
    {
        $this->formHybridDataContainer = 'tl_iso_product_collection';

        $objForm = new DirectCheckoutForm($this);

        $this->Template->checkoutForm = $objForm->generate();
    }
}
