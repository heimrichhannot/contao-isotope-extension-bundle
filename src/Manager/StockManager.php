<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\Manager;

use Contao\Message;
use Contao\Model;
use HeimrichHannot\IsotopeExtensionBundle\Attribute\MaxOrderSizeAttribute;
use HeimrichHannot\IsotopeExtensionBundle\Attribute\StockAttribute;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use Isotope\Interfaces\IsotopeProduct;
use Isotope\Isotope;
use Isotope\Model\Config;
use Isotope\Model\Product;
use Isotope\Model\ProductCollection\Order;
use Isotope\Model\ProductCollectionItem;
use Isotope\Model\ProductType;

class StockManager
{
    protected ModelUtil $modelUtil;
    /**
     * @var StockAttribute
     */
    private $stockAttribute;
    /**
     * @var MaxOrderSizeAttribute
     */
    private $maxOrderSizeAttribute;
    /**
     * @var ContainerUtil
     */
    private $containerUtil;

    public function __construct(
        StockAttribute $stockAttribute,
        MaxOrderSizeAttribute $maxOrderSizeAttribute,
        ContainerUtil $containerUtil,
        ModelUtil $modelUtil
    ) {
        $this->stockAttribute = $stockAttribute;
        $this->maxOrderSizeAttribute = $maxOrderSizeAttribute;
        $this->containerUtil = $containerUtil;
        $this->modelUtil = $modelUtil;
    }

    /**
     * @param                       $quantity
     * @param ProductCollectionItem $cartItem
     * @param int                   $setQuantity
     *
     * @return array|bool
     */
    public function validateQuantity(Product $product, $quantity, ProductCollectionItem $cartItem = null, bool $includeError = false, int $setQuantity = null)
    {
        // no quantity at all
        if (null === $quantity) {
            return true;
        } elseif (empty($quantity)) {
            $quantity = 1;
        }

        $quantityTotal = $this->getTotalCartQuantity($quantity, $product, $cartItem, $setQuantity);

        // stock
        if (!$this->getOverridableStockProperty('skipStockValidation', $product)) {
            $validateStock = $this->stockAttribute->validate($product, $quantityTotal, $includeError);

            if (true !== $validateStock[0]) {
                return $this->validateQuantityErrorResult($validateStock[1], $includeError);
            }
        }

        // maxOrderSize
        $validateMaxOrderSize = $this->maxOrderSizeAttribute->validate($product, $quantityTotal);

        if (true !== $validateMaxOrderSize[0]) {
            return $this->validateQuantityErrorResult($validateMaxOrderSize[1], $includeError);
        }

        if ($includeError) {
            return [true, null];
        }

        return true;
    }

    /**
     * Returns the config value.
     *
     * Checks if global value is overwritten by product or product type
     *
     * priorities (first is the most important):
     * product, product type, global shop config.
     *
     * @param $product
     *
     * @return mixed
     */
    public function getOverridableStockProperty(string $property, $product)
    {
        // at first check for product and product type
        if ($product->overrideStockShopConfig) {
            return $product->{$property};
        }
        /** @var ProductType|null $objProductType */
        if (null !== ($objProductType = $this->modelUtil->findModelInstanceByPk('tl_iso_producttype', $product->type))
            && $objProductType->overrideStockShopConfig) {
            return $objProductType->{$property};
        }

        // nothing returned?
        $objConfig = Isotope::getConfig();

        // per default return the value defined in the global config
        return $objConfig->{$property};
    }

    /**
     * watch out: also in backend the current set quantity is used.
     *
     * @param null $cartItem
     * @param null $setQuantity
     * @param null $config
     *
     * @return int|null
     */
    public function getTotalStockQuantity(int $quantity, IsotopeProduct $product, $cartItem = null, $setQuantity = null, $config = null)
    {
        $finalSetQuantity = 1;

        if ($setQuantity) {
            $finalSetQuantity = $setQuantity;
        } elseif (!$this->getOverridableShopConfigProperty('skipSets', $config) && $product->setQuantity) {
            $finalSetQuantity = $product->setQuantity;
        }

        $quantity *= $finalSetQuantity;

        if (null !== $cartItem) {
            $quantity += $cartItem->quantity * $finalSetQuantity;
        }

        return $quantity;
    }

    /**
     * @param null $config
     *
     * @return mixed
     */
    public function getOverridableShopConfigProperty(string $property, $config = null)
    {
        if (!$config) {
            $config = Isotope::getConfig();
        }

        return $config->{$property};
    }

    /**
     * Returns the total quanitity of the product type added to cart and already in cart, taking set size into account.
     *
     * watch out: also in backend the current set quantity is used.
     *
     * @param null   $cartItem
     * @param null   $setQuantity
     * @param Config $config
     *
     * @return int|null
     */
    public function getTotalCartQuantity(int $quantity, Model $product, $cartItem = null, $setQuantity = null, Config $config = null)
    {
        $intFinalSetQuantity = 1;

        if ($setQuantity) {
            $intFinalSetQuantity = $setQuantity;
        } elseif (!$this->getOverridableShopConfigProperty('skipSets', $config) && $product->setQuantity) {
            $intFinalSetQuantity = $product->setQuantity;
        }

        $quantity *= $intFinalSetQuantity;

        // Add to already existing quantity (if product is already in cart)
        if ($cartItem) {
            $quantity += $cartItem->quantity * $intFinalSetQuantity;
        }

        return $quantity;
    }

    /**
     * @param bool $isPostCheckout
     *
     * @return bool
     */
    public function validateStockCheckout(Order $order, $isPostCheckout = false)
    {
        $items = $order->getItems();
        $orders = [];

        foreach ($items as $item) {
            $product = $item->getProduct();

            if ('' != $product->stock && null !== $product->stock) {
                // override the quantity!
                if (!$this->validateQuantity($product, $item->quantity)) {
                    return false;
                }

                if ($isPostCheckout) {
                    $orders[] = $item;
                }
            }
        }

        // save new stock
        if ($isPostCheckout) {
            foreach ($orders as $item) {
                $product = $item->getProduct();

                if ($this->getOverridableStockProperty('skipStockEdit', $product)) {
                    continue;
                }

                $intQuantity = $this->getTotalStockQuantity($item->quantity, $product);

                $product->stock -= $intQuantity;

                if ($product->stock <= 0
                    && !$this->getOverridableStockProperty('skipExemptionFromShippingWhenStockEmpty', $product)) {
                    $product->shipping_exempt = true;
                }

                $product->save();
            }
        }

        return true;
    }

    /**
     * Formats the return message of validateQuantity if an error occurred.
     *
     * @return array|bool
     */
    protected function validateQuantityErrorResult(string $errorMessage, bool $includeError)
    {
        if ($this->containerUtil->isFrontend()) {
            $_SESSION['ISO_ERROR'][] = $errorMessage;
        } else {
            Message::addError($errorMessage);
        }

        if ($includeError) {
            return [false, $errorMessage];
        }

        return false;
    }
}
