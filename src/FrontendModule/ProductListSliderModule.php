<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\FrontendModule;

use Contao\System;
use HeimrichHannot\TinySliderBundle\Util\Config;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;

class ProductListSliderModule extends ProductListExtendedModule
{
    const TYPE = 'iso_productlist_slider';

    protected $strTemplate = 'mod_iso_productlist_slider';

    public function generate()
    {
        if (System::getContainer()->get(ContainerUtil::class)->isBackend()) {
            $objTemplate = new \BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ISOTOPE ECOMMERCE: PRODUCT LIST SLIDER ###';

            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;

            return $objTemplate->parse();
        }

        parent::generate();

        if (!class_exists('HeimrichHannot\TinySliderBundle\ContaoTinySliderBundle')) {
            throw new \Exception('For the product list slider to work you need to install heimrichhannot/contao-tiny-slider-bundle in version ^1.16.');
        }

        if (null !== ($sliderConfig = System::getContainer()->get(ModelUtil::class)->findModelInstanceByPk('tl_tiny_slider_config', $this->tinySliderConfig))) {
            $this->Template->class .= ' tiny-slider '.System::getContainer()->get(Config::class)->getTinySliderCssClassFromModel($sliderConfig);
            $this->Template->attributes .= System::getContainer()->get(Config::class)->getAttributes($sliderConfig);
        }

        return $this->Template->parse();
    }
}
