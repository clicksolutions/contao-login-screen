<?php

/**
 * Contao bundle contao-login-screen
 *
 * @copyright click solutions GmbH 2023 <https://www.click-solutions.de>
 * @author    René Fehrmann <rf@click-solutions.de>
 */

declare(strict_types=1);

namespace ClickSolutions\ContaoLoginScreenBundle\Tests\EventListener\Hooks;

use ClickSolutions\ContaoLoginScreenBundle\EventListener\Hooks\OutputBackendTemplateListener;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\FilesModel;
use Contao\Model\Collection;
use Contao\PageModel;
use Contao\StringUtil;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class OutputBackendTemplateListenerTest extends TestCase
{
    private const PACKAGE = 'click_solutions_contao_login_screen';

    private RecordingLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
    }

    public function testIgnoresOtherTemplates(): void
    {
        $listener = $this->createListener([$this->createRootPage()]);

        self::assertSame('<html></html>', $listener('<html></html>', 'be_main'));
        self::assertSame([], $this->logger->records);
    }

    public function testReturnsBufferUnchangedWithoutRootPage(): void
    {
        $buffer = $this->fixture('be_login');

        self::assertSame($buffer, $this->createListener([])($buffer, 'be_login'));
    }

    public function testInjectsStylesheetBeforeHeadEnd(): void
    {
        $output = $this->render([$this->createRootPage()]);

        self::assertMatchesRegularExpression(
            '#<link rel="stylesheet" href="[^"]*css/contao-login-screen\.css">\s*</head>#',
            $output,
        );
    }

    public function testAddsBodyClassToExistingClassAttribute(): void
    {
        $output = $this->render([$this->createRootPage()]);

        self::assertStringContainsString('<body class="be_login cs_cls_login" data-foo="bar">', $output);
    }

    public function testAddsBodyClassWhenBodyHasNoClassAttribute(): void
    {
        $buffer = str_replace('<body class="be_login" data-foo="bar">', '<body data-class="x">', $this->fixture('be_login'));

        $output = $this->createListener([$this->createRootPage()])($buffer, 'be_login');

        // data-class must not be touched, the class attribute is added instead
        self::assertStringContainsString('<body data-class="x" class="cs_cls_login">', $output);
    }

    public function testMovesFrontendLinkIntoMain(): void
    {
        $output = $this->render([$this->createRootPage()]);

        $linkPos = strpos($output, '<p class="fe-link">');
        self::assertNotFalse($linkPos);
        self::assertSame(1, substr_count($output, 'class="fe-link"'));
        self::assertLessThan(strpos($output, '</main>'), $linkPos);
        self::assertGreaterThan(strpos($output, '<main'), $linkPos);
    }

    public function testInjectsEscapedPlainTextBelowLoginProviders(): void
    {
        $output = $this->render([$this->createRootPage(['cs_cls_text' => '<b>Hallo</b> <script>x</script> & Welt'])]);

        self::assertStringContainsString('<div class="cs_cls_text">Hallo x &amp; Welt</div>', $output);
        self::assertStringNotContainsString('<script>x', $output);
        self::assertLessThan(strpos($output, '</main>'), strpos($output, 'cs_cls_text"'));
    }

    public function testDoesNotDoubleEncodeEntities(): void
    {
        $output = $this->render([$this->createRootPage(['cs_cls_text' => 'A &amp; B'])]);

        self::assertStringContainsString('>A &amp; B</div>', $output);
    }

    public function testNoTextBlockForEmptyText(): void
    {
        $output = $this->render([$this->createRootPage(['cs_cls_text' => '  <i></i> '])]);

        self::assertStringNotContainsString('cs_cls_text', $output);
    }

    public function testSkipsLogoAndBackgroundWithoutFiles(): void
    {
        $output = $this->render([$this->createRootPage()]);

        self::assertStringNotContainsString('cs_cls_logo', $output);
        self::assertStringNotContainsString('class="cs_cls_bg_image', $output);
    }

    public function testSkipsLogoAndBackgroundWhenFileDoesNotExist(): void
    {
        $output = $this->render([$this->createRootPage([
            'cs_cls_logo' => 'uuid-logo',
            'cs_cls_bg_image' => serialize(['uuid-bg']),
            'cs_cls_bg_image_blur' => '1',
        ])]);

        self::assertStringNotContainsString('cs_cls_logo', $output);
        self::assertStringNotContainsString('<div class="cs_cls_bg_image', $output);
        self::assertSame([], $this->logger->records);
    }

    public function testTwoFactorTemplateHasNoFrontendLinkAndNoWarning(): void
    {
        $output = $this->createListener([$this->createRootPage(['cs_cls_text' => 'Hinweis'])])(
            $this->fixture('be_login_two_factor'),
            'be_login_two_factor',
        );

        self::assertStringContainsString('class="be_login_two_factor cs_cls_login"', $output);
        self::assertStringContainsString('<div class="cs_cls_text">Hinweis</div>', $output);
        self::assertStringNotContainsString('fe-link', $output);
        self::assertSame([], $this->logger->records);
    }

    public function testWarnsOnceWhenAnchorIsMissing(): void
    {
        $buffer = '<html><head></head><body><p>no main</p><p class="fe-link"><a href="/">x</a></p></body></html>';

        $output = $this->createListener([$this->createRootPage(['cs_cls_text' => 'Hinweis'])])($buffer, 'be_login');

        // Missing </main>: text is not injected and the frontend link stays where it is
        self::assertStringNotContainsString('cs_cls_text', $output);
        self::assertStringContainsString('<p class="fe-link"><a href="/">x</a></p></body>', $output);

        $messages = array_column($this->logger->records, 'message');
        self::assertCount(count(array_unique($messages)), $messages, 'Each missing anchor must only be logged once');
        self::assertContains('Login screen: anchor "</main>" not found, nothing injected.', $messages);
    }

    public function testWarningsAreResetBetweenInvocations(): void
    {
        $listener = $this->createListener([$this->createRootPage(['cs_cls_text' => 'x'])]);
        $buffer = '<html><head></head><body></body></html>';

        $listener($buffer, 'be_login');
        $first = count($this->logger->records);
        $listener($buffer, 'be_login');

        self::assertGreaterThan(0, $first);
        self::assertSame($first * 2, count($this->logger->records));
    }

    public function testReturnsUnchangedBufferAndLogsErrorOnException(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->method('initialize')->willThrowException(new \RuntimeException('boom'));
        $buffer = $this->fixture('be_login');

        $listener = new OutputBackendTemplateListener(
            __DIR__,
            $framework,
            new RequestStack(),
            $this->createMock(Studio::class),
            $this->createPackages(),
            $this->logger,
        );

        self::assertSame($buffer, $listener($buffer, 'be_login'));
        self::assertCount(1, $this->logger->records);
        self::assertSame('error', $this->logger->records[0]['level']);
        self::assertSame('Login screen: boom', $this->logger->records[0]['message']);
    }

    public function testPrefersRootPageMatchingCurrentHost(): void
    {
        $first = $this->createRootPage(['cs_cls_text' => 'Erste']);
        $matching = $this->createRootPage(['cs_cls_text' => 'Passend'], 'login.example.test');

        $output = $this->render([$first, $matching], 'login.example.test');

        self::assertStringContainsString('Passend', $output);
        self::assertStringNotContainsString('Erste', $output);
    }

    public function testFallsBackToFirstRootPageForUnknownHost(): void
    {
        $first = $this->createRootPage(['cs_cls_text' => 'Erste']);
        $other = $this->createRootPage(['cs_cls_text' => 'Andere'], 'other.example.test');

        $output = $this->render([$first, $other], 'unknown.example.test');

        self::assertStringContainsString('Erste', $output);
    }

    /**
     * @param list<PageModel> $rootPages
     */
    private function render(array $rootPages, string $host = 'example.test'): string
    {
        return $this->createListener($rootPages, $host)($this->fixture('be_login'), 'be_login');
    }

    /**
     * @param list<PageModel> $rootPages
     */
    private function createListener(array $rootPages, string $host = 'example.test'): OutputBackendTemplateListener
    {
        $pageAdapter = $this->adapter([
            'findBy' => static fn (): ?Collection => [] === $rootPages ? null : new Collection($rootPages, 'tl_page'),
        ]);
        $filesAdapter = $this->adapter(['findByUuid' => static fn (): ?FilesModel => null]);
        $stringUtilAdapter = $this->adapter([
            'deserialize' => static fn (mixed $value): array => \is_string($value) ? (array) unserialize($value) : [],
        ]);

        $framework = $this->createMock(ContaoFramework::class);
        $framework->method('getAdapter')->willReturnCallback(
            static fn (string $class): Adapter => match ($class) {
                PageModel::class => $pageAdapter,
                FilesModel::class => $filesAdapter,
                StringUtil::class => $stringUtilAdapter,
                default => throw new \LogicException('Unexpected adapter ' . $class),
            },
        );

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://' . $host . '/contao'));

        return new OutputBackendTemplateListener(
            __DIR__,
            $framework,
            $requestStack,
            $this->createMock(Studio::class),
            $this->createPackages(),
            $this->logger,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function createRootPage(array $row = [], string $dns = ''): PageModel
    {
        $row += ['cs_cls_logo' => null, 'cs_cls_bg_image' => null, 'cs_cls_bg_image_blur' => '', 'cs_cls_text' => '', 'dns' => $dns];

        $page = $this->createMock(PageModel::class);
        $page->method('row')->willReturn($row);
        $page->method('__get')->willReturnCallback(static fn (string $key): mixed => $row[$key] ?? null);

        return $page;
    }

    /**
     * @param array<string, \Closure> $methods
     */
    private function adapter(array $methods): Adapter
    {
        return new class($methods) extends Adapter {
            /**
             * @param array<string, \Closure> $methods
             */
            public function __construct(private readonly array $methods)
            {
            }

            public function __call(string $name, array $arguments): mixed
            {
                return ($this->methods[$name] ?? throw new \LogicException('Unexpected call ' . $name))(...$arguments);
            }
        };
    }

    private function createPackages(): Packages
    {
        return new Packages(new Package(new EmptyVersionStrategy()), [self::PACKAGE => new Package(new EmptyVersionStrategy())]);
    }

    private function fixture(string $name): string
    {
        $content = file_get_contents(__DIR__ . '/../../Fixtures/' . $name . '.html');
        self::assertIsString($content);

        return $content;
    }
}

final class RecordingLogger extends AbstractLogger
{
    /**
     * @var list<array{level: string, message: string}>
     */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'message' => (string) $message];
    }
}
