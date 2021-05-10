<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\Manager;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Model\Collection;
use Contao\StringUtil;
use Isotope\Isotope;
use Isotope\Model\Product;
use Isotope\Module\Module;

class ProductListManager
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * count the requested products.
     */
    public function countProducts(string $where, array $values, Module $module, $categories): int
    {
        return $this->framework->getAdapter(Product::class)->countPublishedBy($this->getColumns($where, null, $module, $categories), $values);
    }

    /**
     * Get the requested products.
     *
     * @param null  $cacheIds
     * @param int   $offset
     * @param int   $limit
     * @param mixed $sorting
     *
     * @return array|\Contao\Model[]|\Model[]
     */
    public function fetchProducts($cacheIds, $offset, $limit, string $where, array $values, $sorting, Module $module, array $categories)
    {
        $options = [];

        if (\is_string($sorting)) {
            $options['order'] = $sorting;
        }

        if ($limit > 0) {
            $options['limit'] = $limit;
        }

        if ($offset > 0) {
            $options['offset'] = $offset;
        }

        /** @var Collection $products */
        $products = $this->framework->getAdapter(Product::class)->findAvailableBy($this->getColumns($where, $cacheIds, $module, $categories), $values, $options);

        return (null === $products) ? [] : $products->getModels();
    }

    /**
     * @param null $cacheIds
     */
    protected function getColumns(string $where, $cacheIds, Module $module, array $categories): array
    {
        $columns = [];

        if (!empty($categories)) {
            $columns[] = 'c.page_id IN ('.implode(',', $categories).')';
        }

        if (!empty($cacheIds) && \is_array($cacheIds)) {
            $columns[] = Product::getTable().'.id IN ('.implode(',', $cacheIds).')';
        }

        // Apply new/old product filter
        if ('show_new' == $module->iso_newFilter) {
            $columns[] = Product::getTable().'.dateAdded>='.$this->framework->getAdapter(Isotope::class)->getConfig()->getNewProductLimit();
        } elseif ('show_old' == $module->iso_newFilter) {
            $columns[] = Product::getTable().'.dateAdded<'.$this->framework->getAdapter(Isotope::class)->getConfig()->getNewProductLimit();
        }

        if ('' != $module->iso_list_where) {
            $columns[] = $this->framework->getAdapter(Controller::class)->replaceInsertTags($module->iso_list_where);
        }

        if ('' != $where) {
            $columns[] = $where;
        }

        if ($module->iso_producttype_filter) {
            $productTypes = StringUtil::deserialize($module->iso_producttype_filter, true);

            if (!empty($productTypes)) {
                $columns[] = 'tl_iso_product.type IN ('.implode(',', $productTypes).')';
            }
        }

        if ($module->iso_price_filter) {
            $columns[] = '(SELECT tl_iso_product_pricetier.price FROM tl_iso_product_price LEFT JOIN tl_iso_product_pricetier ON tl_iso_product_pricetier.pid = tl_iso_product_price.id WHERE tl_iso_product.id = tl_iso_product_price.pid) '.('paid' == $module->iso_price_filter ? '> 0' : '= 0');
        }

        return $columns;
    }
}
