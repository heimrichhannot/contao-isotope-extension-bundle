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
use Isotope\Interfaces\IsotopeCheckoutStep;
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
    protected $billingAddressFields = [];
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

    public function modifyDC(&$dca = null)
    {
        // get the product
        switch ($this->iso_direct_checkout_product_mode) {
            case 'product_type':
                foreach (StringUtil::deserialize($this->iso_direct_checkout_product_types, true) as $type) {
                    $columns = [
                        'type=?',
                    ];

                    $values = [
                        $type['iso_direct_checkout_product_type'],
                    ];

                    if ($this->iso_listingSortField) {
                        $sorting = [
                            $this->iso_listingSortField => ('DESC' == $this->iso_listingSortDirection ? Sort::descending() : Sort::ascending()),
                        ];
                    } else {
                        $sorting = [];
                    }

                    $products = Product::findPublishedBy($columns, $values, [
                        'sorting' => $sorting,
                    ]);

                    if ($products->count() > 0) {
                        $product = $products->current();

                        $this->arrProducts[] = [
                            'product' => $product,
                            'useQuantity' => $type['iso_use_quantity'],
                        ];

                        $this->addProductFields($product, $type['iso_use_quantity'], $type['iso_addSubscriptionCheckbox'], $dca);
                    }
                }

                break;

            default:
                foreach (StringUtil::deserialize($this->iso_direct_checkout_products, true) as $productData) {
                    $product = Product::findByPk($productData['iso_direct_checkout_product']);

                    $this->arrProducts[] = [
                        'product' => $product,
                        'useQuantity' => $productData['iso_use_quantity'],
                    ];

                    $this->addProductFields($product, $productData['iso_use_quantity'], $productData['iso_addSubscriptionCheckbox'], $dca);
                }

                break;
        }

        // add address fields
        Controller::loadDataContainer('tl_iso_address');
        System::loadLanguageFile('tl_iso_address');

        $addressFields = StringUtil::deserialize(Config::findByPk($this->iso_config_id)->address_fields, true);

        // add billing address fields
        foreach ($addressFields as $name => $addressField) {
            $data = $GLOBALS['TL_DCA']['tl_iso_address']['fields'][$name];

            if (!\is_array($data) || 'disabled' == $addressField['billing']) {
                continue;
            }

            $data['eval']['mandatory'] = 'mandatory' == $addressField['billing'];

            $this->billingAddressFields[] = $name;
            $this->addEditableField($name, $data);
        }

        $this->addFieldsToDefaultPalette($this->billingAddressFields);

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
        $shippingAddressFields = [];

        foreach ($addressFields as $name => $addressField) {
            $data = $GLOBALS['TL_DCA']['tl_iso_address']['fields'][$name];

            if (!\is_array($data) || 'disabled' == $addressField['shipping']) {
                continue;
            }

            $data['eval']['mandatory'] = 'mandatory' == $addressField['shipping'];

            $this->addEditableField('shippingaddress_'.$name, $data);

            $shippingAddressFields[] = 'shippingaddress_'.$name;
        }

        $this->dca['palettes']['__selector__'][] = 'shippingaddress';
        $this->dca['subpalettes']['shippingaddress'] = implode(',', $shippingAddressFields);
        $this->arrShippingAddressFields = $shippingAddressFields;

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

    protected function addFieldsToDefaultPalette($fields)
    {
        $palette = '';

        if (!\is_array($fields)) {
            if ($fields && !preg_match("~\b ".$fields."\b~", $this->dca['palettes']['default'])) {
                $palette .= ','.$fields;
            }
        } else {
            foreach ($fields as $field) {
                if (!preg_match("~\b ".$field."\b~", $this->dca['palettes']['default'])) {
                    $palette .= ','.$field;
                }
            }
        }

        $this->dca['palettes']['default'] .= $palette.';';
    }

    protected function addProductFields($product, $addQuantity, $addSubscriptionCheckbox, &$dca)
    {
        $blnSubPalette = $addQuantity ||
            (class_exists('HeimrichHannot\IsotopeSubscriptionsBundle\HeimrichHannotIsotopeSubscriptionsBundle') &&
                $addSubscriptionCheckbox);

        $this->setProductCount(\count(StringUtil::deserialize($this->iso_direct_checkout_products, true)));
        $this->setTypeCount(\count(StringUtil::deserialize($this->iso_direct_checkout_product_types, true)));

        if ($this->getProductCount() > 1 || $this->getTypeCount() > 1) {
            // add checkbox
            $this->addEditableField('product_'.$product->id, [
                'label' => $product->name,
                'inputType' => 'checkbox',
                'eval' => [
                    'submitOnChange' => $blnSubPalette,
                ],
            ]);

            $this->addFieldsToDefaultPalette('product_'.$product->id);

            if ($blnSubPalette) {
                $dca['palettes']['__selector__'][] = 'product_'.$product->id;
            }

            if ($addQuantity) {
                $dca['subpalettes']['product_'.$product->id] = 'quantity_'.$product->id;
            }

            if (class_exists('HeimrichHannot\IsotopeSubscriptionsBundle\HeimrichHannotIsotopeSubscriptionsBundle') &&
                $addSubscriptionCheckbox) {
                $dca['subpalettes']['product_'.$product->id] .= ',subscribeToProduct_'.$product->id;
            }
        }

        if ($addQuantity) {
            $this->addEditableField('quantity_'.$product->id, [
                'label' => &$GLOBALS['TL_LANG']['MSC']['quantity'],
                'inputType' => 'text',
                'eval' => ['mandatory' => true],
            ]);

            $this->addFieldsToDefaultPalette('quantity_'.$product->id);
        }

        if (class_exists('HeimrichHannot\IsotopeSubscriptionsBundle\HeimrichHannotIsotopeSubscriptionsBundle') &&
            $addSubscriptionCheckbox) {
            $this->addEditableField('subscribeToProduct_'.$product->id, [
                'label' => ' ',
                'inputType' => 'checkbox',
                'options' => [
                    '1' => $GLOBALS['TL_LANG']['MSC']['subscribeToProduct'],
                ],
            ]);

            $this->addFieldsToDefaultPalette('subscribeToProduct_'.$product->id);
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

        $cart = new Cart();

        // Can't call the individual rows here, it would trigger markModified and a save()
        $cart->setRow(array_merge($cart->row(), [
            'tstamp' => time(),
            'member' => 0,
            'uniqid' => null,
            'config_id' => $this->iso_config_id,
            'store_id' => (int) $framework->getAdapter(PageModel::class)->findByPk($objPage->rootId)->iso_store_id,
        ]));

        $submission = $this->getSubmission(false);

        // add products to cart
        foreach ($this->arrProducts as $arrProduct) {
            $strProduct = 'product_'.$arrProduct['product']->id;
            $strQuantity = 'quantity_'.$arrProduct['product']->id;

            if (($this->getProductCount() > 1 || $this->getTypeCount() > 1) && !$submission->{$strProduct}) {
                continue;
            }

            if (!$cart->addProduct($arrProduct['product'], $arrProduct['useQuantity'] ? $submission->{$strQuantity} : 1)) {
                $this->transformIsotopeErrorMessages();

                return;
            }
        }

        $cart->save();

        $order = $cart->getDraftOrder();

        // temporarily override the cart for generating the reviews...
        $cartTmp = $framework->getAdapter(Isotope::class)->getCart();
        $framework->getAdapter(Isotope::class)->setCart($cart);

        // create steps
        $steps = [];
        $checkoutInfo = [];

        // billing address
        $billingAddress = new Address();

        foreach ($this->billingAddressFields as $name) {
            $billingAddress->{$name} = $submission->{$name};
        }

        $billingAddress->save();
        $order->setBillingAddress($billingAddress);
        $billingAddressStep = new BillingAddress($this->objCheckoutModule);
        $steps[] = $billingAddressStep;
        $checkoutInfo['billing_address'] = $billingAddressStep->review()['billing_address'];

        // check if shipping method is group
        $shippingMethod = $framework->getAdapter(Shipping::class)->findByPk($this->objCheckoutModule->iso_shipping_modules);

        if ('group' == $shippingMethod->type) {
            $quantity = $cart->sumItemsQuantity();

            foreach (StringUtil::deserialize($shippingMethod->group_methods) as $method) {
                $groupMethod = $framework->getAdapter(Shipping::class)->findByPk($method);

                if ($groupMethod->minimum_quantity <= $quantity && $groupMethod->maximum_quantity >= $quantity) {
                    $this->objCheckoutModule->iso_shipping_modules = $groupMethod->id;
                    $this->iso_shipping_modules = $groupMethod->id;
                }
            }
        }

        // shipping address
        $shippingAddress = new Address();

        // standard isotope handling for distinguishing between the address types:
        // -> if only a billing address is available, it's also the shipping address
        foreach (($submission->shippingaddress ? $this->arrShippingAddressFields : $this->billingAddressFields) as $strName
        ) {
            $shippingAddress->{str_replace('shippingaddress_', '', $strName)} = $submission->{$submission->shippingaddress ? $strName : str_replace('shippingaddress_', 'billingaddress_', $strName)};
        }

        $shippingAddress->save();

        $order->setShippingAddress($shippingAddress);
        $objShippingAddressStep = new ShippingAddress($this->objCheckoutModule);
        $steps[] = $objShippingAddressStep;
        $checkoutInfo['shipping_address'] = $objShippingAddressStep->review()['shipping_address'];

        // add shipping method
        $isotopeShipping = $framework->getAdapter(Flat::class)->findByPk($this->iso_shipping_modules);
        $order->setShippingMethod($isotopeShipping);
        $shippingMethodStep = new ShippingMethod($this->objCheckoutModule);
        $steps[] = $shippingMethodStep;

        $checkoutInfo['shipping_method'] = $shippingMethodStep->review()['shipping_method'];

        // add all the checkout info to the order
        $order->checkout_info = $checkoutInfo;

        $order->notes = $submission->notes;

        //... restore the former cart again
        $framework->getAdapter(Isotope::class)->setCart($cartTmp);

        $order->nc_notification = $this->nc_notification;
        $order->email_data = $this->getNotificationTokensFromSteps($steps, $order);

        // !HOOK: pre-process checkout
        if (isset($GLOBALS['ISO_HOOKS']['preCheckout']) && \is_array($GLOBALS['ISO_HOOKS']['preCheckout'])) {
            foreach ($GLOBALS['ISO_HOOKS']['preCheckout'] as $callback) {
                $this->import($callback[0]);

                if ($this->{$callback[0]}->{$callback[1]}($order, $this->objCheckoutModule) === false) {
                    System::log('Callback '.$callback[0].'::'.$callback[1].'() cancelled checkout for Order ID '.$this->id, __METHOD__, TL_ERROR);

                    $this->objCheckoutModule->redirectToStep('failed');
                }
            }
        }

        $order->lock();
        $order->checkout();
        $order->complete();

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
    protected function getNotificationTokensFromSteps(array $steps, IsotopeProductCollection $order)
    {
        $tokens = [];

        // Run trough all steps to collect checkout information
        /** @var IsotopeCheckoutStep $module */
        foreach ($steps as $module) {
            $tokens = array_merge($tokens, $module->getNotificationTokens($order));
        }

        return $tokens;
    }
}
