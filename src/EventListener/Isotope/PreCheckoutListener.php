<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\EventListener\Isotope;

use Contao\Model\Collection;
use Contao\StringUtil;
use HeimrichHannot\IsotopeExtensionBundle\Manager\StockManager;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use Isotope\Model\ProductCollection\Order;
use Isotope\Model\Shipping;

class PreCheckoutListener
{
    protected StockManager $stockManager;
    protected ModelUtil    $modelUtil;

    public function __construct(StockManager $stockManager, ModelUtil $modelUtil)
    {
        $this->stockManager = $stockManager;
        $this->modelUtil = $modelUtil;
    }

    public function validateStockCheckout($order)
    {
        return $this->stockManager->validateStockCheckout($order);
    }

    public function modifyShippingPrice($order, $module)
    {
        $shippingMethodId = $module->getModel()->iso_shipping_modules;

        if (null === ($method = $this->modelUtil->findModelInstanceByPk('tl_iso_shipping', $shippingMethodId)) || 'group' !== $method->type) {
            return;
        }

        $groupMethodIds = StringUtil::deserialize($method->group_methods, true);

        if (null === ($groupMethods = $this->modelUtil->callModelMethod('tl_iso_shipping', 'findMultipleByIds', $groupMethodIds))) {
            return;
        }

        if (null === ($shippingMethod = $this->getCurrentShippingMethod($groupMethods, $order))) {
            return;
        }

        $order->setShippingMethod($shippingMethod);
    }

    protected function getCurrentShippingMethod(Collection $groupMethods, Order $order)
    {
        $quantity = $this->getQuantityBySkipProducts($groupMethods, $order);

        foreach ($groupMethods as $method) {
            if (!$this->isCurrentShippingMethod($quantity, $method)) {
                continue;
            }

            return $method;
        }

        return null;
    }

    /**
     * @return int|null
     */
    protected function getQuantityBySkipProducts(Collection $methods, Order $order)
    {
        $currentQuantity = $this->getItemQuantity($order);
        $skipItems = $this->getSkipItems($methods, $currentQuantity);

        if (null === $skipItems) {
            return null;
        }

        $items = $order->getItems();

        foreach ($items as $item) {
            if (!\in_array($item->product_id, $skipItems)) {
                continue;
            }

            $currentQuantity -= $item->quantity;
        }

        return $currentQuantity;
    }

    /**
     * @return array|null
     */
    protected function getSkipItems(Collection $methods, int $quantity)
    {
        foreach ($methods as $method) {
            if (!$this->isCurrentShippingMethod($quantity, $method)) {
                continue;
            }

            $skipItems = StringUtil::deserialize($method->skipProducts, true);

            if (!empty($skipItems)) {
                return $skipItems;
            }
        }

        return null;
    }

    /**
     * check for suitable method boundaries.
     *
     * @return bool
     */
    protected function isCurrentShippingMethod(int $quantity, Shipping $method)
    {
        if ($quantity >= $method->minimum_quantity && $quantity <= $method->maximum_quantity) {
            return true;
        }

        return false;
    }

    /**
     * @return int
     */
    protected function getItemQuantity(Order $order)
    {
        $quantity = 0;
        $items = $order->getItems();

        foreach ($items as $item) {
            $quantity += $item->quantity;
        }

        return $quantity;
    }
}
