<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\FrontendModule;

use Contao\BackendTemplate;
use Contao\Controller;
use Contao\CoreBundle\Exception\AjaxRedirectResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Environment;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\System;
use Haste\Generator\RowClass;
use Haste\Http\Response\HtmlResponse;
use HeimrichHannot\IsotopeExtensionBundle\Manager\ProductListManager;
use HeimrichHannot\IsotopeExtensionBundle\Manager\StockManager;
use HeimrichHannot\RequestBundle\Component\HttpFoundation\Request;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;
use Isotope\Isotope;
use Isotope\Message;
use Isotope\Model\Product;
use Isotope\Model\ProductCache;
use Isotope\Model\RequestCache;
use Isotope\Module\ProductList;
use Isotope\RequestCache\FilterQueryBuilder;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

class ProductListExtendedModule extends ProductList
{
    const TYPE = 'iso_product_list_extended';

    /**
     * @var ContaoFramework
     */
    protected $framework;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var CsrfTokenManager
     */
    protected $tokenManager;

    /**
     * @var mixed
     */
    protected $token;

    /**
     * @var array
     */
    protected $cacheIds;

    /**
     * @var array
     */
    protected $products;

    /**
     * @var ProductListManager
     */
    protected $listManager;

    public function __construct(ModuleModel $objModule, string $strColumn = 'main')
    {
        parent::__construct($objModule, $strColumn);

        $container = System::getContainer();

        $this->framework = $container->get(ContaoFramework::class);
        $this->request = $container->get(Request::class);
        $this->tokenManager = $container->get(CsrfTokenManager::class);
        $this->token = $container->getParameter('contao.csrf_token_name');
        $this->listManager = System::getContainer()->get(ProductListManager::class);
    }

    /**
     * Display a wildcard in the back end.
     *
     * @return string
     */
    public function generate()
    {
        if (System::getContainer()->get(ContainerUtil::class)->isBackend()) {
            $template = new BackendTemplate('be_wildcard');

            $template->wildcard = '### ISOTOPE ECOMMERCE: PRODUCT LIST EXTENDED ###';

            $template->title = $this->headline;
            $template->id = $this->id;
            $template->link = $this->name;
            $template->href = 'contao?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;

            return $template->parse();
        }

        return parent::generate();
    }

    /**
     * Compile product list.
     *
     * This function is specially designed so you can keep it in your child classes and only override fetchProducts().
     * You will automatically gain product caching (see class property), grid classes, pagination and more.
     */
    protected function compile()
    {
        // return message if no filter is set
        if ($this->iso_emptyFilter && !$this->request->hasGet('isorc') && !$this->request->hasGet('keywords')) {
            $this->Template->message = $this->replaceInsertTags($this->iso_noFilter);
            $this->Template->type = 'noFilter';
            $this->Template->products = [];

            return;
        }

        global $objPage;

        $pageId = ('article' == $this->iso_category_scope ? $GLOBALS['ISO_CONFIG']['current_article']['pid'] : $objPage->id);
        $this->cacheProducts = System::getContainer()->getParameter('kernel.debug') ? false : true;
        $categories = $this->findCategories();
        [$filters, $sorting, $where, $values] = $this->getFiltersAndSorting();
        $total = $this->listManager->countProducts($where, $values, $this, $categories);

        if ($total < 1) {
            // No products found
            $this->compileEmptyMessage();

            return;
        }
        $this->generatePagination(range(0, $total));

        /** @var ProductCache $cache Try to load the products from cache */
        if ($this->cacheProducts && null !== ($cache = $this->framework->getAdapter(ProductCache::class)->findByUniqid($this->getCacheKey()))) {
            $this->getProductsFromCache($cache);
        }

        if (!\is_array($this->products)) {
            $this->getProducts($pageId, $where, $values, $sorting, $categories);
        }

        // unset Isotope::defaultButtons because of performance reasons
        unset($GLOBALS['ISO_HOOKS']['buttons'][0]);
        $buffer = $this->parseProducts();

        // HOOK: to add any product field or attribute to mod_iso_productlist template
        if (isset($GLOBALS['ISO_HOOKS']['generateProductList']) && \is_array($GLOBALS['ISO_HOOKS']['generateProductList'])) {
            foreach ($GLOBALS['ISO_HOOKS']['generateProductList'] as $callback) {
                $objCallback = System::importStatic($callback[0]);
                $buffer = $objCallback->{$callback[1]}($buffer, $this->products, $this->Template, $this);
            }
        }

        RowClass::withKey('class')->addCount('product_')->addEvenOdd('product_')->addFirstLast('product_')->addGridRows($this->iso_cols)->addGridCols($this->iso_cols)->applyTo($buffer);

        $this->Template->products = $buffer;
    }

    /**
     * Get filter & sorting configuration.
     *
     * @param bool
     *
     * @return array
     */
    protected function getFiltersAndSorting($nativeSql = true)
    {
        /** @var RequestCache $requestCache */
        $requestCache = $this->framework->getAdapter(Isotope::class)->getRequestCache();
        $filter = $requestCache->getFiltersForModules($this->iso_filterModules);
        $sorting = $requestCache->getSortingsForModules($this->iso_filterModules);

        if (empty($sorting) && '' !== $this->iso_listingSortField) {
            $sorting = $this->iso_listingSortField.('DESC' == $this->iso_listingSortDirection ? ' DESC' : '');
        }

        if ($nativeSql) {
            $queryBuilder = new FilterQueryBuilder($filter);

            return [$queryBuilder->getFilters(), $sorting, $queryBuilder->getSqlWhere(), $queryBuilder->getSqlValues()];
        }

        return [$filter, $sorting];
    }

    /**
     * @param string $sorting
     */
    protected function getProducts(int $pageId, string $where, array $values, $sorting, array $categories)
    {
        global $objPage;

        $cacheKey = $this->getCacheKey();

        // Display "loading products" message and add cache flag
        if ($this->cacheProducts) {
            $productCacheAdapter = $this->framework->getAdapter(ProductCache::class);
            $cacheMessage = (bool) $this->iso_productcache[$pageId][(int) $this->request->getGet('isorc')];

            if ($cacheMessage && !$this->request->hasGet('buildCache')) {
                // Do not index or cache the page
                $objPage->noSearch = 1;
                $objPage->cache = 0;

                $this->Template = new \Isotope\Template('mod_iso_productlist_caching');
                $this->Template->message = $GLOBALS['TL_LANG']['MSC']['productcacheLoading'];

                return;
            }

            // Start measuring how long it takes to load the products
            $start = microtime(true);

            // Load products
            $this->products = $this->listManager->fetchProducts($this->cacheIds, $this->Template->offset, $this->Template->limit, $where, $values, $sorting, $this, $categories);

            // Decide if we should show the "caching products" message the next time
            $end = microtime(true) - $start;
            $this->cacheProducts = $end > 1 ? true : false;

            $arrCacheMessage = $this->iso_productcache;

            if ($cacheMessage != $this->cacheProducts) {
                $arrCacheMessage[$pageId][(int) $this->request->getGet('isorc')] = $this->cacheProducts;
                $this->framework->createInstance(Database::class)->prepare('UPDATE tl_module SET iso_productcache=? WHERE id=?')->execute(serialize($arrCacheMessage), $this->id);
            }

            // Do not write cache if table is locked. That's the case if another process is already writing cache
            if ($productCacheAdapter->isWritable()) {
                $this->framework->createInstance(Database::class)->lockTables([ProductCache::getTable() => 'WRITE', 'tl_iso_product' => 'READ']);

                $ids = [];

                foreach ($this->products as $product) {
                    $ids[] = $product->id;
                }

                // Delete existing cache if necessary
                $productCacheAdapter->deleteByUniqidOrExpired($cacheKey);

                /** @var ProductCache $cache */
                $cache = $productCacheAdapter->createForUniqid($cacheKey);
                $cache->expires = $this->getProductCacheExpiration();
                $cache->setProductIds($ids);
                $cache->save();

                $this->framework->createInstance(Database::class)->getInstance()->unlockTables();
            }
        } else {
            $this->products = $this->listManager->fetchProducts($this->cacheIds, $this->Template->offset, $this->Template->limit, $where, $values, $sorting, $this, $categories);
        }
    }

    /**
     * get cached products.
     */
    protected function getProductsFromCache(ProductCache $cache)
    {
        $this->cacheIds = $cache->getProductIds();

        // Use the cache if keywords match. Otherwise we will use the product IDs as a "limit" for fetchProducts()
        if ($cache->keywords == $this->request->getGet('keywords')) {
            $cacheIds = $this->generatePagination($this->cacheIds);

            $this->products = $this->framework->getAdapter(Product::class)->findAvailableByIds($this->cacheIds, [
                'order' => $this->framework->createInstance(Database::class)->findInSet(Product::getTable().'.id', $this->cacheIds),
            ]);

            $this->products = (null === $this->products) ? [] : $this->products->getModels();

            // Cache is wrong, drop everything and run fetchProducts()
            if (\count($this->products) != \count($cacheIds)) {
                $this->cacheIds = null;
                $this->products = null;
            }
        }
    }

    /**
     * @return array
     */
    protected function parseProducts()
    {
        $buffer = [];
        $defaultProductOptions = $this->getDefaultProductOptions();

        /** @var \Isotope\Model\Product\Standard $product */
        foreach ($this->products as $product) {
            $config = [
                'module' => $this,
                'template' => ($this->iso_list_layout ?: $product->getRelated('type')->list_template),
                'gallery' => ($this->iso_gallery ?: $product->getRelated('type')->list_gallery),
                'buttons' => StringUtil::deserialize($this->iso_buttons, true),
                'useQuantity' => $this->iso_use_quantity,
                'jumpTo' => $this->findJumpToPage($product),
                'requestToken' => $this->tokenManager->getToken($this->token)->getValue(),
            ];

            if (Environment::get('isAjaxRequest') && $this->request->getPost('AJAX_MODULE') == $this->id && $this->request->getPost('AJAX_PRODUCT') == $product->getProductId()) {
                $arrCheck = System::getContainer()->get(StockManager::class)->validateQuantity($product, $this->request->getPost('quantity_requested'), Isotope::getCart()->getItemForProduct($product), true);

                if (isset($arrCheck[0]) && !$arrCheck[0]) {
                    // remove synchronous error messages in case of ajax
                    unset($_SESSION['ISO_ERROR']);
                    $response = new HtmlResponse($arrCheck[1], 400);
                } else {
                    try {
                        $product->generate($config);
                    } catch (AjaxRedirectResponseException $exception) {
                    }
                    $response = new HtmlResponse();
                }
                // reset message on ajax call
                Message::reset();
                $response->send();
            }

            $product->mergeRow($defaultProductOptions);

            // Must be done after setting options to generate the variant config into the URL
            if ($this->iso_jump_first && '' == \Haste\Input\Input::getAutoItem('product', false, true)) {
                $this->framework->getAdapter(Controller::class)->redirect($product->generateUrl($config['jumpTo']));
            }

            $arrCSS = StringUtil::deserialize($product->cssID, true);
            $buffer[] = [
                'cssID' => ('' != $arrCSS[0]) ? ' id="'.$arrCSS[0].'"' : '',
                'class' => trim('product '.($product->isNew() ? 'new ' : '').$arrCSS[1]),
                'html' => $product->generate($config),
                'product' => $product,
            ];
        }

        return $buffer;
    }
}
