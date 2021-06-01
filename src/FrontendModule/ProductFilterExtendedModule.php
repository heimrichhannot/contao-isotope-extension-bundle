<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\FrontendModule;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Environment;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;
use HeimrichHannot\RequestBundle\Component\HttpFoundation\Request;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;
use HeimrichHannot\UtilsBundle\Url\UrlUtil;
use Isotope\Interfaces\IsotopeAttributeWithOptions;
use Isotope\Isotope;
use Isotope\Model\RequestCache;
use Isotope\Module\ProductFilter;
use Isotope\RequestCache\Filter;
use Isotope\RequestCache\Sort;

class ProductFilterExtendedModule extends ProductFilter
{
    const TYPE = 'iso_product_filter_extended';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var ContaoFramework
     */
    private $framework;

    public function __construct(ModuleModel $module, string $column = 'main')
    {
        $container = System::getContainer();

        $this->framework = $container->get('contao.framework');
        $this->request = $container->get(Request::class);

        parent::__construct($module, $column);
    }

    /**
     * Display a wildcard in the back end.
     *
     * @return string
     */
    public function generate()
    {
        if (System::getContainer()->get(ContainerUtil::class)->isBackend()) {
            $objTemplate = new \BackendTemplate('be_wildcard');
            $objTemplate->wildcard = '### ISOTOPE ECOMMERCE: PRODUCT FILTER EXTENDED ###';

            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;

            return $objTemplate->parse();
        }

        return parent::generate();
    }

    /**
     * Generate the module.
     */
    protected function compile()
    {
        $this->blnUpdacteCache = $this->request->getPost('FORM_SUBMIT') == 'iso_filter_'.$this->id ? true : false;

        $this->generateFilters();
        $this->generateSorting();
        $this->generateLimit();

        if (!$this->blnUpdateCache) {
            // Search does not affect request cache
            $this->generateSearch();

            $params = array_filter(array_keys($_GET), function ($key) {
                return 0 === strpos($key, 'page_iso');
            });

            $this->Template->id = $this->id;
            $this->Template->formId = 'iso_filter_'.$this->id;
            $this->Template->action = ampersand(System::getContainer()->get(UrlUtil::class)->removeQueryString($params));
            $this->Template->actionClear = ampersand(strtok(Environment::get('request'), '?')).'?keywords='.$this->request->getGet('keywords');
            $this->Template->clearLabel = $GLOBALS['TL_LANG']['MSC']['clearFiltersLabel'];
            $this->Template->slabel = $GLOBALS['TL_LANG']['MSC']['submitLabel'];
        }
    }

    /**
     * Generate a search form.
     */
    protected function generateSearch()
    {
        global $objPage;

        $this->Template->hasSearch = false;
        $this->Template->hasAutocomplete = ($this->iso_searchAutocomplete) ? true : false;

        if (\is_array($this->iso_searchFields) && \count($this->iso_searchFields)) { // Can't use empty() because its an object property (using __get)
            if ('' != $this->request->getGet('keywords') && $GLOBALS['TL_LANG']['MSC']['defaultSearchText'] != $this->request->getGet('keywords')) {
                // Redirect to search result page if one is set (see #1068)
                if (!$this->blnUpdateCache && $this->jumpTo != $objPage->id && null !== $this->objModel->getRelated('jumpTo')) {
                    /** @var \PageModel $jumpTo */
                    $jumpTo = $this->objModel->getRelated('jumpTo');

                    // Include \Environment::base or the URL would not work on the index page
                    Controller::redirect(Environment::get('base').$jumpTo->getFrontendUrl().'?'.$_SERVER['QUERY_STRING']);
                }

                $keywords = StringUtil::trimsplit(' |-', $this->request->getGet('keywords'));
                $keywords = array_filter(array_unique($keywords));

                foreach ($keywords as $keyword) {
                    foreach ($this->iso_searchFields as $field) {
                        Isotope::getRequestCache()->addFilterForModule(Filter::attribute($field)->contains($keyword)->groupBy('keyword: '.$keyword), $this->id);
                    }
                }
            }

            $this->Template->hasSearch = true;
            $this->Template->keywordsLabel = $GLOBALS['TL_LANG']['MSC']['searchTermsLabel'];
            $this->Template->keywords = $this->request->getGet('keywords');
            $this->Template->searchLabel = $GLOBALS['TL_LANG']['MSC']['searchLabel'];
            $this->Template->defaultSearchText = $GLOBALS['TL_LANG']['MSC']['defaultSearchText'];
        }
    }

    /**
     * Generate a sorting form.
     */
    protected function generateSorting()
    {
        $this->Template->hasSorting = false;

        if (\is_array($this->iso_sortingFields) && \count($this->iso_sortingFields)) {    // Can't use empty() because its an object property (using __get)
            $arrOptions = [];

            // Cache new request value
            // @todo should support multiple sorting fields
            [$sortingField, $sortingDirection] = explode(':', $this->request->getPost('sorting'));

            if ($this->blnUpdateCache && \in_array($sortingField, $this->iso_sortingFields, true)) {
                Isotope::getRequestCache()->setSortingForModule($sortingField, ('DESC' == $sortingDirection ? Sort::descending() : Sort::ascending()), $this->id);
            } elseif (array_diff(array_keys(Isotope::getRequestCache()->getSortingsForModules([$this->id])), $this->iso_sortingFields)) {
                // Request cache contains wrong value, delete it!

                $this->blnUpdateCache = true;
                Isotope::getRequestCache()->unsetSortingsForModule($this->id);

                RequestCache::deleteById($this->request->getGet('isorc'));
            } elseif (!$this->blnUpdateCache) {
                // No need to generate options if we reload anyway
                $first = Isotope::getRequestCache()->getFirstSortingFieldForModule($this->id);

                if ('' === $first) {
                    $first = $this->iso_listingSortField;
                    $objSorting = 'DESC' === $this->iso_listingSortDirection ? Sort::descending() : Sort::ascending();
                } else {
                    $objSorting = Isotope::getRequestCache()->getSortingForModule($first, $this->id);
                }

                foreach ($this->iso_sortingFields as $field) {
                    [$asc, $desc] = $this->getSortingLabels($field);
                    $objSorting = $first == $field ? Isotope::getRequestCache()->getSortingForModule($field, $this->id) : null;

                    if ('releaseDate' === $field) {
                        $arrOptions[] = [
                            'label' => ($desc),
                            'value' => $field.':DESC',
                            'default' => ((null !== $objSorting && $objSorting->isDescending()) ? '1' : ''),
                        ];
                        $arrOptions[] = [
                            'label' => ($asc),
                            'value' => $field.':ASC',
                            'default' => ((null !== $objSorting && $objSorting->isAscending()) ? '1' : ''),
                        ];
                    } else {
                        $arrOptions[] = [
                            'label' => ($asc),
                            'value' => $field.':ASC',
                            'default' => ((null !== $objSorting && $objSorting->isAscending()) ? '1' : ''),
                        ];
                        $arrOptions[] = [
                            'label' => ($desc),
                            'value' => $field.':DESC',
                            'default' => ((null !== $objSorting && $objSorting->isDescending()) ? '1' : ''),
                        ];
                    }
                }
            }

            $this->Template->hasSorting = true;
            $this->Template->sortingLabel = $GLOBALS['TL_LANG']['MSC']['orderByLabel'];
            $this->Template->sortingOptions = $arrOptions;
        }
    }

    /**
     * Generate a filter form.
     */
    protected function generateFilters()
    {
        $this->Template->hasFilters = false;

        if (\is_array($this->iso_filterFields) && \count($this->iso_filterFields)) {
            return;
        }
        // Can't use empty() because its an object property (using __get)
        $time = time();
        $filters = [];
        $input = $this->request->getPost('filter');
        $categories = $this->findCategories();

        foreach ($this->iso_filterFields as $field) {
            $values = [];
            $objValues = $this->framework->createInstance(Database::class)->execute("
                    SELECT DISTINCT p1.$field FROM tl_iso_product p1 LEFT OUTER JOIN tl_iso_product p2 ON p1.pid=p2.id
                    WHERE p1.language='' ".(BE_USER_LOGGED_IN === true ? '' : "AND p1.published='1' AND (p1.start='' OR p1.start<$time) AND (p1.stop='' OR p1.stop>$time) ").'
                    AND (p1.id IN (SELECT pid FROM '.\Isotope\Model\ProductCategory::getTable().' WHERE page_id IN ('.implode(',', $categories).'))
                    OR p1.pid IN (SELECT pid FROM '.\Isotope\Model\ProductCategory::getTable().' WHERE page_id IN ('.implode(',', $categories).')))
                    '.(BE_USER_LOGGED_IN === true ? '' : " AND (p1.pid=0 OR (p2.published='1' AND (p2.start='' OR p2.start<$time) AND (p2.stop='' OR p2.stop>$time)))").'
                    '.('' == $this->iso_list_where ? '' : ' AND '.Controller::replaceInsertTags($this->iso_list_where)));

            while ($objValues->next()) {
                $values[] = StringUtil::deserialize($objValues->$field, false);
            }

            if ($this->blnUpdateCache && \in_array($input[$field], $values, true)) {
                Isotope::getRequestCache()->setFilterForModule($field, Filter::attribute($field)->isEqualTo($input[$field]), $this->id);
            } elseif ($this->blnUpdateCache && '' == $input[$field]) {
                Isotope::getRequestCache()->removeFilterForModule($field, $this->id);
            } elseif (null !== ($filter = Isotope::getRequestCache()->getFilterForModule($field, $this->id)) && $filter->valueNotIn($values)) {
                // Request cache contains wrong value, delete it!

                $this->blnUpdateCache = true;
                Isotope::getRequestCache()->removeFilterForModule($field, $this->id);

                RequestCache::deleteById($this->request->getGet('isorc'));
            } elseif (!$this->blnUpdateCache) {
                // Only generate options if we do not reload anyway
                if (empty($values)) {
                    continue;
                }

                $data = $GLOBALS['TL_DCA']['tl_iso_product']['fields'][$field];

                if (\is_array($GLOBALS['ISO_ATTR'][$data['inputType']]['callback']) && !empty($GLOBALS['ISO_ATTR'][$data['inputType']]['callback'])) {
                    foreach ($GLOBALS['ISO_ATTR'][$data['inputType']]['callback'] as $callback) {
                        $objCallback = System::importStatic($callback[0]);
                        $data = $objCallback->{$callback[1]}($field, $data, $this);
                    }
                }

                // Use the default routine to initialize options data
                $widget = Widget::getAttributesFromDca($data, $field);
                $filter = Isotope::getRequestCache()->getFilterForModule($field, $this->id);

                if (null !== ($attribute = $GLOBALS['TL_DCA']['tl_iso_product']['attributes'][$field])
                    && $attribute instanceof IsotopeAttributeWithOptions) {
                    $attribute->optionsSource = 'attribute';
                    $widget['options'] = $attribute->getOptionsForProductFilter($values);
                }

                foreach ($values as $value) {
                    $widget['options'][] = ['value' => $value, 'label' => ('' == $value) ? ' ' : 'text'];
                }

                // Must have options to apply the filter
                if (!\is_array($widget['options'])) {
                    continue;
                }

                foreach ($widget['options'] as $k => $option) {
                    if ('' == $option['value']) {
                        $widget['blankOptionLabel'] = $option['label'];
                        unset($widget['options'][$k]);

                        continue;
                    } elseif (!\in_array($option['value'], $values, true) || '-' == $option['value']) {
                        // @deprecated IsotopeAttributeWithOptions::getOptionsForProductFilter already checks this
                        unset($widget['options'][$k]);

                        continue;
                    }

                    $widget['options'][$k]['default'] = ((null !== $filter && $filter->valueEquals($option['value'])) ? '1' : '');
                }

                // Hide fields with just one option (if enabled)
                if ($this->iso_filterHideSingle && \count($widget['options']) < 2) {
                    continue;
                }

                $filters[$field] = $widget;
            }
        }

        // !HOOK: alter the filters
        if (isset($GLOBALS['ISO_HOOKS']['generateFilters']) && \is_array($GLOBALS['ISO_HOOKS']['generateFilters'])) {
            foreach ($GLOBALS['ISO_HOOKS']['generateFilters'] as $callback) {
                $objCallback = System::importStatic($callback[0]);
                $filters = $objCallback->$callback[1]($filters);
            }
        }

        if (!empty($filters)) {
            $this->Template->hasFilters = true;
            $this->Template->filterOptions = $filters;
        }
    }
}
