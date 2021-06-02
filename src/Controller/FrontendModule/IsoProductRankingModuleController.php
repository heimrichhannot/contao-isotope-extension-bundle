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
use Contao\Template;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @FrontendModule(IsoProductRankingModuleController::TYPE,category="isotope_extension_bundle")
 */
class IsoProductRankingModuleController extends AbstractFrontendModuleController
{
    const TYPE = 'iso_product_ranking';

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
        $ranking = [];

        $query = '
			SELECT
			p.name,
			t.name AS type,
			SUM(quantity) as count,
			p.id,
			p.setQuantity,
			MONTH(FROM_UNIXTIME(o.locked)) as month
			FROM tl_iso_product p
			INNER JOIN tl_iso_product_collection_item oi ON oi.product_id = p.id
			LEFT JOIN tl_iso_product_collection o ON o.id = oi.pid
			INNER JOIN tl_iso_producttype t ON p.type = t.id
			WHERE DATE(FROM_UNIXTIME(o.locked)) BETWEEN DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND DATE(CURDATE())
			AND o.type = "order" AND o.locked > 0
			GROUP BY p.id, month ORDER BY month DESC';

        $result = Database::getInstance()->prepare($query)->execute();

        if ($result->numRows > 0) {
            while ($result->next()) {
                $products[$result->id] = $result->row();
                $ranking[$result->id][$result->month] = $result->count;
            }
        }

        $template->products = $products;
        $template->ranking = $ranking;
        $template->months = [
            date('n', strtotime('-2 month')),
            date('n', strtotime('-1 month')),
            date('n', time()),
        ];

        return $template->getResponse();
    }
}
