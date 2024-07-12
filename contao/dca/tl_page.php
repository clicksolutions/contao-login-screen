<?php

/**
 * Contao bundle contao-login-screen
 *
 * @copyright click solutions GmbH 2023 <https://www.click-solutions.de>
 * @author    René Fehrmann <rf@click-solutions.de>
 * @license   Commercial
 */

declare(strict_types=1);

/**
 * Palettes
 */
$GLOBALS['TL_DCA']['tl_page']['palettes']['root'] .= ';{cs_cls_login_legend},cs_cls_logo,cs_cls_bg_image,cs_cls_text';
$GLOBALS['TL_DCA']['tl_page']['palettes']['rootfallback'] .= ';{cs_cls_login_legend},cs_cls_logo,cs_cls_bg_image,cs_cls_text';

/**
 * Fields
 */
$GLOBALS['TL_DCA']['tl_page']['fields']['cs_cls_logo'] = [
    'exclude' => true,
    'inputType' => 'fileTree',
    'eval' => ['files' => true, 'filesOnly' => true, 'extensions' => 'jpg,svg,png', 'fieldType' => 'radio'],
    'sql' => "binary(16) NULL"
];
$GLOBALS['TL_DCA']['tl_page']['fields']['cs_cls_bg_image'] = [
    'exclude' => true,
    'inputType' => 'fileTree',
    'eval' => ['multiple' => true, 'files' => true, 'filesOnly' => true, 'extensions' => 'jpg,jpeg,png,webp,avif', 'fieldType' => 'checkbox', 'isGallery' => true],
    'sql' => "blob NULL"
];
$GLOBALS['TL_DCA']['tl_page']['fields']['cs_cls_text'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rte' => 'ace', 'maxlength' => 255, 'tl_classes' => 'long'],
    'sql' => "varchar(255) NOT NULL default ''"
];
