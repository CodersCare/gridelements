<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\ContextMenu;

use GridElementsTeam\Gridelements\ContextMenu\ItemProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\ContextMenu\ContextMenu;
use TYPO3\CMS\Backend\ContextMenu\ItemProviders\ItemProvidersRegistry;
use TYPO3\CMS\Backend\Controller\BackendController;
use TYPO3\CMS\Backend\Controller\SimpleDataHandlerController;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class ItemProviderTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/paste_after_destination.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $request = (new ServerRequest('https://example.com/typo3/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $GLOBALS['TYPO3_REQUEST'] = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));
    }

    private function putOnClipboard(int $uid, string $mode): void
    {
        $GLOBALS['BE_USER']->pushModuleData('clipboard', [
            'current' => 'normal',
            'normal' => ['el' => ['tt_content|' . $uid => '1'], 'mode' => $mode],
        ]);
    }

    private static function isTypo3Twelve(): bool
    {
        return (new Typo3Version())->getMajorVersion() >= 12;
    }

    /**
     * @return array{item: array, query: array}
     */
    private function pasteAfterItem(int $anchorUid, string $itemName = 'pasteAfter'): array
    {
        $contextMenu = self::isTypo3Twelve() ? $this->get(ContextMenu::class) : GeneralUtility::makeInstance(ContextMenu::class);
        $items = $contextMenu->getItems('tt_content', (string)$anchorUid);
        self::assertArrayHasKey($itemName, $items);
        $actionUrl = $items[$itemName]['additionalAttributes']['data-action-url'] ?? '';
        if (!self::isTypo3Twelve()) {
            // TYPO3 11 renders the attributes into an HTML string, which the browser decodes
            $actionUrl = htmlspecialchars_decode($actionUrl);
        }
        parse_str((string)parse_url($actionUrl, PHP_URL_QUERY), $query);

        return ['item' => $items[$itemName], 'query' => $query];
    }

    private static function expectedCallbackModule(): string
    {
        return self::isTypo3Twelve()
            ? '@gridelementsteam/gridelements/context-menu-actions'
            : 'TYPO3/CMS/Gridelements/ContextMenuActions';
    }

    private function fetchContentRow(string $field, int $value): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder->select('*')
            ->from('tt_content')
            ->where($queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter($value, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative() ?: [];
    }

    #[Test]
    public function itemProviderIsRegisteredOnlyOnceAndOnlyByTheContainer(): void
    {
        if (!self::isTypo3Twelve()) {
            self::markTestSkipped('TYPO3 11 has no ItemProvidersRegistry.');
        }
        $providers = $this->get(ItemProvidersRegistry::class)->getItemProviders();
        $gridelementsProviders = array_filter($providers, static fn(object $provider): bool => $provider instanceof ItemProvider);

        self::assertCount(1, $gridelementsProviders);
        self::assertArrayNotHasKey(1487270751, $GLOBALS['TYPO3_CONF_VARS']['BE']['ContextMenu']['ItemProviders'] ?? []);
    }

    #[Test]
    public function itemProviderIsRegisteredOnlyOnceInTheLegacyProviderListBelowTypo3Twelve(): void
    {
        if (self::isTypo3Twelve()) {
            self::markTestSkipped('TYPO3 12 registers the provider by its DI tag.');
        }
        $providers = $GLOBALS['TYPO3_CONF_VARS']['BE']['ContextMenu']['ItemProviders'] ?? [];

        self::assertSame([1487270751], array_keys($providers, ItemProvider::class, true));
    }

    public static function pasteAfterDestinationDataProvider(): \Generator
    {
        $inContainerColumn5 = ['colPos' => '-1', 'sys_language_uid' => '0', 'tx_gridelements_container' => '21', 'tx_gridelements_columns' => '5'];
        $inContainerColumn0 = ['colPos' => '-1', 'sys_language_uid' => '0', 'tx_gridelements_container' => '21', 'tx_gridelements_columns' => '0'];
        $outsideContainer = ['colPos' => '2', 'sys_language_uid' => '0', 'tx_gridelements_container' => '0', 'tx_gridelements_columns' => '0'];
        $outsideContainerLanguage1 = ['colPos' => '0', 'sys_language_uid' => '1', 'tx_gridelements_container' => '0', 'tx_gridelements_columns' => '0'];

        yield 'cut, after anchor in container column 5' => [24, '', 22, $inContainerColumn5];
        yield 'copy, after anchor in container column 5' => [24, 'copy', 22, $inContainerColumn5];
        yield 'cut, after anchor in container column 0' => [24, '', 25, $inContainerColumn0];
        yield 'copy, after anchor in container column 0' => [24, 'copy', 25, $inContainerColumn0];
        yield 'cut out of a container, after page-level anchor' => [22, '', 23, $outsideContainer];
        yield 'copy out of a container, after page-level anchor' => [22, 'copy', 23, $outsideContainer];
        yield 'cut, after page-level anchor in language 1' => [24, '', 26, $outsideContainerLanguage1];
        yield 'copy, after page-level anchor in language 1' => [24, 'copy', 26, $outsideContainerLanguage1];
    }

    #[Test]
    #[DataProvider('pasteAfterDestinationDataProvider')]
    public function pasteAfterSendsTheDestinationOfTheAnchor(int $clipboardUid, string $mode, int $anchorUid, array $expectedUpdate): void
    {
        $this->putOnClipboard($clipboardUid, $mode);
        ['item' => $item, 'query' => $query] = $this->pasteAfterItem($anchorUid);

        self::assertSame('pasteAfter', $item['callbackAction']);
        self::assertSame(self::expectedCallbackModule(), $item['additionalAttributes']['data-callback-module']);
        self::assertSame('tt_content|-' . $anchorUid, $query['CB']['paste']);
        self::assertSame($expectedUpdate, $query['CB']['update']);
    }

    #[Test]
    public function pasteReferenceSharesTheCallbackModuleAndTheDestinationOfPasteAfter(): void
    {
        $this->putOnClipboard(24, 'copy');
        ['query' => $pasteAfterQuery] = $this->pasteAfterItem(22);
        ['item' => $item, 'query' => $query] = $this->pasteAfterItem(22, 'pastereference');

        self::assertSame('pasteReference', $item['callbackAction']);
        self::assertSame(self::expectedCallbackModule(), $item['additionalAttributes']['data-callback-module']);
        self::assertSame('1', $query['reference']);
        self::assertSame('tt_content|-22', $query['CB']['paste']);
        self::assertSame($pasteAfterQuery['CB']['update'], $query['CB']['update']);
    }

    #[Test]
    #[DataProvider('pasteAfterDestinationDataProvider')]
    public function pasteAfterActionUrlPlacesTheRecordAtTheDestinationOfTheAnchor(int $clipboardUid, string $mode, int $anchorUid, array $expectedUpdate): void
    {
        $this->putOnClipboard($clipboardUid, $mode);
        ['query' => $query] = $this->pasteAfterItem($anchorUid);

        $request = (new ServerRequest('https://example.com/typo3/record/commit'))
            ->withQueryParams($query)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $GLOBALS['TYPO3_REQUEST'] = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));
        GeneralUtility::makeInstance(SimpleDataHandlerController::class)->mainAction($GLOBALS['TYPO3_REQUEST']);

        $pastedUid = $mode === 'copy'
            ? (int)($this->fetchContentRow('t3_origuid', $clipboardUid)['uid'] ?? 0)
            : $clipboardUid;
        $pasted = $this->fetchContentRow('uid', $pastedUid);
        $anchor = $this->fetchContentRow('uid', $anchorUid);

        self::assertGreaterThan(0, $pastedUid);
        self::assertSame((int)$anchor['pid'], (int)$pasted['pid']);
        self::assertGreaterThan((int)$anchor['sorting'], (int)$pasted['sorting']);
        self::assertSame(
            array_map('intval', $expectedUpdate),
            [
                'colPos' => (int)$pasted['colPos'],
                'sys_language_uid' => (int)$pasted['sys_language_uid'],
                'tx_gridelements_container' => (int)$pasted['tx_gridelements_container'],
                'tx_gridelements_columns' => (int)$pasted['tx_gridelements_columns'],
            ]
        );
    }

    #[Test]
    public function actionUrlIsNotHtmlEncoded(): void
    {
        if (!self::isTypo3Twelve()) {
            self::markTestSkipped('TYPO3 11 renders the menu as HTML string.');
        }
        $this->putOnClipboard(24, '');
        $actionUrl = $this->pasteAfterItem(22)['item']['additionalAttributes']['data-action-url'];

        self::assertStringNotContainsString('&amp;', $actionUrl);
        self::assertStringContainsString('&', $actionUrl);
    }

    #[Test]
    public function actionUrlIsHtmlEncodedBelowTypo3Twelve(): void
    {
        if (self::isTypo3Twelve()) {
            self::markTestSkipped('TYPO3 12 sets the attributes via DOM.');
        }
        $this->putOnClipboard(24, '');
        $contextMenu = GeneralUtility::makeInstance(ContextMenu::class);
        $actionUrl = $contextMenu->getItems('tt_content', '22')['pasteAfter']['additionalAttributes']['data-action-url'];

        self::assertStringContainsString('&amp;', $actionUrl);
        self::assertSame($actionUrl, htmlspecialchars(htmlspecialchars_decode($actionUrl)));
    }

    #[Test]
    public function callbackModuleIsResolvableByTheRequireJsConfigBelowTypo3Twelve(): void
    {
        if (self::isTypo3Twelve()) {
            self::markTestSkipped('TYPO3 12 resolves the callback module by the import map.');
        }
        $this->putOnClipboard(24, '');
        $moduleName = $this->pasteAfterItem(22)['item']['additionalAttributes']['data-callback-module'];

        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->loadRequireJs();
        $paths = $pageRenderer->getRequireJsConfig(PageRenderer::REQUIREJS_SCOPE_RESOLVE)['paths'] ?? [];
        $prefixes = array_filter(
            array_keys($paths),
            static fn(string $prefix): bool => str_starts_with($moduleName, $prefix . '/')
        );
        usort($prefixes, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
        self::assertNotEmpty($prefixes, $moduleName . ' is not resolvable by the RequireJS paths');

        $path = (string)parse_url($paths[$prefixes[0]] . substr($moduleName, strlen($prefixes[0])) . '.js', PHP_URL_PATH);
        $sitePath = (string)GeneralUtility::getIndpEnv('TYPO3_SITE_PATH');
        if (str_starts_with($path, $sitePath)) {
            $path = substr($path, strlen($sitePath));
        }
        $file = realpath(Environment::getPublicPath() . '/' . ltrim($path, '/'));
        self::assertNotFalse($file);
        self::assertStringEndsWith('/Resources/Public/JavaScript/ContextMenuActions.js', $file);
        self::assertGreaterThan(0, filesize($file));
    }

    #[Test]
    public function callbackModuleIsResolvableByTheImportMapOfTheBackendTopFrame(): void
    {
        if (!self::isTypo3Twelve()) {
            self::markTestSkipped('TYPO3 11 has no import map.');
        }
        $this->putOnClipboard(24, '');
        // context-menu.js imports data-callback-module with an appended '.js'
        $specifier = $this->pasteAfterItem(22)['item']['additionalAttributes']['data-callback-module'] . '.js';

        $request = $GLOBALS['TYPO3_REQUEST']->withAttribute('route', $this->get(Router::class)->getRoute('main'));
        $html = (string)$this->get(BackendController::class)->mainAction($request)->getBody();
        self::assertSame(1, preg_match('#<script type="importmap"[^>]*>(.*?)</script>#s', $html, $match));
        $imports = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR)['imports'];

        $address = $imports[$specifier] ?? null;
        if ($address === null) {
            $prefixes = array_filter(
                array_keys($imports),
                static fn(string $key): bool => str_ends_with($key, '/') && str_starts_with($specifier, $key)
            );
            usort($prefixes, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
            $address = isset($prefixes[0]) ? $imports[$prefixes[0]] . substr($specifier, strlen($prefixes[0])) : null;
        }
        self::assertNotNull($address, $specifier . ' is not resolvable by the backend import map');

        $path = (string)parse_url($address, PHP_URL_PATH);
        $sitePath = (string)GeneralUtility::getIndpEnv('TYPO3_SITE_PATH');
        if (str_starts_with($path, $sitePath)) {
            $path = substr($path, strlen($sitePath));
        }
        $file = Environment::getPublicPath() . '/' . ltrim($path, '/');
        self::assertFileExists($file);
        self::assertStringEndsWith('/Resources/Public/JavaScript/context-menu-actions.js', realpath($file));
    }
}
