<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\Form;

use Contao\Controller;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use HeimrichHannot\FormHybrid\Form;
use HeimrichHannot\StatusMessages\StatusMessage;
use Isotope\CheckoutStep\BillingAddress;
use Isotope\CheckoutStep\ShippingAddress;
use Isotope\CheckoutStep\ShippingMethod;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Isotope;
use Isotope\Model\Address;
use Isotope\Model\Config;
use Isotope\Model\Product;
use Isotope\Model\ProductCollection\Cart;
use Isotope\Model\Shipping;
use Isotope\Model\Shipping\Flat;
use Isotope\RequestCache\Sort;

class DirectCheckoutForm extends Form
{
    protected $strMethod = FORMHYBRID_METHOD_POST;
    protected $arrBillingAddressFields = [];
    protected $arrShippingAddressFields = [];
    protected $arrProducts = [];
    protected $objCheckoutModule;
    protected $productCount = 0;
    protected $typeCount = 0;
    protected $noEntity = true;

    public function __construct($objModule = null, $instanceId = 0)
    {
        $this->objCheckoutModule = $objModule;
        parent::__construct($objModule, $instanceId);
    }

    public function modifyDC(&$arrDca = null)
    {
        $fieldPalette = System::getContainer()->get('huh.fieldpalette.manager');

        // get the product
        switch ($this->iso_direct_checkout_product_mode) {
            case 'product_type':
                if (null !== ($objTypes = $fieldPalette->getInstance()->findByPidAndTableAndField($this->objModule->id, 'tl_module', 'iso_direct_checkout_product_types'))) {
                    while ($objTypes->next()) {
                        $arrColumns = [
                            'type=?',
                        ];

                        $arrValues = [
                            $objTypes->iso_direct_checkout_product_type,
                        ];

                        if ($this->iso_listingSortField) {
                            $arrSorting = [
                                $this->iso_listingSortField => ('DESC' == $this->iso_listingSortDirection ? Sort::descending() : Sort::ascending()),
                            ];
                        } else {
                            $arrSorting = [];
                        }

                        $objProducts = Product::findPublishedBy($arrColumns, $arrValues, [
                            'sorting' => $arrSorting,
                        ]);

                        if ($objProducts->count() > 0) {
                            $objProduct = $objProducts->current();

                            $this->arrProducts[] = [
                                'product' => $objProduct,
                                'useQuantity' => $objTypes->iso_use_quantity,
                            ];

                            $this->addProductFields($objProduct, $objTypes->iso_use_quantity, $objTypes->iso_addSubscriptionCheckbox, $arrDca);
                        }
                    }
                }

                break;

            default:
                if (null !== ($objProducts = $fieldPalette->getInstance()->findByPidAndTableAndField($this->objModule->id, 'tl_module', 'iso_direct_checkout_products'))) {
                    while ($objProducts->next()) {
                        $objProduct = Product::findByPk($objProducts->iso_direct_checkout_product);

                        $this->arrProducts[] = [
                            'product' => $objProduct,
                            'useQuantity' => $objProducts->iso_use_quantity,
                        ];
                        $this->addProductFields($objProduct, $objProducts->iso_use_quantity, $objProducts->iso_addSubscriptionCheckbox, $arrDca);
                    }
                }

                break;
        }

        // add address fields
        Controller::loadDataContainer('tl_iso_address');
        System::loadLanguageFile('tl_iso_address');

        $arrAddressFields = StringUtil::deserialize(Config::findByPk($this->iso_config_id)->address_fields, true);

        // add billing address fields
        foreach ($arrAddressFields as $strName => $arrAddressField) {
            $arrData = $GLOBALS['TL_DCA']['tl_iso_address']['fields'][$strName];

            if (!\is_array($arrData) || 'disabled' == $arrAddressField['billing']) {
                continue;
            }

            $arrData['eval']['mandatory'] = 'mandatory' == $arrAddressField['billing'];

            $this->arrBillingAddressFields[] = $strName;
            $this->addEditableField($strName, $arrData);
        }

        $this->addFieldsToDefaultPalette($this->arrBillingAddressFields);

        if ($this->iso_use_notes) {
            $this->addEditableField('notes', [
                'label' => &$GLOBALS['TL_LANG']['MSC']['iso_note'],
                'exclude' => true,
                'inputType' => 'textarea',
                'eval' => ['tl_class' => 'clr w50'],
                'sql' => 'text NULL',
            ]);
        }

        if ($this->iso_useAgb) {
            $this->addEditableField('agb', [
                'label' => $this->iso_agbText,
                'exclude' => true,
                'inputType' => 'checkbox',
                'eval' => ['tl_class' => 'clr', 'mandatory' => true],
                'sql' => "char(1) NOT NULL default ''",
            ], true);
        }

        if ($this->iso_useConsent) {
            $this->addEditableField('consent', [
                'label' => $this->iso_consentText,
                'exclude' => true,
                'inputType' => 'checkbox',
                'eval' => ['tl_class' => 'clr', 'mandatory' => true],
                'sql' => "char(1) NOT NULL default ''",
            ], true);
        }

        $this->addEditableField('shippingaddress', [
            'label' => [$GLOBALS['TL_LANG']['MSC']['differentShippingAddress'], $GLOBALS['TL_LANG']['MSC']['differentShippingAddress']],
            'inputType' => 'checkbox',
            'eval' => ['submitOnChange' => true],
        ]);

        // add shipping address fields
        $arrShippingAddressFields = [];

        foreach ($arrAddressFields as $strName => $arrAddressField) {
            $arrData = $GLOBALS['TL_DCA']['tl_iso_address']['fields'][$strName];

            if (!\is_array($arrData) || 'disabled' == $arrAddressField['shipping']) {
                continue;
            }

            $arrData['eval']['mandatory'] = 'mandatory' == $arrAddressField['shipping'];

            $this->addEditableField('shippingaddress_'.$strName, $arrData);

            $arrShippingAddressFields[] = 'shippingaddress_'.$strName;
        }

        $this->dca['palettes']['__selector__'][] = 'shippingaddress';
        $this->dca['subpalettes']['shippingaddress'] = implode(',', $arrShippingAddressFields);
        $this->arrShippingAddressFields = $arrShippingAddressFields;

        $this->addFieldsToDefaultPalette($this->arrShippingAddressFields);
    }

    public function setProductCount($count)
    {
        $this->productCount = $count;
    }

    public function getProductCount()
    {
        return $this->productCount;
    }

    public function setTypeCount($count)
    {
        $this->typeCount = $count;
    }

    public function getTypeCount()
    {
        return $this->typeCount;
    }

    protected function compile()
    {
        if (empty($this->arrProducts)) {
            $this->Template->error = $GLOBALS['TL_LANG']['MSC']['productNotFound'];
        }
    }

    protected function addFieldsToDefaultPalette($arrFields)
    {
        $strFields = '';

        if (!\is_array($arrFields)) {
            if ($arrFields && !preg_match("~\b ".$arrFields."\b~", $this->dca['palettes']['default'])) {
                $strFields .= ','.$arrFields;
            }
        } else {
            foreach ($arrFields as $field) {
                if (!preg_match("~\b ".$field."\b~", $this->dca['palettes']['default'])) {
                    $strFields .= ','.$field;
                }
            }
        }

        $this->dca['palettes']['default'] .= $strFields.';';
    }

    protected function addProductFields($objProduct, $blnAddQuantity, $blnAddSubscriptionCheckbox, &$arrDca)
    {
        $blnSubPalette = $blnAddQuantity ||
            (class_exists('HeimrichHannot\IsotopeSubscriptionsBundle\HeimrichHannotIsotopeSubscriptionsBundle') &&
                $blnAddSubscriptionCheckbox);

        $this->setProductCount(\count(StringUtil::deserialize($this->iso_direct_checkout_products, true)));
        $this->setTypeCount(\count(StringUtil::deserialize($this->iso_direct_checkout_product_types, true)));

        if ($this->getProductCount() > 1 || $this->getTypeCount() > 1) {
            // add checkbox
            $this->addEditableField('product_'.$objProduct->id, [
                'label' => $objProduct->name,
                'inputType' => 'checkbox',
                'eval' => [
                    'submitOnChange' => $blnSubPalette,
                ],
            ]);

            $this->addFieldsToDefaultPalette('product_'.$objProduct->id);

            if ($blnSubPalette) {
                $arrDca['palettes']['__selector__'][] = 'product_'.$objProduct->id;
            }

            if ($blnAddQuantity) {
                $arrDca['subpalettes']['product_'.$objProduct->id] = 'quantity_'.$objProduct->id;
            }

            if (class_exists('HeimrichHannot\IsotopeSubscriptionsBundle\HeimrichHannotIsotopeSubscriptionsBundle') &&
                $blnAddSubscriptionCheckbox) {
                $arrDca['subpalettes']['product_'.$objProduct->id] .= ',subscribeToProduct_'.$objProduct->id;
            }
        }

        if ($blnAddQuantity) {
            $this->addEditableField('quantity_'.$objProduct->id, [
                'label' => &$GLOBALS['TL_LANG']['MSC']['quantity'],
                'inputType' => 'text',
                'eval' => ['mandatory' => true],
            ]);

            $this->addFieldsToDefaultPalette('quantity_'.$objProduct->id);
        }

        if (class_exists('HeimrichHannot\IsotopeSubscriptionsBundle\HeimrichHannotIsotopeSubscriptionsBundle') &&
            $blnAddSubscriptionCheckbox) {
            $this->addEditableField('subscribeToProduct_'.$objProduct->id, [
                'label' => ' ',
                'inputType' => 'checkbox',
                'options' => [
                    '1' => $GLOBALS['TL_LANG']['MSC']['subscribeToProduct'],
                ],
            ]);

            $this->addFieldsToDefaultPalette('subscribeToProduct_'.$objProduct->id);
        }
    }

    // avoid standard formhybrid save and callback routines, just process the form
    protected function save($varValue = '')
    {
    }

    protected function runCallbacks()
    {
    }

    protected function processForm()
    {
        $framework = System::getContainer()->get('contao.framework');
        // get a product collection (aka cart)
        global $objPage;

        $objCart = new Cart();

        // Can't call the individual rows here, it would trigger markModified and a save()
        $objCart->setRow(array_merge($objCart->row(), [
            'tstamp' => time(),
            'member' => 0,
            'uniqid' => null,
            'config_id' => $this->iso_config_id,
            'store_id' => (int) $framework->getAdapter(PageModel::class)->findByPk($objPage->rootId)->iso_store_id,
        ]));

        $objSubmission = $this->getSubmission(false);

        // add products to cart
        foreach ($this->arrProducts as $arrProduct) {
            $strProduct = 'product_'.$arrProduct['product']->id;
            $strQuantity = 'quantity_'.$arrProduct['product']->id;

            if (($this->getProductCount() > 1 || $this->getTypeCount() > 1) && !$objSubmission->{$strProduct}) {
                continue;
            }

            if (!$objCart->addProduct($arrProduct['product'], $arrProduct['useQuantity'] ? $objSubmission->{$strQuantity} : 1)) {
                $this->transformIsotopeErrorMessages();

                return;
            }
        }

        $objCart->save();

        $objOrder = $objCart->getDraftOrder();

        // temporarily override the cart for generating the reviews...
        $objCartTmp = $framework->getAdapter(Isotope::class)->getCart();
        $framework->getAdapter(Isotope::class)->setCart($objCart);

        // create steps
        $arrSteps = [];
        $arrCheckoutInfo = [];

        // billing address
        $objBillingAddress = new Address();

        foreach ($this->arrBillingAddressFields as $strName) {
            $objBillingAddress->{$strName} = $objSubmission->{$strName};
        }

        $objBillingAddress->save();
        $objOrder->setBillingAddress($objBillingAddress);
        $objBillingAddressStep = new BillingAddress($this->objCheckoutModule);
        $arrSteps[] = $objBillingAddressStep;
        $arrCheckoutInfo['billing_address'] = $objBillingAddressStep->review()['billing_address'];

        // check if shipping method is group
        $shippingMethod = $framework->getAdapter(Shipping::class)->findByPk($this->objCheckoutModule->iso_shipping_modules);

        if ('group' == $shippingMethod->type) {
            $quantity = $objCart->sumItemsQuantity();

            foreach (StringUtil::deserialize($shippingMethod->group_methods) as $method) {
                $groupMethod = $framework->getAdapter(Shipping::class)->findByPk($method);

                if ($groupMethod->minimum_quantity <= $quantity && $groupMethod->maximum_quantity >= $quantity) {
                    $this->objCheckoutModule->iso_shipping_modules = $groupMethod->id;
                    $this->iso_shipping_modules = $groupMethod->id;
                }
            }
        }

        // shipping address
        $objShippingAddress = new Address();

        // standard isotope handling for distinguishing between the address types:
        // -> if only a billing address is available, it's also the shipping address
        foreach (
        ($objSubmission->shippingaddress ? $this->arrShippingAddressFields : $this->arrBillingAddressFields) as $strName
        ) {
            $objShippingAddress->{str_replace('shippingaddress_', '', $strName)} = $objSubmission->{$objSubmission->shippingaddress ? $strName : str_replace('shippingaddress_', 'billingaddress_', $strName)};
        }

        $objShippingAddress->save();

        $objOrder->setShippingAddress($objShippingAddress);
        $objShippingAddressStep = new ShippingAddress($this->objCheckoutModule);
        $arrSteps[] = $objShippingAddressStep;
        $arrCheckoutInfo['shipping_address'] = $objShippingAddressStep->review()['shipping_address'];

        // add shipping method
        $objIsotopeShipping = $framework->getAdapter(Flat::class)->findByPk($this->iso_shipping_modules);
        $objOrder->setShippingMethod($objIsotopeShipping);
        $objShippingMethodStep = new ShippingMethod($this->objCheckoutModule);
        $arrSteps[] = $objShippingMethodStep;

        $arrCheckoutInfo['shipping_method'] = $objShippingMethodStep->review()['shipping_method'];

        // add all the checkout info to the order
        $objOrder->checkout_info = $arrCheckoutInfo;

        $objOrder->notes = $objSubmission->notes;

        //... restore the former cart again
        $framework->getAdapter(Isotope::class)->setCart($objCartTmp);

        $objOrder->nc_notification = $this->nc_notification;
        $objOrder->email_data = $this->getNotificationTokensFromSteps($arrSteps, $objOrder);

        // !HOOK: pre-process checkout
        if (isset($GLOBALS['ISO_HOOKS']['preCheckout']) && \is_array($GLOBALS['ISO_HOOKS']['preCheckout'])) {
            foreach ($GLOBALS['ISO_HOOKS']['preCheckout'] as $callback) {
                $this->import($callback[0]);

                if ($this->{$callback[0]}->{$callback[1]}($objOrder, $this->objCheckoutModule) === false) {
                    System::log('Callback '.$callback[0].'::'.$callback[1].'() cancelled checkout for Order ID '.$this->id, __METHOD__, TL_ERROR);

                    $this->objCheckoutModule->redirectToStep('failed');
                }
            }
        }

        $objOrder->lock();
        $objOrder->checkout();
        $objOrder->complete();

        if (\is_array($this->dca['config']['onsubmit_callback'])) {
            foreach ($this->dca['config']['onsubmit_callback'] as $key => $callback) {
                if ('Isotope\Backend\ProductCollection\Callback' == $callback[0] && 'executeSaveHook' == $callback[1]) {
                    unset($this->dca['config']['onsubmit_callback'][$key]);

                    break;
                }
            }
        }

        $this->transformIsotopeErrorMessages();

        parent::processForm();
    }

    protected function transformIsotopeErrorMessages()
    {
        if (\is_array($_SESSION['ISO_ERROR'])) {
            if (!empty($_SESSION['ISO_ERROR'])) {
                // no redirect!
                $this->jumpTo = null;
            }

            foreach ($_SESSION['ISO_ERROR'] as $strError) {
                StatusMessage::addError($strError, $this->getConfig()->getModule()->id);
            }

            unset($_SESSION['ISO_ERROR']);
        }
    }

    // copy from Checkout.php
    protected function getNotificationTokensFromSteps(array $arrSteps, IsotopeProductCollection $objOrder)
    {
        $arrTokens = [];

        // Run trough all steps to collect checkout information
        foreach ($arrSteps as $objModule) {
            $arrTokens = array_merge($arrTokens, $objModule->getNotificationTokens($objOrder));
        }

        return $arrTokens;
    }
}
