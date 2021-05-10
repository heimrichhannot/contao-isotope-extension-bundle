<?php

$dca = &$GLOBALS['TL_DCA']['tl_iso_product'];

/**
 * List
 */
$dca['list']['label']['fields'] = array_merge($dca['list']['label']['fields'], ['stock', 'initialStock']);
