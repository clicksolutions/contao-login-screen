<?php

/**
 * Contao bundle contao-login-screen
 *
 * @copyright click solutions GmbH 2023 <https://www.click-solutions.de>
 * @author    René Fehrmann <rf@click-solutions.de>
 */

declare(strict_types=1);

namespace ClickSolutions\ContaoLoginScreenBundle\EventListener\Hooks;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Image\Studio\Figure;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\Image\PictureConfiguration;
use Contao\Image\PictureConfigurationItem;
use Contao\Image\ResizeConfiguration;
use Contao\PageModel;
use Contao\StringUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\RequestStack;

class OutputBackendTemplateListener
{
    private const TEMPLATES = ['be_login', 'be_login_two_factor'];
    private const ASSET_PACKAGE = 'click_solutions_contao_login_screen';
    private const ASSET_PATH = 'css/contao-login-screen.css';

    /**
     * @var array<string, true>
     */
    private array $warned = [];

    public function __construct(
        private readonly string $projectDir,
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly Studio $studio,
        private readonly Packages $packages,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Inject logo, background image and text into the rendered backend login templates.
     */
    public function __invoke(string $buffer, string $templateName): string
    {
        if (!\in_array($templateName, self::TEMPLATES, true)) {
            return $buffer;
        }

        // The login screen must never break, so any error returns the unchanged output
        try {
            $this->warned = [];

            return $this->injectIntoBuffer($buffer);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('Login screen: %s', $e->getMessage()), ['exception' => $e]);

            return $buffer;
        }
    }

    private function injectIntoBuffer(string $buffer): string
    {
        $this->framework->initialize();

        // Get root page or return unchanged
        $rootPage = $this->determineRootPage();
        if (null === $rootPage) {
            return $buffer;
        }

        $row = $rootPage->row();

        // Add the stylesheet (only needed on the login screen)
        $buffer = $this->insertBefore($buffer, '</head>', sprintf('<link rel="stylesheet" href="%s">', htmlspecialchars($this->packages->getUrl(self::ASSET_PATH, self::ASSET_PACKAGE), ENT_QUOTES, 'UTF-8')));

        // Add body class and background image (directly after the opening body tag)
        $backgroundHtml = $this->generateBackgroundImageHtml($row['cs_cls_bg_image'] ?? null);
        $blurClass = !empty($row['cs_cls_bg_image_blur']) ? ' cs_cls_bg_image_blur' : '';
        $buffer = $this->replaceOnce(
            $buffer,
            '/<body\b([^>]*)>/',
            fn (array $m): string => $this->prepareBody($m[1]) . (null !== $backgroundHtml ? sprintf('<div class="cs_cls_bg_image%s">%s</div>', $blurClass, $backgroundHtml) : ''),
            'body'
        );

        // Add logo (before the headline)
        $logoHtml = $this->generateLogoHtml($row['cs_cls_logo'] ?? null);
        if (null !== $logoHtml) {
            $buffer = $this->replaceOnce(
                $buffer,
                '/<h1\b/',
                static fn (array $m): string => sprintf('<div class="cs_cls_logo">%s</div>', $logoHtml) . $m[0],
                'h1'
            );
        }

        // Add plain text (at the end of the main block, below the login providers like passkey)
        $text = trim(strip_tags((string) ($row['cs_cls_text'] ?? '')));
        if ('' !== $text) {
            $buffer = $this->insertBefore($buffer, '</main>', sprintf('<div class="cs_cls_text">%s</div>', htmlspecialchars($text, ENT_QUOTES, 'UTF-8', false)));
        }

        // Move the frontend link into the main block, so it stays readable on top of the background image
        return $this->moveFrontendLinkIntoMain($buffer);
    }

    private function moveFrontendLinkIntoMain(string $buffer): string
    {
        // The two-factor template has no frontend link
        if (!preg_match('#\s*<p class="fe-link">.*?</p>#s', $buffer, $m)) {
            return $buffer;
        }

        // Only move the link if the target anchor exists, otherwise it would get lost
        if (false === strrpos($buffer, '</main>')) {
            $this->warnAnchorMissing('</main>');

            return $buffer;
        }

        return $this->insertBefore(str_replace($m[0], '', $buffer), '</main>', trim($m[0]));
    }

    private function prepareBody(string $attributes): string
    {
        // Add our class to the existing class attribute of the body tag (but not to e.g. data-class)
        $pattern = '/(?<![\w-])class=(["\'])(.*?)\1/';
        if (preg_match($pattern, $attributes)) {
            $attributes = preg_replace($pattern, 'class=$1$2 cs_cls_login$1', $attributes, 1) ?? $attributes;
        } else {
            $attributes .= ' class="cs_cls_login"';
        }

        return '<body' . $attributes . '>';
    }

    /**
     * @param callable(array<int, string>): string $callback
     */
    private function replaceOnce(string $buffer, string $pattern, callable $callback, string $anchor): string
    {
        $result = preg_replace_callback($pattern, $callback, $buffer, 1, $count);
        if (null === $result || 0 === $count) {
            $this->warnAnchorMissing($anchor);

            return $buffer;
        }

        return $result;
    }

    /**
     * Insert the HTML before the last occurrence of the anchor.
     */
    private function insertBefore(string $buffer, string $anchor, string $html): string
    {
        $pos = strrpos($buffer, $anchor);
        if (false === $pos) {
            $this->warnAnchorMissing($anchor);

            return $buffer;
        }

        return substr_replace($buffer, $html, $pos, 0);
    }

    private function warnAnchorMissing(string $anchor): void
    {
        if (isset($this->warned[$anchor])) {
            return;
        }

        $this->warned[$anchor] = true;
        $this->logger->warning(sprintf('Login screen: anchor "%s" not found, nothing injected.', $anchor));
    }

    private function generateLogoHtml(mixed $uuid): null|string
    {
        $logo = $this->findFile($uuid);
        if (null === $logo) {
            return null;
        }

        // Create image
        $figure = $this->studio->createFigureBuilder()
            ->fromFilesModel($logo)
            ->setSize([260, null, 'proportional', '1,1.5,2'])
            ->build();

        return $this->renderFigure($figure);
    }

    private function generateBackgroundImageHtml(mixed $value): null|string
    {
        // Get selected background images or return unchanged
        $uuids = array_values(array_filter(
            $this->framework->getAdapter(StringUtil::class)->deserialize($value, true),
            static fn (mixed $uuid): bool => \is_string($uuid) && '' !== $uuid,
        ));
        if ([] === $uuids) {
            return null;
        }

        // Get random background image
        $image = $this->findFile($uuids[array_rand($uuids)]);
        if (null === $image) {
            return null;
        }

        // Create picture configuration
        $pictureConfig = (new PictureConfiguration())
            ->setFormats(['jpg' => ['webp']])
            ->setSize($this->createPictureItem(576, 800))
            ->setSizeItems([
                $this->createPictureItem(1920, 1080, '(min-width: 1400px)'),
                $this->createPictureItem(1400, null, '(min-width: 1200px)'),
                $this->createPictureItem(1200, null, '(min-width: 992px)'),
                $this->createPictureItem(992, null, '(min-width: 768px)'),
                $this->createPictureItem(768, null, '(min-width: 576px)'),
            ]);

        // Create image
        $figure = $this->studio->createFigureBuilder()
            ->fromFilesModel($image)
            ->setSize($pictureConfig)
            ->build();

        return $this->renderFigure($figure);
    }

    private function createPictureItem(int $width, null|int $height, null|string $media = null): PictureConfigurationItem
    {
        $item = (new PictureConfigurationItem())
            ->setDensities('1x,1.5x,2x')
            ->setResizeConfig((new ResizeConfiguration())->setWidth($width)->setHeight($height ?? 0));

        return null !== $media ? $item->setMedia($media) : $item;
    }

    private function renderFigure(Figure $figure): string
    {
        // Get image template
        $template = new FrontendTemplate('image');
        $figure->applyLegacyTemplateData($template);

        return $template->parse();
    }

    private function findFile(mixed $uuid): null|FilesModel
    {
        if (!\is_string($uuid) || '' === $uuid) {
            return null;
        }

        $file = $this->framework->getAdapter(FilesModel::class)->findByUuid($uuid);
        if (null === $file || !is_readable($this->projectDir . '/' . $file->path)) {
            return null;
        }

        return $file;
    }

    private function determineRootPage(): null|PageModel
    {
        $host = $this->requestStack->getCurrentRequest()?->getHost();

        // Get all root pages
        $rootPages = $this->framework->getAdapter(PageModel::class)->findBy('type', 'root', ['order' => 'sorting ASC']);
        if (null === $rootPages) {
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
