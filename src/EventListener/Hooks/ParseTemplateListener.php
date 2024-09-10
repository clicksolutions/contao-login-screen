<?php

/**
 * Contao bundle contao-login-screen
 *
 * @copyright click solutions GmbH 2023 <https://www.click-solutions.de>
 * @author    René Fehrmann <rf@click-solutions.de>
 */

declare(strict_types=1);

namespace ClickSolutions\ContaoLoginScreenBundle\EventListener\Hooks;

use Contao\CoreBundle\Image\Studio\Studio;
use Contao\Environment;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\Image\PictureConfiguration;
use Contao\Image\PictureConfigurationItem;
use Contao\Image\ResizeConfiguration;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Template;

class ParseTemplateListener
{
    public function __construct(private readonly string $projectDir, private readonly Studio $studio) {}

    public function __invoke(Template $template): void
    {
        // Change backend login template
        if ('be_login' === $template->getName()) {
            // Get root page or return unchanged
            $rootPage = $this->determineRootPage();
            if (!$rootPage) {
                return;
            }

            // Handle logo and background image
            $template->htmlLogo = $this->generateLogoHtml($rootPage);
            $template->htmlBackgroundImage = $this->generateBackgroundImageHtml($rootPage);

            // Add blur if selected
            if ($rootPage->cs_cls_bg_image_blur) {
                $template->blurClass = ' cs_cls_bg_image_blur';
            }

            // Handle text
            if ($text = $rootPage->cs_cls_text) {
                $template->loginInfo = sprintf('<div class="cs_cls_text">%s</div>', $text);
            }
        }
    }

    protected function generateLogoHtml(PageModel $rootPage): null|string
    {
        $logo = FilesModel::findByUuid($rootPage->cs_cls_logo);
        if (!$logo || !is_readable($this->projectDir . '/' . $logo->path)) {
            return null;
        }

        // Create image
        $figureBuilder = $this->studio->createFigureBuilder();
        $figureBuilder->fromUuid($logo->uuid)
            ->setSize([260, null, 'proportional', '1,1.5,2']);
        $figure = $figureBuilder->build();

        // Get image template
        $template = new FrontendTemplate('image');
        $figure->applyLegacyTemplateData($template);

        return $template->parse();
    }

    protected function generateBackgroundImageHtml(PageModel $rootPage): null|string
    {
        // Get selected background images or return unchanged
        $images = StringUtil::deserialize($rootPage->cs_cls_bg_image, true);
        if (empty($images)) {
            return null;
        }

        // Get random background image
        $image = FilesModel::findByUuid($images[array_rand($images)]);
        if (!$image || !is_readable($this->projectDir . '/' . $image->path)) {
            return null;
        }

        // Create picture configuration
        $pictureConfig = (new PictureConfiguration())
            ->setFormats(['jpg' => ['webp'],])
            ->setSize((new PictureConfigurationItem())
                ->setDensities('1x,1.5x,2x')
                ->setResizeConfig((new ResizeConfiguration())->setWidth(576)->setHeight(800)))
            ->setSizeItems([
                    (new PictureConfigurationItem())
                        ->setMedia('(min-width: 1400px)')
                        ->setDensities('1x,1.5x,2x')
                        ->setResizeConfig((new ResizeConfiguration())->setWidth(1920)->setHeight(1080)),
                    (new PictureConfigurationItem())
                        ->setMedia('(min-width: 1200px)')
                        ->setDensities('1x,1.5x,2x')
                        ->setResizeConfig((new ResizeConfiguration())->setWidth(1400)),
                    (new PictureConfigurationItem())
                        ->setMedia('(min-width: 992px)')
                        ->setDensities('1x,1.5x,2x')
                        ->setResizeConfig((new ResizeConfiguration())->setWidth(1200)),
                    (new PictureConfigurationItem())
                        ->setMedia('(min-width: 768px)')
                        ->setDensities('1x,1.5x,2x')
                        ->setResizeConfig((new ResizeConfiguration())->setWidth(992)),
                    (new PictureConfigurationItem())
                        ->setMedia('(min-width: 576px)')
                        ->setDensities('1x,1.5x,2x')
                        ->setResizeConfig((new ResizeConfiguration())->setWidth(768)),
                ]
            );

        // Create image
        $figureBuilder = $this->studio->createFigureBuilder();
        $figureBuilder->fromUuid($image->uuid)->setSize($pictureConfig);
        $figure = $figureBuilder->build();

        // Get image template
        $template = new FrontendTemplate('image');
        $figure->applyLegacyTemplateData($template);

        return $template->parse();
    }

    protected function determineRootPage(): null|PageModel
    {
        $host = Environment::get('host');

        // Get all root pages
        $rootPages = PageModel::findByType('root', ['order' => 'sorting ASC']);
        if (!$rootPages) {
            return null;
        }

        // Get root page with current host
        foreach ($rootPages as $rootPage) {
            if ($rootPage->dns && $rootPage->dns === $host) {
                return $rootPage;
            }
        }

        // Return first root page
        return $rootPages->getModels()[0];
    }
}
