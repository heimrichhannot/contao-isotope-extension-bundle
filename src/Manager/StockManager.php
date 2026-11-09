<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\Manager;

use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Message;
use Contao\Model;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use HeimrichHannot\IsotopeExtensionBundle\Attribute\MaxOrderSizeAttribute;
use HeimrichHannot\IsotopeExtensionBundle\Attribute\StockAttribute;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;
use HeimrichHannot\UtilsBundle\Database\DatabaseUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use Isotope\Interfaces\IsotopeProduct;
use Isotope\Isotope;
use Isotope\Model\Attribute;
use Isotope\Model\Config;
use Isotope\Model\Product;
use Isotope\Model\ProductCollection\Order;
use Isotope\Model\ProductCollectionItem;
use Isotope\Model\ProductType;

class StockManager
{
    protected ModelUtil    $modelUtil;
    protected DatabaseUtil $databaseUtil;
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
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        StockAttribute $stockAttribute,
        MaxOrderSizeAttribute $maxOrderSizeAttribute,
        ContainerUtil $containerUtil,
        ModelUtil $modelUtil,
        DatabaseUtil $databaseUtil,
        Connection $connection
    ) {
        $this->stockAttribute = $stockAttribute;
        $this->maxOrderSizeAttribute = $maxOrderSizeAttribute;
        $this->containerUtil = $containerUtil;
        $this->modelUtil = $modelUtil;
        $this->databaseUtil = $databaseUtil;
        $this->connection = $connection;
    }

    /**
     * Returns the id of the product row that holds the stock: the parent product if the variant
     * inherits the stock attribute, otherwise the product itself.
     *
     * Like Isotope's Standard::setRow(), a variant inherits the stock if it is no variant attribute of the
     * product type or if it is listed in the variant's inherit field (or is an always inherited attribute).
     */
    public function getStockTargetId(IsotopeProduct $product): int
    {
        if ($product->isVariant()) {
            if (method_exists($product, 'getVariantAttributes')
                && !\in_array('stock', (array) $product->getVariantAttributes(), true)) {
                return (int) $product->getProductId();
            }

            $inherited = array_merge(StringUtil::deserialize($product->inherit, true), Attribute::getInheritFields());

            if (\in_array('stock', $inherited, true)) {
                return (int) $product->getProductId();
            }
        }

        return (int) $product->getId();
    }

    /**
     * Atomically decreases the stock of the product's stock row by $quantity.
     *
     * The update only applies if the product's stock is managed (not '') and sufficient, unless
     * stock validation is skipped for the product (then the stock may become negative).
     * Afterwards the current stock is always re-read into $product->stock (as string).
     *
     * @return bool true if the stock was decreased, false otherwise (insufficient or unmanaged stock)
     */
    public function decreaseStock(IsotopeProduct $product, int $quantity): bool
    {
        return $this->decreaseStockRow($product, $quantity, null);
    }

    /**
     * Atomically increases the stock of the product's stock row by $quantity (only if the stock is
     * managed) and re-reads the current stock into $product->stock.
     */
    public function increaseStock(IsotopeProduct $product, int $quantity): void
    {
        $targetId = $this->getStockTargetId($product);

        $this->increaseStockById($targetId, $quantity);
        $this->refreshStock($product, $targetId);
    }

    /**
     * Atomically increases the stock of the given product row by $quantity (only if the stock is managed).
     */
    public function increaseStockById(int $targetId, int $quantity): void
    {
        $this->connection->executeStatement(
            "UPDATE tl_iso_product SET stock = CAST(NULLIF(stock, '') AS SIGNED) + :quantity WHERE id = :id AND stock <> ''",
            ['quantity' => $quantity, 'id' => $targetId],
            ['quantity' => ParameterType::INTEGER, 'id' => ParameterType::INTEGER]
        );
    }

    /**
     * Reserves (decreases) the stock of all items of the order and stores what was reserved in the order's
     * stock_reservation field (serialized map: id of the stock row => quantity).
     *
     * If the order already holds exactly the (non-empty) reservation its items need, it is kept as is. This keep
     * path skips the checks that don't concern the reserved stock (e.g. maxOrderSize): the same items already
     * passed them when the reservation was made. Otherwise an existing reservation is released first and the stock
     * is reserved anew.
     * Items with unmanaged stock or a quantity <= 0 are skipped. If nothing needs to be reserved, an empty map is
     * stored, so the order is not mistaken for one from before the reservation (whose stock is computed from its
     * items). If an item can't be reserved, an error message is reported (see validateQuantity()), everything
     * already decreased is booked back and false is returned.
     */
    public function reserveOrder(Order $order): bool
    {
        $orderId = (int) $order->id;

        if ($orderId <= 0) {
            // the reservation can only be stored on a saved order
            $this->addReservationFailedError();

            return false;
        }

        $currentMap = $this->fetchReservationMap($orderId);

        // an empty map holds no stock: go the full path, so items that are only validated (skipStockEdit) are checked again
        if (null !== $currentMap && [] !== ($currentReservation = $this->unserializeReservationMap($currentMap))
            && $currentReservation === $this->getRequiredReservation($order)) {
            // releasing first would open a window in which a concurrent request could take the stock
            $this->syncReservationField($order, $currentMap);

            return true;
        }

        $this->releaseOrder($order);

        /** @var array<int, int> $reserved */
        $reserved = [];

        foreach ($order->getItems() as $item) {
            $quantity = (int) $item->quantity;
            $product = $item->getProduct();

            if ($quantity <= 0 || null === $product) {
                continue;
            }

            $targetId = $this->getStockTargetId($product);

            // pre-check (and its message) with the current stock, not the one the model was loaded with
            if ('' === $this->refreshStock($product, $targetId)) {
                continue;
            }

            if (!$this->validateQuantity($product, $quantity)) {
                $this->bookBack($reserved);

                return false;
            }

            if ($this->getOverridableStockProperty('skipStockEdit', $product)) {
                continue;
            }

            $stockQuantity = (int) $this->getTotalStockQuantity($quantity, $product);

            if ($stockQuantity <= 0) {
                continue;
            }

            // shipping_exempt is set after checkout (markShippingExemptForEmptyStock()): setting it here, before
            // Isotope locks the order, would make the order drop its shipping address
            if (!$this->decreaseStockRow($product, $stockQuantity, null, false)) {
                // the stock changed since the pre-check: report it with the stock decreaseStock() just re-read
                if ($this->validateQuantity($product, $quantity)) {
                    // the stock rose again meanwhile (or the row vanished): don't leave the user without a message
                    $this->addReservationFailedError();
                }

                $this->bookBack($reserved);

                return false;
            }

            $reserved[$targetId] = ($reserved[$targetId] ?? 0) + $stockQuantity;
        }

        // also store an empty map: a NULL map would make cancel/delete compute the stock from the items
        $map = serialize($reserved);

        $affected = $this->connection->executeStatement(
            'UPDATE tl_iso_product_collection SET stock_reservation = :map WHERE id = :id AND stock_reservation IS NULL',
            ['map' => $map, 'id' => $orderId],
            ['map' => ParameterType::STRING, 'id' => ParameterType::INTEGER]
        );

        if (1 !== (int) $affected) {
            // reserved concurrently by another request: that reservation applies, take ours back
            $this->bookBack($reserved);

            $map = $this->fetchReservationMap($orderId);

            if (null === $map) {
                // ... but it is gone again: nothing is reserved for this order, fail closed
                $this->syncReservationField($order, null);
                $this->addReservationFailedError();

                return false;
            }
        }

        $this->syncReservationField($order, $map);

        return true;
    }

    /**
     * Releases the order's stock reservation (if any): clears the stock_reservation field and books the
     * reserved quantities back. Only the request that actually cleared the field books back.
     */
    public function releaseOrder(Order $order): void
    {
        $orderId = (int) $order->id;
        $map = $this->fetchReservationMap($orderId);

        if (null !== $map) {
            $affected = $this->connection->executeStatement(
                'UPDATE tl_iso_product_collection SET stock_reservation = NULL WHERE id = :id AND stock_reservation = :map',
                ['id' => $orderId, 'map' => $map],
                ['id' => ParameterType::INTEGER, 'map' => ParameterType::STRING]
            );

            if (1 === (int) $affected) {
                $this->bookBack($this->unserializeReservationMap($map));
            }
        }

        $this->syncReservationField($order, null);
    }

    /**
     * Books a serialized reservation map back without compare-and-swap and without touching the order.
     *
     * Only for orders that are already deleted (the map can't be released concurrently anymore). $orderId is
     * only used for the log entry.
     */
    public function releaseReservationMap(int $orderId, string $serializedMap): void
    {
        $reserved = $this->unserializeReservationMap($serializedMap);

        if (!$reserved) {
            return;
        }

        $this->bookBack($reserved);

        if (System::getContainer()->has('monolog.logger.contao')) {
            System::getContainer()->get('monolog.logger.contao')->info(
                sprintf('Released the stock reservation of deleted order ID %s: %s', $orderId, json_encode($reserved)),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]
            );
        }
    }

    /**
     * Returns the stock quantities the order holds (id of the stock row => quantity): its reservation map if it
     * has one (read from the database), otherwise (orders from before the reservation) computed from its items
     * with the order's shop config. Items with unmanaged stock, a deleted product or a quantity <= 0 are skipped.
     *
     * @return array<int, int>
     */
    public function getOrderStockQuantities(Order $order): array
    {
        $map = $this->fetchReservationMap((int) $order->id);

        if (null !== $map) {
            return $this->unserializeReservationMap($map);
        }

        $config = $this->getOrderConfig($order);
        $quantities = [];

        foreach ($order->getItems() as $item) {
            $quantity = (int) $item->quantity;
            $product = $item->getProduct();

            if ($quantity <= 0 || null === $product) {
                continue;
            }

            $targetId = $this->getStockTargetId($product);

            if ('' === $this->refreshStock($product, $targetId)) {
                continue;
            }

            $stockQuantity = (int) $this->getTotalStockQuantity($quantity, $product, null, $item->setQuantity ?: null, $config);

            if ($stockQuantity > 0) {
                $quantities[$targetId] = ($quantities[$targetId] ?? 0) + $stockQuantity;
            }
        }

        ksort($quantities);

        return $quantities;
    }

    /**
     * Atomically books the order's stock quantities (see getOrderStockQuantities()) back, e.g. when the order is
     * cancelled. Unmanaged stock rows are not touched.
     */
    public function restockOrder(Order $order): void
    {
        $this->bookBack($this->getOrderStockQuantities($order));
    }

    /**
     * Atomically decreases the stock by the order's stock quantities again (e.g. a cancelled order is reactivated).
     *
     * Each decrease is conditional (enough stock), unless stock validation is skipped for the product (checked with
     * the order's shop config). Rows of deleted products and rows whose stock is unmanaged are skipped. If a row
     * can't be decreased, an error message is reported (with the current stock), everything already decreased is
     * booked back and false is returned.
     */
    public function unrestockOrder(Order $order): bool
    {
        $config = $this->getOrderConfig($order);
        /** @var array<int, int> $decreased */
        $decreased = [];

        foreach ($this->getOrderStockQuantities($order) as $targetId => $quantity) {
            if ($quantity <= 0 || null === ($product = Product::findByPk($targetId))) {
                continue;
            }

            if ($this->decreaseStockRow($product, $quantity, $config)) {
                $decreased[$targetId] = $quantity;

                continue;
            }

            if ('' === (string) $product->stock) {
                // unmanaged (or the row vanished): nothing to decrease
                continue;
            }

            $validateStock = $this->stockAttribute->validate($product, $quantity);

            if (true !== $validateStock[0]) {
                $this->validateQuantityErrorResult((string) $validateStock[1], false);
            } else {
                // the stock rose again meanwhile: don't leave the user without a message
                $this->addReservationFailedError();
            }

            $this->bookBack($decreased);

            return false;
        }

        return true;
    }

    /**
     * Books the stock of an order that is deleted in the backend (DC_Table) back. Must be called before DC_Table
     * deletes the row (ondelete callback).
     *
     * Only orders that hold stock are booked back: completed orders (stock taken at checkout) and drafts with a
     * reservation. To book back only once, this method deletes the order row itself (only if it still holds the
     * map that was read) and books back only if it did: a concurrent Model::delete() of the same order (whose
     * postDeleteCollection listener releases a draft's reservation) or a second backend delete then deletes
     * nothing and books nothing back. The callers must check the order status themselves (orders in a
     * stock-increasing status hold no stock).
     *
     * @return bool true if this call deleted the row and booked the stock back
     */
    public function restockDeletedOrder(Order $order): bool
    {
        $orderId = (int) $order->id;
        $map = $this->fetchReservationMap($orderId);

        if (null === $map && !$order->isCheckoutComplete()) {
            // draft without reservation: no stock was taken
            return false;
        }

        $quantities = $this->getOrderStockQuantities($order);

        $affected = $this->connection->executeStatement(
            'DELETE FROM tl_iso_product_collection WHERE id = :id AND stock_reservation <=> :map',
            ['id' => $orderId, 'map' => $map],
            ['id' => ParameterType::INTEGER, 'map' => null === $map ? ParameterType::NULL : ParameterType::STRING]
        );

        if (1 !== (int) $affected) {
            return false;
        }

        $this->bookBack($quantities);

        return true;
    }

    /**
     * Whether the given order status is one of the config's stockIncreaseOrderStates (e.g. "cancelled").
     *
     * @param int|string $statusId
     */
    public function isStockIncreaseOrderStatus($statusId, ?Config $config = null): bool
    {
        if (null === $config) {
            $config = Isotope::getConfig();
        }

        $states = array_map('strval', StringUtil::deserialize($config->stockIncreaseOrderStates, true));

        return \in_array((string) $statusId, $states, true);
    }

    /**
     * The shop config the order was placed with (falls back to the current config if it is gone).
     */
    public function getOrderConfig(Order $order): Config
    {
        $config = $order->getRelated('config_id');

        return $config instanceof Config ? $config : Isotope::getConfig();
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
     * Marks the products whose stock this order emptied as exempt from shipping (unless
     * skipExemptionFromShippingWhenStockEmpty is set). Runs after checkout, because doing it while
     * reserving would make Isotope drop the order's shipping address when it locks the order.
     */
    public function markShippingExemptForEmptyStock(Order $order): void
    {
        $orderId = (int) $order->id;
        $map = $orderId > 0 ? $this->fetchReservationMap($orderId) : null;

        if (null === $map) {
            return;
        }

        foreach (array_keys($this->unserializeReservationMap($map)) as $targetId) {
            $product = Product::findByPk($targetId);

            if (null === $product
                || $this->getOverridableStockProperty('skipExemptionFromShippingWhenStockEmpty', $product, $this->getOrderConfig($order))) {
                continue;
            }

            $this->connection->executeStatement(
                "UPDATE tl_iso_product SET shipping_exempt = '1' WHERE id = :id AND stock <> '' AND CAST(NULLIF(stock, '') AS SIGNED) <= 0",
                ['id' => $targetId],
                ['id' => ParameterType::INTEGER]
            );
        }
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
     * @param Config|null $config the shop config to fall back to (default: the current one)
     *
     * @return mixed
     */
    public function getOverridableStockProperty(string $property, $product, ?Config $config = null)
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
        $objConfig = $config ?: Isotope::getConfig();

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
     * Checks (without changing the stock) whether all items of the order are available.
     *
     * @param bool $isPostCheckout no effect anymore, the stock is no longer decreased here
     *
     * @return bool
     *
     * @deprecated use reserveOrder(), which checks and reserves the stock atomically
     */
    public function validateStockCheckout(Order $order, $isPostCheckout = false)
    {
        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();

            if (null !== $product && '' != $product->stock && null !== $product->stock
                && !$this->validateQuantity($product, $item->quantity)) {
                return false;
            }
        }

        return true;
    }

    /**
     * See decreaseStock(); $config is the shop config the stock settings fall back to (null: the current one).
     * With $markShippingExempt = false an emptied stock does not mark the product as exempt from shipping.
     */
    protected function decreaseStockRow(IsotopeProduct $product, int $quantity, ?Config $config, bool $markShippingExempt = true): bool
    {
        $targetId = $this->getStockTargetId($product);

        // NULLIF keeps CAST away from '' so strict sql_mode can never raise "Truncated incorrect INTEGER value"
        $sql = "UPDATE tl_iso_product SET stock = CAST(NULLIF(stock, '') AS SIGNED) - :quantity WHERE id = :id AND stock <> ''";

        if (!$this->getOverridableStockProperty('skipStockValidation', $product, $config)) {
            $sql .= " AND CAST(NULLIF(stock, '') AS SIGNED) >= :quantity";
        }

        $affected = $this->connection->executeStatement(
            $sql,
            ['quantity' => $quantity, 'id' => $targetId],
            ['quantity' => ParameterType::INTEGER, 'id' => ParameterType::INTEGER]
        );

        $stock = $this->refreshStock($product, $targetId);
        $success = 1 === (int) $affected;

        if ($success && $markShippingExempt && (int) $stock <= 0
            && !$this->getOverridableStockProperty('skipExemptionFromShippingWhenStockEmpty', $product, $config)) {
            $this->connection->executeStatement(
                "UPDATE tl_iso_product SET shipping_exempt = '1' WHERE id = :id",
                ['id' => $targetId],
                ['id' => ParameterType::INTEGER]
            );
            $product->shipping_exempt = '1';
        }

        return $success;
    }

    /**
     * Reads the current stock of the given row and writes it (as string) into $product->stock.
     */
    protected function refreshStock(IsotopeProduct $product, int $targetId): string
    {
        $stock = $this->connection->fetchOne(
            'SELECT stock FROM tl_iso_product WHERE id = :id',
            ['id' => $targetId],
            ['id' => ParameterType::INTEGER]
        );

        $stock = false === $stock || null === $stock ? '' : (string) $stock;
        $product->stock = $stock;

        return $stock;
    }

    /**
     * Returns the reservation (id of the stock row => quantity) the order's items currently need, without
     * changing any stock. Same rules as reserveOrder(): managed stock, quantity > 0, not skipStockEdit.
     *
     * @return array<int, int>
     */
    protected function getRequiredReservation(Order $order): array
    {
        $required = [];

        foreach ($order->getItems() as $item) {
            $quantity = (int) $item->quantity;
            $product = $item->getProduct();

            if ($quantity <= 0 || null === $product) {
                continue;
            }

            $targetId = $this->getStockTargetId($product);

            if ('' === $this->refreshStock($product, $targetId)
                || $this->getOverridableStockProperty('skipStockEdit', $product)) {
                continue;
            }

            $stockQuantity = (int) $this->getTotalStockQuantity($quantity, $product);

            if ($stockQuantity > 0) {
                $required[$targetId] = ($required[$targetId] ?? 0) + $stockQuantity;
            }
        }

        ksort($required);

        return $required;
    }

    protected function addReservationFailedError(): void
    {
        $this->validateQuantityErrorResult((string) ($GLOBALS['TL_LANG']['MSC']['stockReservationFailed'] ?? ''), false);
    }

    /**
     * Books the given quantities (id of the stock row => quantity) back.
     *
     * @param array<int, int> $reserved
     */
    protected function bookBack(array $reserved): void
    {
        foreach ($reserved as $targetId => $quantity) {
            if ($quantity > 0) {
                $this->increaseStockById((int) $targetId, (int) $quantity);
            }
        }
    }

    /**
     * Reads the order's serialized reservation map from the database (not from the model).
     */
    protected function fetchReservationMap(int $orderId): ?string
    {
        $map = $this->connection->fetchOne(
            'SELECT stock_reservation FROM tl_iso_product_collection WHERE id = :id',
            ['id' => $orderId],
            ['id' => ParameterType::INTEGER]
        );

        return false === $map || null === $map ? null : (string) $map;
    }

    /**
     * @return array<int, int>
     */
    protected function unserializeReservationMap(string $map): array
    {
        $data = @unserialize($map, ['allowed_classes' => false]);

        if (!\is_array($data)) {
            return [];
        }

        $reserved = [];

        foreach ($data as $targetId => $quantity) {
            if ((int) $targetId > 0 && (int) $quantity > 0) {
                $reserved[(int) $targetId] = (int) $quantity;
            }
        }

        ksort($reserved);

        return $reserved;
    }

    /**
     * Updates the order model's stock_reservation to the value now in the database, so it can't diverge from
     * it. If the field isn't modified in the model, it stays unmodified, so a later save() of the order doesn't
     * write it at all (and can't overwrite a reservation made concurrently).
     */
    protected function syncReservationField(Order $order, ?string $map): void
    {
        $order->mergeRow(['stock_reservation' => $map]);

        if ($order->stock_reservation !== $map) {
            // the field was modified (but not saved) in the model: override that pending value
            $order->stock_reservation = $map;
        }
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
