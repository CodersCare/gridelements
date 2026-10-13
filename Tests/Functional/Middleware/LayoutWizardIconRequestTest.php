<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\Middleware;

use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Http\Application;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The wizard only passes icon identifiers, <typo3-backend-icon> fetches the markup with a separate request.
 */
class LayoutWizardIconRequestTest extends FunctionalTestCase
{
    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><rect width="16" height="16"/></svg>';
    // FAL files in a public storage outside fileadmin and in fileadmin
    private const FILES = ['/home/global/design/gridelements/icon_benefits-country.svg', '/fileadmin/gridelements-icon-test/icon_benefits-country.svg'];

    // Grid layouts of page 1 with an icon source and the expected icon markup
    private const LAYOUT_ICONS = [
        'global_icon' => '<svg',
        'fileadmin_icon' => '<svg',
        '3' => '<svg',
        'ts_svg' => '<svg',
        'ts_bitmap' => 'NotFound.png',
    ];

    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/wizard_icons.csv');
        foreach (self::FILES as $file) {
            GeneralUtility::mkdir_deep(dirname(Environment::getPublicPath() . $file));
            file_put_contents(Environment::getPublicPath() . $file, self::SVG);
        }
    }

    protected function tearDown(): void
    {
        GeneralUtility::rmdir(Environment::getPublicPath() . '/home', true);
        GeneralUtility::rmdir(Environment::getPublicPath() . '/fileadmin/gridelements-icon-test', true);
        parent::tearDown();
    }

    #[Test]
    public function wizardRequestRegistersLayoutIcons(): array
    {
        $icons = $this->requestWizardIcons(0);
        $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
        $configurations = [];
        foreach (array_keys(self::LAYOUT_ICONS) as $layoutId) {
            self::assertArrayHasKey($layoutId, $icons);
            self::assertTrue($iconRegistry->isRegistered($icons[$layoutId]), (string)$layoutId);
            $configurations[$layoutId] = $iconRegistry->getIconConfigurationByIdentifier($icons[$layoutId]);
        }
        return ['icons' => $icons, 'configurations' => $configurations, 'pid' => getmypid()];
    }

    #[Test]
    public function identifiersNamePageAndLayoutInsteadOfThePosition(): void
    {
        $icons = $this->requestWizardIcons(0);
        // page column 1 only allows the layout global_icon
        $filteredIcons = $this->requestWizardIcons(1);

        foreach (array_keys(self::LAYOUT_ICONS) as $layoutId) {
            self::assertSame('gridelements-layout-1-' . $layoutId, $icons[$layoutId]);
        }
        self::assertSame(['global_icon'], array_keys($filteredIcons));
        self::assertSame($icons['global_icon'], $filteredIcons['global_icon']);
        self::assertSame(array_unique($icons), $icons);
        $tsSvg = GeneralUtility::makeInstance(IconRegistry::class)->getIconConfigurationByIdentifier($icons['ts_svg']);
        self::assertSame('EXT:gridelements/Resources/Public/Icons/Extension.svg', $tsSvg['options']['source']);
        self::assertSame('content-text', $icons['ts_explicit']);
        self::assertSame('gridelements-default', $icons['ts_without_icon']);
    }

    #[Test]
    public function iconMiddlewareRunsAfterTheBackendAuthentication(): void
    {
        $middlewares = array_keys($this->get('backend.middlewares')->getArrayCopy());
        $authentication = array_search('typo3/cms-backend/authentication', $middlewares, true);
        $routing = array_search('typo3/cms-backend/backend-routing', $middlewares, true);
        $layoutWizardIcons = array_search('gridelementsteam/gridelements/layout-wizard-icons', $middlewares, true);

        self::assertIsInt($layoutWizardIcons);
        // the stack lists the middlewares in reverse order of their execution
        self::assertSame($routing > $authentication, $authentication > $layoutWizardIcons);
    }

    #[Test]
    #[Depends('wizardRequestRegistersLayoutIcons')]
    #[RunInSeparateProcess]
    public function freshIconRequestRendersTheWizardIcons(array $wizard): void
    {
        self::assertNotSame($wizard['pid'], getmypid());
        $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
        foreach (self::LAYOUT_ICONS as $layoutId => $expectedMarkup) {
            $identifier = $wizard['icons'][$layoutId];
            self::assertFalse($iconRegistry->isRegistered($identifier), $identifier . ' is registered before the icon request');

            $markup = (string)$this->requestIcon($identifier)->getBody();

            self::assertStringContainsString('data-identifier="' . $identifier . '"', $markup, (string)$layoutId);
            self::assertStringContainsString($expectedMarkup, $markup, (string)$layoutId);
            self::assertStringNotContainsString('default-not-found', $markup, (string)$layoutId);
            // a FAL public URL may resolve against another public path in this process
            $configuration = $iconRegistry->getIconConfigurationByIdentifier($identifier);
            self::assertSame($wizard['configurations'][$layoutId]['provider'], $configuration['provider'], (string)$layoutId);
            self::assertStringEndsWith($configuration['options']['source'], $wizard['configurations'][$layoutId]['options']['source'], (string)$layoutId);
        }
    }

    #[Test]
    #[RunInSeparateProcess]
    public function iconRequestRegistersOnlyIconsOfLayoutsOfTheRequestedPage(): void
    {
        $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
        $identifiers = [
            'gridelements-layout-2-ts_svg',
            'gridelements-layout-0-ts_svg',
            'gridelements-layout-1-ts_explicit',
            'gridelements-layout-1-ts_without_icon',
            'gridelements-layout-1-missing',
            'gridelements-ts_svg',
        ];
        foreach ($identifiers as $identifier) {
            $markup = (string)$this->requestIcon($identifier)->getBody();

            self::assertStringContainsString('default-not-found', $markup, $identifier);
            self::assertFalse($iconRegistry->isRegistered($identifier), $identifier);
        }
        $markup = (string)$this->requestIcon('gridelements-layout-1-ts_svg')->getBody();
        self::assertStringContainsString('<svg', $markup);
        self::assertStringNotContainsString('default-not-found', $markup);
        self::assertFalse($iconRegistry->isRegistered('gridelements-layout-1-ts_bitmap'));
    }

    #[Test]
    #[RunInSeparateProcess]
    public function iconRequestRequiresGridElementsAndPageAccess(): void
    {
        $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
        // user 2 may use grid elements without page access, user 3 has page access without grid elements
        foreach ([2 => 'gridelements-layout-1-global_icon', 3 => 'gridelements-layout-1-3'] as $userUid => $identifier) {
            $markup = (string)$this->requestIcon($identifier, $userUid)->getBody();

            self::assertStringContainsString('default-not-found', $markup, $identifier);
            self::assertFalse($iconRegistry->isRegistered($identifier), $identifier);
        }
        $markup = (string)$this->requestIcon('gridelements-layout-1-fileadmin_icon', 4)->getBody();
        self::assertStringContainsString('<svg', $markup);
        self::assertTrue($iconRegistry->isRegistered('gridelements-layout-1-fileadmin_icon'));
    }

    #[Test]
    #[RunInSeparateProcess]
    public function otherRequestsDoNotLoadLayouts(): void
    {
        $layoutSetup = new class extends LayoutSetup {
            public bool $initialized = false;

            public function init(int $pageId, array $typoScriptSetup = []): LayoutSetup
            {
                $this->initialized = true;
                return parent::init($pageId, $typoScriptSetup);
            }
        };
        GeneralUtility::addInstance(LayoutSetup::class, $layoutSetup);
        $layoutIcon = json_encode(['gridelements-layout-1-ts_svg', 'small', null, 'default', 'inline']);

        $markup = (string)$this->requestIcon('actions-add')->getBody();
        $defaultMarkup = (string)$this->requestIcon('gridelements-default')->getBody();
        $this->executeBackendRequest('ajax_login_timedout', ['icon' => $layoutIcon]);

        self::assertStringContainsString('data-identifier="actions-add"', $markup);
        self::assertStringContainsString('data-identifier="gridelements-default"', $defaultMarkup);
        self::assertFalse($layoutSetup->initialized);
        self::assertFalse(GeneralUtility::makeInstance(IconRegistry::class)->isRegistered('gridelements-layout-1-ts_svg'));

        $this->requestIcon('gridelements-layout-1-ts_svg');
        self::assertTrue($layoutSetup->initialized);
        GeneralUtility::purgeInstances();
    }

    /**
     * Icon identifiers of the grid layouts in the wizard of page 1, keyed by layout
     *
     * @return string[]
     */
    private function requestWizardIcons(int $colPos): array
    {
        $html = (string)$this->executeBackendRequest('new_content_element_wizard', [
            'id' => 1,
            'colPos' => $colPos,
            'uid_pid' => 1,
            'sys_language_uid' => 0,
        ])->getBody();
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        // TYPO3 12 and 13 tag names
        $wizard = $document->getElementsByTagName('typo3-backend-new-record-wizard')->item(0)
            ?? $document->getElementsByTagName('typo3-backend-new-content-element-wizard')->item(0);
        self::assertNotNull($wizard, 'No wizard in ' . $html);
        $icons = [];
        foreach (json_decode($wizard->getAttribute('categories'), true) as $category) {
            foreach ($category['items'] as $item) {
                if (str_starts_with($item['identifier'], 'gridelements_')) {
                    $icons[substr($item['identifier'], strlen('gridelements_'))] = $item['icon'];
                }
            }
        }
        return $icons;
    }

    private function requestIcon(string $identifier, int $userUid = 1): ResponseInterface
    {
        // the parameters sent by <typo3-backend-icon>
        return $this->executeBackendRequest('ajax_icons', ['icon' => json_encode([$identifier, 'small', null, 'default', 'inline'])], $userUid);
    }

    private function executeBackendRequest(string $route, array $parameters, int $userUid = 1): ResponseInterface
    {
        $backendUser = $this->setUpBackendUser($userUid);
        $uri = 'https://typo3-testing.local' . $this->get(UriBuilder::class)->buildUriFromRoute($route, $parameters);
        $uriParts = parse_url($uri);
        // TYPO3 12 routes backend requests relative to the /typo3/ entry point
        $scriptName = (new Typo3Version())->getMajorVersion() < 13 ? '/typo3/index.php' : '/index.php';
        $request = (new ServerRequest($uri, 'GET', 'php://input', [], [
            'DOCUMENT_ROOT' => Environment::getPublicPath(),
            'HTTP_HOST' => $uriParts['host'],
            'SERVER_NAME' => $uriParts['host'],
            'HTTPS' => 'on',
            'SERVER_PORT' => '443',
            'REMOTE_ADDR' => '127.0.0.1',
            'SCRIPT_NAME' => $scriptName,
            'SCRIPT_FILENAME' => Environment::getPublicPath() . $scriptName,
            'REQUEST_URI' => $uriParts['path'] . '?' . $uriParts['query'],
            'QUERY_STRING' => $uriParts['query'],
            'REQUEST_METHOD' => 'GET',
        ]))
            ->withQueryParams(GeneralUtility::explodeUrl2Array($uriParts['query']))
            ->withCookieParams(['be_typo_user' => $backendUser->getSession()->getJwt()]);
        $response = $this->get(Application::class)->handle($request);
        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        return $response;
    }
}
