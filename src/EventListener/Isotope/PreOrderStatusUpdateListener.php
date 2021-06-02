<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\EventListener\Isotope;

use Contao\StringUtil;
use HeimrichHannot\IsotopeExtensionBundle\Manager\StockManager;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;
use HeimrichHannot\UtilsBundle\Database\DatabaseUtil;
use Isotope\Isotope;
use Isotope\Model\ProductCollection\Order;

class PreOrderStatusUpdateListener
{
    protected StockManager  $stockManager;
    protected ContainerUtil $containerUtil;
    protected DatabaseUtil  $databaseUtil;

    public function __construct(StockManager $stockManager, ContainerUtil $containerUtil, DatabaseUtil $databaseUtil)
    {
        $this->stockManager = $stockManager;
        $this->containerUtil = $containerUtil;
        $this->databaseUtil = $databaseUtil;
    }

    public function updateStock(Order $order, $newsStatus)
    {
        // atm only for backend
        if ($this->containerUtil->isFrontend()) {
            return false;
        }

        // the order's config is used!
        $config = Isotope::getConfig();

        $stockIncreaseOrderStates = StringUtil::deserialize($config->stockIncreaseOrderStates, true);

        // e.g. new -> cancelled => increase the stock based on the order item's setQuantity-values (no validation required, of course)
        if (!\in_array($order->order_status, $stockIncreaseOrderStates, true) && \in_array($newsStatus->id, $stockIncreaseOrderStates)) {
            foreach ($order->getItems() as $item) {
                if (null !== ($product = $item->getProduct())) {
                    $totalQuantity = $this->stockManager->getTotalStockQuantity($item->quantity, $product, null, $item->setQuantity);

                    if ($totalQuantity) {
                        $this->databaseUtil->update('tl_iso_product', [
                            'stock' => $product->stock + $totalQuantity,
                        ], 'tl_iso_product.id=?', [$product->id]);
                    }
                }
            }
        } // e.g. cancelled -> new => decrease the stock after validation
        elseif (\in_array($order->order_status, $stockIncreaseOrderStates, true) && !\in_array($newsStatus->id, $stockIncreaseOrderStates)) {
            foreach ($order->getItems() as $item) {
                if (null !== ($product = $item->getProduct())) {
                    $skipValidation = $this->stockManager->getOverridableStockProperty('skipStockValidation', $product);

                    // watch out: also in backend the current set quantity is used for validation!
                    if (!$skipValidation && !$this->stockManager->validateQuantity($product, $item->quantity)) {
                        // if the validation breaks for only one product collection item -> cancel the order status transition
                        return true;
                    }
                }
            }

            foreach ($order->getItems() as $item) {
                if (null !== ($product = $item->getProduct())) {
                    $totalQuantity = $this->stockManager->getTotalStockQuantity($item->quantity, $product);

                    if ($totalQuantity) {
                        $data = [
                            'stock' => $product->stock - $totalQuantity,
                        ];

                        if ($data['stock'] <= 0 && !$this->stockManager->getOverridableStockProperty('skipExemptionFromShippingWhenStockEmpty', $product)) {
                            $data['shipping_exempt'] = true;
                        }

                        $this->databaseUtil->update('tl_iso_product', $data, 'tl_iso_product.id=?', [$product->id]);
                    }
                }
            }
        }

        // don't cancel
        return false;
    }
}
