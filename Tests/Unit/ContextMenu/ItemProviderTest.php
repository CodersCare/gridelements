<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\ContextMenu;

use GridElementsTeam\Gridelements\ContextMenu\ItemProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Clipboard\Clipboard;
use TYPO3\CMS\Backend\ContextMenu\ItemProviders\AbstractProvider;
use TYPO3\CMS\Backend\ContextMenu\ItemProviders\RecordProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ItemProviderTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    /**
     * @param bool $canBePastedAfter what the (untested, TYPO3-core) canBePastedAfter() should report
     * @param string $clipboardMode what Clipboard::currentMode() should report
     * @param bool $authMode what BackendUserAuthentication::checkAuthMode() should report
     */
    private function makeProvider(bool $canBePastedAfter, string $clipboardMode, bool $authMode): ItemProvider
    {
        $provider = new class ($canBePastedAfter) extends ItemProvider {
            public function __construct(private readonly bool $canBePastedAfterResult)
            {
            }

            protected function canBePastedAfter(): bool
            {
                return $this->canBePastedAfterResult;
            }
        };

        $clipboard = $this->createStub(Clipboard::class);
        $clipboard->method('currentMode')->willReturn($clipboardMode);
        $backendUser = $this->createStub(BackendUserAuthentication::class);
        $backendUser->method('checkAuthMode')->willReturn($authMode);

        $abstractProvider = new \ReflectionClass(AbstractProvider::class);
        $abstractProvider->getProperty('clipboard')->setValue($provider, $clipboard);
        $abstractProvider->getProperty('backendUser')->setValue($provider, $backendUser);

        return $provider;
    }

    private function callCanRender(ItemProvider $provider, string $itemName, string $type = 'item'): bool
    {
        return (new \ReflectionClass(ItemProvider::class))
            ->getMethod('canRender')
            ->invoke($provider, $itemName, $type);
    }

    #[Test]
    public function canRenderIsTrueForPasteReferenceWhenAllConditionsMatch(): void
    {
        $provider = $this->makeProvider(true, 'copy', true);
        self::assertTrue($this->callCanRender($provider, 'pastereference'));
    }

    #[Test]
    public function canRenderIsFalseForItemNamesOtherThanPasteReference(): void
    {
        $provider = $this->makeProvider(true, 'copy', true);
        self::assertFalse($this->callCanRender($provider, 'paste'));
    }

    #[Test]
    public function canRenderIsFalseWhenRecordCannotBePastedAfter(): void
    {
        $provider = $this->makeProvider(false, 'copy', true);
        self::assertFalse($this->callCanRender($provider, 'pastereference'));
    }

    #[Test]
    public function canRenderIsFalseWhenClipboardIsNotInCopyMode(): void
    {
        $provider = $this->makeProvider(true, 'cut', true);
        self::assertFalse($this->callCanRender($provider, 'pastereference'));
    }

    #[Test]
    public function canRenderIsFalseWhenBackendUserLacksShortcutAuthMode(): void
    {
        $provider = $this->makeProvider(true, 'copy', false);
        self::assertFalse($this->callCanRender($provider, 'pastereference'));
    }

    #[Test]
    public function canHandleIsTrueOnlyForTtContent(): void
    {
        $provider = (new \ReflectionClass(ItemProvider::class))->newInstanceWithoutConstructor();
        $tableProperty = (new \ReflectionClass(AbstractProvider::class))->getProperty('table');

        $tableProperty->setValue($provider, 'tt_content');
        self::assertTrue($provider->canHandle());

        $tableProperty->setValue($provider, 'pages');
        self::assertFalse($provider->canHandle());
    }

    #[Test]
    public function getPriorityReturnsFortyFive(): void
    {
        $provider = (new \ReflectionClass(ItemProvider::class))->newInstanceWithoutConstructor();
        self::assertSame(45, $provider->getPriority());
    }

    /**
     * @return array{attributes: array, parameters: array}
     */
    private function getAttributesForAnchor(array $anchor, string $itemName = 'pasteAfter'): array
    {
        $parameters = [];
        $uriBuilder = self::createStub(UriBuilder::class);
        $uriBuilder->method('buildUriFromRoute')->willReturnCallback(
            static function (string $route, array $urlParameters) use (&$parameters): Uri {
                $parameters = $urlParameters;
                return new Uri('/typo3/record/commit?token=abc&CB%5Bpad%5D=normal');
            }
        );
        GeneralUtility::setSingletonInstance(UriBuilder::class, $uriBuilder);

        $provider = $this->makeProvider(true, 'cut', true);
        $abstractProvider = new \ReflectionClass(AbstractProvider::class);
        $abstractProvider->getProperty('languageService')->setValue($provider, self::createStub(LanguageService::class));
        $abstractProvider->getProperty('table')->setValue($provider, 'tt_content');
        (new \ReflectionClass(RecordProvider::class))->getProperty('record')->setValue($provider, $anchor);
        $attributes = (new \ReflectionClass(ItemProvider::class))
            ->getMethod('getAdditionalAttributes')
            ->invoke($provider, $itemName);

        return ['attributes' => $attributes, 'parameters' => $parameters];
    }

    #[Test]
    public function pasteAfterAnchorInContainerSendsContainerColumnAndColPosMinusOne(): void
    {
        ['parameters' => $parameters] = $this->getAttributesForAnchor([
            'uid' => 22,
            'CType' => 'text',
            'colPos' => 0,
            'sys_language_uid' => 1,
            'tx_gridelements_container' => 21,
            'tx_gridelements_columns' => 5,
            'tx_gridelements_backend_layout' => '',
        ]);

        self::assertSame('tt_content|-22', $parameters['CB[paste]']);
        self::assertSame(
            ['colPos' => -1, 'sys_language_uid' => 1, 'tx_gridelements_container' => 21, 'tx_gridelements_columns' => 5],
            $parameters['CB[update]']
        );
    }

    #[Test]
    public function pasteReferenceUsesTheSameDestination(): void
    {
        ['parameters' => $parameters] = $this->getAttributesForAnchor([
            'uid' => 22,
            'colPos' => -1,
            'sys_language_uid' => 0,
            'tx_gridelements_container' => 21,
            'tx_gridelements_columns' => 0,
        ], 'pastereference');

        self::assertSame(1, $parameters['reference']);
        self::assertSame(
            ['colPos' => -1, 'sys_language_uid' => 0, 'tx_gridelements_container' => 21, 'tx_gridelements_columns' => 0],
            $parameters['CB[update]']
        );
    }
}
