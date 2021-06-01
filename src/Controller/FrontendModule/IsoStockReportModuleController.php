<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\Database;
use Contao\ModuleModel;
use Contao\System;
use Contao\Template;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @FrontendModule(IsoStockReportModuleController::TYPE,category="isotope_extension_bundle")
 */
class IsoStockReportModuleController extends AbstractFrontendModuleController
{
    const TYPE = 'iso_stockreport';

    /**
     * @var ModelUtil
     */
    protected ModelUtil       $modelUtil;
    protected ContaoFramework $framework;

    public function __construct(ModelUtil $modelUtil, ContaoFramework $framework)
    {
        $this->modelUtil = $modelUtil;
        $this->framework = $framework;
    }

    protected function getResponse(Template $template, ModuleModel $module, Request $request): ?Response
    {
        $products = [];

        $query = 'SELECT p.*, t.name as type FROM tl_iso_product p INNER JOIN tl_iso_producttype t ON t.id = p.type WHERE p.published=1 AND p.shipping_exempt="" AND p.initialStock!="" AND stock IS NOT NULL';

        $result = Database::getInstance()->prepare($query)->execute();

        $this->framework->getAdapter(System::class)->loadLanguageFile('tl_reports');

        if ($result->numRows < 1) {
            return new Response('');
        }

        while ($result->next()) {
            $product = $this->modelUtil->callModelMethod('tl_iso_product', 'findByIdOrAlias', $result->id);
            $category = 'category_'.$product->type;

            if (!isset($products[$category])) {
                $products[$category]['type'] = 'category';
                $products[$category]['title'] = $result->type;
            }

            $productData = $product->row();
            $productData['stockPercent'] = '-';
            $productData['stock'] = $product->stock;
            $productData['initialStock'] = $product->initialStock;

            if ($product->initialStock > 0 && '' !== $product->initialStock) {
                $percent = floor($product->stock * 100 / $product->initialStock);

                $productData['stockPercent'] = $percent;

                switch ($percent) {
                    default:
                        $strClass = 'bg-success';

                        break;

                    case $percent < 25:
                        $strClass = 'bg-danger';

                        break;

                    case $percent < 50:
                        $strClass = 'bg-warning';

                        break;

                    case $percent < 75:
                        $strClass = 'bg-info';

                        break;
                }

                $productData['stockClass'] = $strClass;
            }

            $products[$category]['products'][$product->id] = $productData;
        }

        $template->items = $products;
        $template->id = 'stockReport';

        return $template->getResponse();
    }
}
