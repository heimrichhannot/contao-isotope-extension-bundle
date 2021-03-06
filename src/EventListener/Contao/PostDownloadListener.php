<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\EventListener\Contao;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use HeimrichHannot\UtilsBundle\Database\DatabaseUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;

/**
 * @Hook("postDownload")
 */
class PostDownloadListener
{
    protected ContaoFramework $framework;
    protected ModelUtil       $modelUtil;
    protected DatabaseUtil    $databaseUtil;

    public function __construct(ContaoFramework $framework, ModelUtil $modelUtil, DatabaseUtil $databaseUtil)
    {
        $this->framework = $framework;
        $this->modelUtil = $modelUtil;
        $this->databaseUtil = $databaseUtil;
    }

    public function __invoke($path)
    {
        if (null === ($file = $this->framework->getAdapter(\Contao\FilesModel::class)->findByPath($path))) {
            return;
        }

        if (null === ($download = $this->modelUtil->findOneModelInstanceBy('tl_iso_download', ['tl_iso_download.singleSRC=?'], [$file->uuid]))) {
            return;
        }

        if (null !== ($product = $this->modelUtil->findModelInstanceByPk('tl_iso_product', $download->pid))) {
            $this->databaseUtil->update('tl_iso_product', [
                'downloadCount' => $product->downloadCount + 1,
            ], 'tl_iso_product.id=?', [$product->id]);
        }
    }
}
