<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\EventListener\Isotope;

use Haste\Generator\RowClass;
use Haste\Util\Format;
use HeimrichHannot\IsotopeExtensionBundle\Manager\StockManager;
use Isotope\Frontend;
use Isotope\Interfaces\IsotopeAttribute;
use Isotope\Isotope;
use Isotope\Model\Attribute;
use Isotope\Model\Gallery;
use Isotope\Model\Gallery\Standard;
use Isotope\Model\Product;
use Isotope\Model\ProductCollection\Order;
use Isotope\Model\ProductCollectionItem;
use Isotope\Model\ProductType;
use Isotope\Template;
use NotificationCenter\Model\Notification;

class PostCheckoutListener
{
    protected StockManager $stockManager;

    public function __construct(StockManager $stockManager)
    {
        $this->stockManager = $stockManager;
    }

    public function validateStockCheckout($order)
    {
        return $this->stockManager->validateStockCheckout($order, true);
    }

    public function setSetQuantity(Order $order)
    {
        if ($this->stockManager->getOverridableShopConfigProperty('skipSets')) {
            return;
        }

        $items = $order->getItems();

        foreach ($items as $item) {
            $product = $item->getProduct();

            if ($product->setQuantity) {
                $item->setQuantity = $product->setQuantity;
                $item->save();
            }
        }
    }

    public function sendOrderNotification(Order $order, array $tokens)
    {
        // only send one one notification per product type and order
        $productTypes = [];

        foreach ($order->getItems() as $item) {
            $productTypes[] = $item->getProduct()->type;
        }

        foreach (array_unique($productTypes) as $productType) {
            if (null !== ($productTypeModel = ProductType::findByPk($productType))) {
                if ($productTypeModel->sendOrderNotification
                    && null !== ($notification = Notification::findByPk($productTypeModel->orderNotification))) {
                    if ($productTypeModel->removeOtherProducts) {
                        $notification->send($this->getCleanTokens($productType, $order, $notification, $tokens), $GLOBALS['TL_LANGUAGE']);
                    } else {
                        $notification->send($tokens, $GLOBALS['TL_LANGUAGE']);
                    }
                }
            }
        }
    }

    // copy of code in Order->getNotificationTokens
    public function getCleanTokens(int $productType, Order $order, Notification $notification, array $tokens = [])
    {
        $objTemplate = new Template($notification->iso_collectionTpl);
        $objTemplate->isNotification = true;

        // FIX - call to custom function since addToTemplate isn't static
        $this->addToTemplate($productType, $order, $objTemplate, [
            'gallery' => $notification->iso_gallery,
            'sorting' => $order->getItemsSortingCallable($notification->iso_orderCollectionBy),
        ]);

        $tokens['cart_html'] = Haste::getInstance()->call('replaceInsertTags', [$objTemplate->parse(), false]);
        $objTemplate->textOnly = true;
        $tokens['cart_text'] = strip_tags(Haste::getInstance()->call('replaceInsertTags', [$objTemplate->parse(), true]));

        return $tokens;
    }

    // copy of code in ProductCollection->addToTemplate
    public function addToTemplate(int $productType, Order $order, \Template $template, array $config = [])
    {
        $arrGalleries = [];
        // FIX - call to custom function since addItemsToTemplate isn't static
        $arrItems = $this->addItemsToTemplate($productType, $order, $template, $config['sorting']);

        $template->id = $order->id;
        $template->collection = $order;
        $template->config = ($order->getRelated('config_id') || Isotope::getConfig());
        $template->surcharges = Frontend::formatSurcharges($order->getSurcharges());
        $template->subtotal = Isotope::formatPriceWithCurrency($order->getSubtotal());
        $template->total = Isotope::formatPriceWithCurrency($order->getTotal());
        $template->tax_free_subtotal = Isotope::formatPriceWithCurrency($order->getTaxFreeSubtotal());
        $template->tax_free_total = Isotope::formatPriceWithCurrency($order->getTaxFreeTotal());

        $template->hasAttribute = function ($strAttribute, ProductCollectionItem $objItem) {
            if (!$objItem->hasProduct()) {
                return false;
            }

            $objProduct = $objItem->getProduct();

            return \in_array($strAttribute, $objProduct->getAttributes(), true)
                || \in_array($strAttribute, $objProduct->getVariantAttributes(), true);
        };

        $template->generateAttribute = function (
            $strAttribute,
            ProductCollectionItem $objItem,
            array $arrOptions = []
        ) {
            if (!$objItem->hasProduct()) {
                return '';
            }

            $objAttribute = $GLOBALS['TL_DCA']['tl_iso_product']['attributes'][$strAttribute];

            if (!($objAttribute instanceof IsotopeAttribute)) {
                throw new \InvalidArgumentException($strAttribute.' is not a valid attribute');
            }

            return $objAttribute->generate($objItem->getProduct(), $arrOptions);
        };

        $template->getGallery = function (
            $strAttribute,
            ProductCollectionItem $objItem
        ) use (
            $config,
            &$arrGalleries
        ) {
            if (!$objItem->hasProduct()) {
                return new Standard();
            }

            $strCacheKey = 'product'.$objItem->product_id.'_'.$strAttribute;
            $config['jumpTo'] = $objItem->getRelated('jumpTo');

            if (!isset($arrGalleries[$strCacheKey])) {
                $arrGalleries[$strCacheKey] = Gallery::createForProductAttribute($objItem->getProduct(), $strAttribute, $config);
            }

            return $arrGalleries[$strCacheKey];
        };

        $template->attributeLabel = function ($name, array $options = []) {
            /** @var Attribute $attribute */
            $attribute = $GLOBALS['TL_DCA']['tl_iso_product']['attributes'][$name];

            if (!$attribute instanceof IsotopeAttribute) {
                return Format::dcaLabel('tl_iso_product', $name);
            }

            return $attribute->getLabel($options);
        };

        $template->attributeValue = function ($name, $value, array $options = []) {
            /** @var Attribute $attribute */
            $attribute = $GLOBALS['TL_DCA']['tl_iso_product']['attributes'][$name];

            if (!$attribute instanceof IsotopeAttribute) {
                return Format::dcaValue('tl_iso_product', $name, $value);
            }

            return $attribute->generateValue($value, $options);
        };

        // !HOOK: allow overriding of the template
        if (isset($GLOBALS['ISO_HOOKS']['addCollectionToTemplate'])
            && \is_array($GLOBALS['ISO_HOOKS']['addCollectionToTemplate'])) {
            foreach ($GLOBALS['ISO_HOOKS']['addCollectionToTemplate'] as $callback) {
                $objCallback = \System::importStatic($callback[0]);
                $objCallback->$callback[1]($template, $arrItems, $order);
            }
        }
    }

    // copy of code in ProductCollection->generateItem
    protected function generateItem(ProductCollectionItem $item)
    {
        $blnHasProduct = $item->hasProduct();
        $objProduct = $item->getProduct();
        $objConfig = $this->getRelated('config_id') ?: Isotope::getConfig();
        $arrCSS = ($blnHasProduct ? deserialize($objProduct->cssID, true) : []);

        // Set the active product for insert tags replacement
        if ($blnHasProduct) {
            Product::setActive($objProduct);
        }

        $arrItem = [
            'id' => $item->id,
            'sku' => $item->getSku(),
            'name' => $item->getName(),
            'options' => Isotope::formatOptions($item->getOptions()),
            'configuration' => $item->getConfiguration(),
            'attributes' => $item->getAttributes(),
            'quantity' => $item->quantity,
            'price' => Isotope::formatPriceWithCurrency($item->getPrice(), true, $objConfig->currency),
            'tax_free_price' => Isotope::formatPriceWithCurrency($item->getTaxFreePrice(), true, $objConfig->currency),
            'original_price' => Isotope::formatPriceWithCurrency($item->getOriginalPrice(), true, $objConfig->currency),
            'total' => Isotope::formatPriceWithCurrency($item->getTotalPrice(), true, $objConfig->currency),
            'tax_free_total' => Isotope::formatPriceWithCurrency($item->getTaxFreeTotalPrice(), true, $objConfig->currency),
            'original_total' => Isotope::formatPriceWithCurrency($item->getTotalOriginalPrice(), true, $objConfig->currency),
            'tax_id' => $item->tax_id,
            'href' => false,
            'hasProduct' => $blnHasProduct,
            'product' => $objProduct,
            'item' => $item,
            'raw' => $item->row(),
            'rowClass' => trim('product '.(($blnHasProduct && $objProduct->isNew()) ? 'new ' : '').$arrCSS[1]),
        ];

        if (null !== $item->getRelated('jumpTo') && $blnHasProduct && $objProduct->isAvailableInFrontend()) {
            $arrItem['href'] = $objProduct->generateUrl($item->getRelated('jumpTo'));
        }

        Product::unsetActive();

        return $arrItem;
    }

    // copy of code in ProductCollection->addItemsToTemplate
    protected function addItemsToTemplate(int $productType, Order $order, \Template $objTemplate, $varCallable = null)
    {
        $taxIds = [];
        $arrItems = [];

        foreach ($this->getItems($varCallable) as $objItem) {
            // FIX - check for product type id
            if ($objItem->getProduct()->type != $productType) {
                continue;
            }
            // ENDFIX

            $item = $this->generateItem($objItem);

            $taxIds[] = $item['tax_id'];
            $arrItems[] = $item;
        }

        RowClass::withKey('rowClass')->addCount('row_')->addFirstLast('row_')->addEvenOdd('row_')->applyTo($arrItems);

        $objTemplate->items = $arrItems;
        $objTemplate->total_tax_ids = \count(array_unique($taxIds));

        return $arrItems;
    }
}
