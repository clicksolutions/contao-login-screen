<?php

/**
 * Contao bundle contao-login-screen
 *
 * @copyright click solutions GmbH 2023 <https://www.click-solutions.de>
 * @author    René Fehrmann <rf@click-solutions.de>
 */

declare(strict_types=1);

/**
 * Fields
 */
$translations = [
    // legends
    'cs_cls_login_legend' => 'Login-Einstellungen',

    // fields
    'cs_cls_logo' => ['Logo', 'Bitte wählen Sie ein Logo aus.'],
    'cs_cls_bg_image' => ['Hintergrundbild', 'Bitte wählen Sie ein Hintergrundbild aus. Für eine zufällige Ausgabe wählen Sie bitte mehrere Hintergrundbilder aus.'],
    'cs_cls_bg_image_blur' => ['Hintergrundbild weichzeichnen', 'Wählen Sie diese Option, wenn das Hintergrundbild weichgezeichnet werden soll.'],
    'cs_cls_text' => ['Text', 'Bitte geben Sie einen Text ein, der dann auf der Anmeldeseite angezeigt wird.'],
];

$GLOBALS['TL_LANG']['tl_page'] = [...$GLOBALS['TL_LANG']['tl_page'], ...$translations];
