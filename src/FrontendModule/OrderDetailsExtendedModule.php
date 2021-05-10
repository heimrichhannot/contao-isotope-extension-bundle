<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\FrontendModule;

use Contao\FrontendUser;
use Contao\StringUtil;
use Contao\System;
use Haste\Util\Format;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;
use Isotope\Isotope;
use Isotope\Model\ProductCollection\Order;
use Isotope\Module\OrderDetails;

class OrderDetailsExtendedModule extends OrderDetails
{
    const TYPE = 'iso_order_details_extended';

    public function generate($blnBackend = false)
    {
        if (System::getContainer()->get(ContainerUtil::class)->isBackend() && !$blnBackend) {
            $objTemplate = new \BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ISOTOPE ECOMMERCE: ORDER DETAILS EXTENDED ###';

            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;

            return $objTemplate->parse();
        }

        if ($blnBackend) {
            $this->backend = true;
            $this->jumpTo = 0;
        }

        return parent::generate();
    }

    protected function compile()
    {
        $container = System::getContainer();
        $framework = $container->get('contao.framework');

        // Also check owner (see #126)
        /** @var Order $order */
        if (null === ($order = $framework->getAdapter(Order::class)->findOneBy('uniqid', (string) \Input::get('uid')))
            || (FE_USER_LOGGED_IN === true
                && $order->member > 0
                && $framework->createInstance(FrontendUser::class)->id != $order->member
                && !$this->iso_show_all_orders)) {
            $this->Template = new \Isotope\Template('mod_message');
            $this->Template->type = 'error';
            $this->Template->message = $GLOBALS['TL_LANG']['ERR']['orderNotFound'];

            return;
        }

        // Order belongs to a member but not logged in
        if (!$this->iso_show_all_orders || $container->get('huh.utils.container')->isFrontend() && $this->iso_loginRequired && $order->member > 0 && FE_USER_LOGGED_IN !== true) {
            global $objPage;

            $objHandler = new $GLOBALS['TL_PTY']['error_403']();
            $objHandler->generate($objPage->id);

            exit;
        }

        $framework->getAdapter(Isotope::class)->setConfig($order->getRelated('config_id'));

        $template = new \Isotope\Template($this->iso_collectionTpl);
        $template->linkProducts = true;

        $order->addToTemplate($template, [
            'gallery' => $this->iso_gallery,
            'sorting' => $order->getItemsSortingCallable($this->iso_orderCollectionBy),
        ]);

        $this->Template->collection = $order;
        $this->Template->products = $template->parse();
        $this->Template->info = StringUtil::deserialize($order->checkout_info, true);
        $this->Template->date = Format::date($order->locked);
        $this->Template->time = Format::time($order->locked);
        $this->Template->datim = Format::datim($order->locked);
        $this->Template->orderDetailsHeadline = sprintf($GLOBALS['TL_LANG']['MSC']['orderDetailsHeadline'], $order->getDocumentNumber(), $this->Template->datim);
        $this->Template->orderStatus = sprintf($GLOBALS['TL_LANG']['MSC']['orderStatusHeadline'], $order->getStatusLabel());
        $this->Template->orderStatusKey = $order->getStatusAlias();
    }
}
