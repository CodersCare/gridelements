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
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * TYPO3 12 wizards only pass icon identifiers, <typo3-backend-icon> and Icons.getIcon() fetch the markup
 * with a separate request. TYPO3 11 renders the icons into the wizard response.
 */
class LayoutWizardIconRequestTest extends FunctionalTestCase
{
    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><rect width="16" height="16"/></svg>';
    // FAL files in a public storage outside fileadmin and in fileadmin, the latter also referenced below the web root
    private const FILES = ['/home/global/design/gridelements/icon_benefits-country.svg', '/fileadmin/gridelements-icon-test/icon_benefits-country.svg'];

    // Grid layouts of page 1 with an icon source and the expected inline markup
    private const LAYOUT_ICONS = [
        'global_icon' => '<svg',
        'fileadmin_icon' => '<svg',
        '3' => '<svg',
        'ts_svg' => '<svg',
        'ts_webroot' => '<svg',
        'ts_bitmap' => 'NotFound.png',
    ];

    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    // Only the PHPUnit error handler, like TYPO3_TESTING_FUNCTIONAL_REMOVE_ERROR_HANDLER, keeps isolated processes intact
    protected array $configurationToUseInTestInstance = ['SYS' => ['errorHandler' => '']];

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
        $this->skipBelowTypo3Twelve();
        $icons = $this->requestWizardIcons(0);
        $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
        $configurations = [];
        foreach (array_keys(self::LAYOUT_ICONS) as $layoutId) {
            self::assertArrayHasKey($layoutId, $icons);
            self::assertTrue($iconRegistry->isRegistered($icons[$layoutId]), (string)$layoutId);
            $configurations[$layoutId] = $iconRegistry->getIconConfigurationByIdentifier($icons[$layoutId]);
        }
        return ['icons' => $icons, 'configurations' => $configurations, 'pid' => getmypid(), 'publicPath' => Environment::getPublicPath()];
    }

    #[Test]
    public function identifiersNamePageAndLayoutInsteadOfThePosition(): void
    {
        $this->skipBelowTypo3Twelve();
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
        $this->skipBelowTypo3Twelve();
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
        $this->skipBelowTypo3Twelve();
        self::assertNotSame($wizard['pid'], getmypid());
        $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
        foreach (self::LAYOUT_ICONS as $layoutId => $expectedMarkup) {
            $identifier = $wizard['icons'][$layoutId];
            self::assertFalse($iconRegistry->isRegistered($identifier), $identifier . ' is registered before the icon request');

            $markup = (string)$this->requestIcon($identifier)->getBody();

            self::assertStringContainsString('data-identifier="' . $identifier . '"', $markup, (string)$layoutId);
            self::assertStringContainsString($expectedMarkup, $markup, (string)$layoutId);
            self::assertStringNotContainsString('default-not-found', $markup, (string)$layoutId);
            // the same file, FAL URLs get the site path prefix only behind the middlewares
            $configuration = $iconRegistry->getIconConfigurationByIdentifier($identifier);
            self::assertSame($wizard['configurations'][$layoutId]['provider'], $configuration['provider'], (string)$layoutId);
            self::assertSame(
                ltrim(str_replace($wizard['publicPath'], '', $wizard['configurations'][$layoutId]['options']['source']), '/'),
                ltrim(str_replace(Environment::getPublicPath(), '', $configuration['options']['source']), '/'),
                (string)$layoutId
            );
            // the drag in wizard requests the default markup
            $defaultMarkup = (string)$this->requestIcon($identifier, 1, 'default')->getBody();
            self::assertMatchesRegularExpression('#<img src="[^"]*(icon_benefits-country\.svg|Extension\.svg|NotFound\.png)"#', $defaultMarkup, (string)$layoutId);
        }
    }

    #[Test]
    #[RunInSeparateProcess]
    public function iconRequestRegistersOnlyIconsOfLayoutsOfTheRequestedPage(): void
    {
        $this->skipBelowTypo3Twelve();
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
        $this->skipBelowTypo3Twelve();
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
        $this->skipBelowTypo3Twelve();
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

    #[Test]
    public function typo3ElevenRendersTheLayoutIconsIntoTheWizardResponse(): array
    {
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::markTestSkipped('TYPO3 12 fetches wizard icons with a separate request.');
        }
        $items = $this->requestWizardItemsOfTypo3Eleven(0);
        $expectedImages = [
            'global_icon' => '#<img src="[^"]*(Extension|icon_benefits-country)\.svg"#',
            'fileadmin_icon' => '#<img src="/?fileadmin/gridelements-icon-test/icon_benefits-country\.svg"#',
            '3' => '#<img src="/?home/global/design/gridelements/icon_benefits-country\.svg"#',
            'ts_svg' => '#<img src="[^"]*Extension\.svg"#',
            'ts_webroot' => '#<img src="/fileadmin/gridelements-icon-test/icon_benefits-country\.svg"#',
            'ts_bitmap' => '#<img src="[^"]*NotFound\.png"#',
        ];
        foreach ($expectedImages as $layoutId => $pattern) {
            self::assertArrayHasKey((string)$layoutId, $items);
            self::assertMatchesRegularExpression($pattern, $items[$layoutId]['icon'], (string)$layoutId);
            self::assertStringNotContainsString('default-not-found', $items[$layoutId]['icon'], (string)$layoutId);
            self::assertStringNotContainsString('typo3-backend-icon', $items[$layoutId]['icon'], (string)$layoutId);
        }
        self::assertStringContainsString('data-identifier="content-text"', $items['ts_explicit']['icon']);
        self::assertStringContainsString('data-identifier="gridelements-default"', $items['ts_without_icon']['icon']);
        self::assertMatchesRegularExpression('#<img src="/home/global/design/gridelements/icon_benefits-country\.svg"#', $items['global_icon']['icon']);
        self::assertNotSame($items['global_icon']['identifier'], $items['ts_svg']['identifier']);
        return ['items' => $items, 'pid' => getmypid()];
    }

    #[Test]
    #[Depends('typo3ElevenRendersTheLayoutIconsIntoTheWizardResponse')]
    #[RunInSeparateProcess]
    public function typo3ElevenRendersTheOwnIconOfALayoutInAFilteredColumn(array $wizard): void
    {
        self::assertNotSame($wizard['pid'], getmypid());
        // page column 1 only allows the layout global_icon, which gets the identifier of another layout of the unfiltered list
        $filteredItems = $this->requestWizardItemsOfTypo3Eleven(1);

        self::assertSame(['global_icon'], array_keys($filteredItems));
        self::assertSame($wizard['items']['ts_svg']['identifier'], $filteredItems['global_icon']['identifier']);
        self::assertMatchesRegularExpression('#<img src="/home/global/design/gridelements/icon_benefits-country\.svg"#', $filteredItems['global_icon']['icon']);
    }

    #[Test]
    public function typo3ElevenLeavesIconRequestsUnchanged(): void
    {
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::markTestSkipped('TYPO3 12 registers the requested layout icon.');
        }
        $middlewares = array_keys($this->get('backend.middlewares')->getArrayCopy());
        self::assertNotContains('gridelementsteam/gridelements/layout-wizard-icons', $middlewares);

        $markup = (string)$this->requestIcon('gridelements-layout-1-ts_svg', 4)->getBody();
        self::assertStringContainsString('default-not-found', $markup);
        self::assertFalse(GeneralUtility::makeInstance(IconRegistry::class)->isRegistered('gridelements-layout-1-ts_svg'));
    }

    private function skipBelowTypo3Twelve(): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            self::markTestSkipped('TYPO3 11 renders wizard icons into the wizard response.');
        }
    }

    /**
     * Icon identifiers of the grid layouts in the TYPO3 12 wizard of page 1, keyed by layout
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
        $wizard = $document->getElementsByTagName('typo3-backend-new-content-element-wizard')->item(0);
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

    /**
     * Rendered grid layout items of the TYPO3 11 wizard of page 1, keyed by layout
     *
     * @return array<string, array{icon: string, identifier: string}>
     */
    private function requestWizardItemsOfTypo3Eleven(int $colPos): array
    {
        $parameters = ['id' => 1, 'colPos' => $colPos];
        // the column restrictions GridElementsOnReady.js adds from the page module (Hooks\PageLayoutController)
        $GLOBALS['LANG'] ??= GeneralUtility::makeInstance(LanguageServiceFactory::class)->createFromUserPreferences($this->setUpBackendUser(1));
        $selectedLayout = GeneralUtility::makeInstance(BackendLayoutView::class)->getSelectedBackendLayout(1);
        $columns = GeneralUtility::makeInstance(LayoutSetup::class)->init(0)
            ->checkAvailableColumns(['config' => $selectedLayout['__config']['backend_layout.']], true);
        foreach (['allowed', 'disallowed'] as $restriction) {
            if (!empty($columns[$restriction][$colPos])) {
                $parameters['tx_gridelements_' . $restriction] = base64_encode((string)json_encode($columns[$restriction][$colPos]));
            }
        }
        $html = (string)$this->executeBackendRequest('new_content_element_wizard', $parameters + [
            'uid_pid' => 1,
            'sys_language_uid' => 0,
        ])->getBody();
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $items = [];
        foreach ($xpath->query('//div[contains(@class, "t3js-media-new-content-element-wizard")]') as $item) {
            $target = (string)$xpath->query('.//button', $item)->item(0)?->getAttribute('data-target');
            parse_str((string)parse_url($target, PHP_URL_QUERY), $parameters);
            $layoutId = $parameters['defVals']['tt_content']['tx_gridelements_backend_layout'] ?? null;
            if ($layoutId === null) {
                continue;
            }
            $icon = $xpath->query('.//div[contains(@class, "media-left")]', $item)->item(0);
            $items[(string)$layoutId] = [
                'icon' => $icon ? (string)$document->saveHTML($icon) : '',
                'identifier' => (string)$xpath->query('.//span[contains(@class, "t3js-icon")]', $item)->item(0)?->getAttribute('data-identifier'),
            ];
        }
        self::assertNotSame([], $items, 'No grid layout items in ' . $html);
        return $items;
    }

    /**
     * Like typo3/index.php, so FAL URLs get the site path prefix of the backend (PublicUrlPrefixer) as in a web request
     */
    private function simulateBackendEntryPoint(array $serverParams): void
    {
        $_SERVER = array_replace($_SERVER, $serverParams);
        Environment::initialize(
            Environment::getContext(),
            false,
            Environment::isComposerMode(),
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            Environment::getConfigPath(),
            Environment::getPublicPath() . '/typo3/index.php',
            Environment::isWindows() ? 'WINDOWS' : 'UNIX'
        );
        GeneralUtility::flushInternalRuntimeCaches();
    }

    private function requestIcon(string $identifier, int $userUid = 1, string $markup = 'inline'): ResponseInterface
    {
        // the parameters sent by <typo3-backend-icon>, the drag in wizard sends 'default'
        return $this->executeBackendRequest('ajax_icons', ['icon' => json_encode([$identifier, 'small', null, 'default', $markup])], $userUid);
    }

    private function executeBackendRequest(string $route, array $parameters, int $userUid = 1): ResponseInterface
    {
        $serverParams = [
            'DOCUMENT_ROOT' => Environment::getPublicPath(),
            'HTTP_HOST' => 'typo3-testing.local',
            'SERVER_NAME' => 'typo3-testing.local',
            'HTTPS' => 'on',
            'SERVER_PORT' => '443',
            'REMOTE_ADDR' => '127.0.0.1',
            'SCRIPT_NAME' => '/typo3/index.php',
            'SCRIPT_FILENAME' => Environment::getPublicPath() . '/typo3/index.php',
            'REQUEST_METHOD' => 'GET',
        ];
        // before the session is created, which TYPO3 11 locks to the remote address
        $this->simulateBackendEntryPoint($serverParams);
        $backendUser = $this->setUpBackendUser($userUid);
        if ((new Typo3Version())->getMajorVersion() < 12) {
            // TYPO3 11 keeps the form protection, and so the route tokens, of an earlier test
            FormProtectionFactory::purgeInstances();
        }
        $session = $backendUser->getSession();
        // TYPO3 11 creates the route token with the language service
        $GLOBALS['LANG'] ??= GeneralUtility::makeInstance(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $uri = 'https://typo3-testing.local' . $this->get(UriBuilder::class)->buildUriFromRoute($route, $parameters);
        $uriParts = parse_url($uri);
        $serverParams['REQUEST_URI'] = $uriParts['path'] . '?' . $uriParts['query'];
        $serverParams['QUERY_STRING'] = $uriParts['query'];
        $_SERVER = array_replace($_SERVER, $serverParams);
        $request = (new ServerRequest($uri, 'GET', 'php://input', [], $serverParams))
            ->withQueryParams(GeneralUtility::explodeUrl2Array($uriParts['query']))
            // TYPO3 11 has no signed session cookie
            ->withCookieParams(['be_typo_user' => method_exists($session, 'getJwt') ? $session->getJwt() : $session->getIdentifier()]);
        $response = $this->get(Application::class)->handle($request);
        self::assertSame(200, $response->getStatusCode(), $response->getHeaderLine('location') . ' ' . $response->getBody());
        return $response;
    }
}
