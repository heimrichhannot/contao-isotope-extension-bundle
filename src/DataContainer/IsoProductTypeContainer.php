<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\DataContainer;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;

class IsoProductTypeContainer
{
    /**
     * @Callback(table="tl_iso_producttype", target="fields.orderNotification.options")
     */
    public function getNotificationChoices(DataContainer $dc)
    {
        $strWhere = '';
        $arrValues = [];
        $arrTypes = $GLOBALS['TL_DCA']['tl_module']['fields'][$dc->field]['eval']['ncNotificationChoices'][$dc->activeRecord->type];

        if (!empty($arrTypes) && \is_array($arrTypes)) {
            $strWhere = ' WHERE '.implode(' OR ', array_fill(0, \count($arrTypes), 'type=?'));
            $arrValues = $arrTypes;
        }

        $arrChoices = [];
        $objNotifications = \Database::getInstance()->prepare('SELECT id,title FROM tl_nc_notification'.$strWhere.' ORDER BY title')
            ->execute($arrValues);

        while ($objNotifications->next()) {
            $arrChoices[$objNotifications->id] = $objNotifications->title;
        }

        return $arrChoices;
    }
}
