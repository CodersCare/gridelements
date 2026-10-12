<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\ContextMenu;

use GridElementsTeam\Gridelements\ContextMenu\ItemProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\ContextMenu\ContextMenu;
use TYPO3\CMS\Backend\ContextMenu\ItemProviders\ItemProvidersRegistry;
use TYPO3\CMS\Backend\Controller\BackendController;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
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

    /**
     * @return array{item: array, query: array}
     */
    private function pasteAfterItem(int $anchorUid): array
    {
        $items = $this->get(ContextMenu::class)->getItems('tt_content', (string)$anchorUid);
        self::assertArrayHasKey('pasteAfter', $items);
        $actionUrl = $items['pasteAfter']['additionalAttributes']['data-action-url'] ?? '';
        parse_str((string)parse_url($actionUrl, PHP_URL_QUERY), $query);

        return ['item' => $items['pasteAfter'], 'query' => $query];
    }

    #[Test]
    public function itemProviderIsRegisteredAndInstantiatedByTheContainer(): void
    {
        $providers = $this->get(ItemProvidersRegistry::class)->getItemProviders();
        $gridelementsProviders = array_filter($providers, static fn(object $provider): bool => $provider instanceof ItemProvider);

        self::assertCount(1, $gridelementsProviders);
    }

    public static function pasteAfterDestinationDataProvider(): \Generator
    {
        $inContainerColumn5 = ['colPos' => '-1', 'sys_language_uid' => '0', 'tx_gridelements_container' => '21', 'tx_gridelements_columns' => '5'];
        $inContainerColumn0 = ['colPos' => '-1', 'sys_language_uid' => '0', 'tx_gridelements_container' => '21', 'tx_gridelements_columns' => '0'];
        $outsideContainer = ['colPos' => '2', 'sys_language_uid' => '0', 'tx_gridelements_container' => '0', 'tx_gridelements_columns' => '0'];

        yield 'cut, after anchor in container column 5' => [24, '', 22, $inContainerColumn5];
        yield 'copy, after anchor in container column 5' => [24, 'copy', 22, $inContainerColumn5];
        yield 'cut, after anchor in container column 0' => [24, '', 25, $inContainerColumn0];
        yield 'copy, after anchor in container column 0' => [24, 'copy', 25, $inContainerColumn0];
        yield 'cut out of a container, after page-level anchor' => [22, '', 23, $outsideContainer];
        yield 'copy out of a container, after page-level anchor' => [22, 'copy', 23, $outsideContainer];
    }

    #[Test]
    #[DataProvider('pasteAfterDestinationDataProvider')]
    public function pasteAfterSendsTheDestinationOfTheAnchor(int $clipboardUid, string $mode, int $anchorUid, array $expectedUpdate): void
    {
        $this->putOnClipboard($clipboardUid, $mode);
        ['item' => $item, 'query' => $query] = $this->pasteAfterItem($anchorUid);

        self::assertSame('pasteAfter', $item['callbackAction']);
        self::assertSame('@gridelementsteam/gridelements/context-menu-actions', $item['additionalAttributes']['data-callback-module']);
        self::assertSame('tt_content|-' . $anchorUid, $query['CB']['paste']);
        self::assertSame($expectedUpdate, $query['CB']['update']);
    }

    #[Test]
    public function actionUrlIsNotHtmlEncoded(): void
    {
        $this->putOnClipboard(24, '');
        $actionUrl = $this->pasteAfterItem(22)['item']['additionalAttributes']['data-action-url'];

        self::assertStringNotContainsString('&amp;', $actionUrl);
        self::assertStringContainsString('&', $actionUrl);
    }

    #[Test]
    public function callbackModuleIsResolvableByTheImportMapOfTheBackendTopFrame(): void
    {
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
