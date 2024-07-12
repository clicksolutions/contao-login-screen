<?php

/**
 * Contao bundle contao-login-screen
 *
 * @copyright click solutions GmbH 2023 <https://www.click-solutions.de>
 * @author    René Fehrmann <rf@click-solutions.de>
 */

declare(strict_types=1);

namespace ClickSolutions\ContaoLoginScreenBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class ClickSolutionsContaoLoginScreenBundle.
 */
class ClickSolutionsContaoLoginScreenBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
