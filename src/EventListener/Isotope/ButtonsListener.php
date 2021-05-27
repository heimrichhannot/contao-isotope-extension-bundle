<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\EventListener\Isotope;

use Contao\Controller;
use HeimrichHannot\UtilsBundle\File\FileUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use HeimrichHannot\UtilsBundle\Url\UrlUtil;
use Isotope\Interfaces\IsotopeProduct;

class ButtonsListener
{
    protected ModelUtil $modelUtil;
    protected FileUtil  $fileUtil;
    protected UrlUtil   $urlUtil;

    public function __construct(ModelUtil $modelUtil, FileUtil $fileUtil, UrlUtil $urlUtil)
    {
        $this->modelUtil = $modelUtil;
        $this->fileUtil = $fileUtil;
        $this->urlUtil = $urlUtil;
    }

    public function addDownloadSingleProductButton(array $buttons)
    {
        $buttons['downloadSingleProduct'] = [
            'label' => $GLOBALS['TL_LANG']['MSC']['buttonLabel']['downloadSingleProduct'],
            'callback' => [self::class, 'downloadSingleProduct'],
        ];

        return $buttons;
    }

    /**
     * Currently only works for products containing one single download.
     */
    public function downloadSingleProduct(IsotopeProduct $product)
    {
        if (null === ($download = $this->modelUtil->findOneModelInstanceBy('tl_iso_download', ['tl_iso_download.pid=?'], [$product->getProductId()])) ||
            !($path = $this->fileUtil->getPathFromUuid($download->singleSRC))) {
            return;
        }

        // TODO count downloads
        // start downloading the file (protected folders also supported)
        Controller::sendFileToBrowser($path);
    }
}
