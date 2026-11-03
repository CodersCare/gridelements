<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\Backend;

use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class LayoutSetupPageContextTest extends FunctionalTestCase
{
    private const ALLOWED_PER_PAGE = [2 => ['text'], 3 => ['image']];
    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
        $this->importCSVDataSet(__DIR__ . '/../Hooks/Fixtures/page_context_restrictions.csv');
    }

    private function allowedCTypes(LayoutSetup $layoutSetup): array
    {
        return array_keys($layoutSetup->getLayoutSetup('pageContext')['allowed'][0]['CType'] ?? []);
    }

    public static function pageOrderProvider(): array
    {
        return [
            'text page first' => [[2, 3]],
            'image page first' => [[3, 2]],
        ];
    }

    #[Test]
    #[DataProvider('pageOrderProvider')]
    public function eachInitUsesTheColumnRestrictionsOfItsOwnPage(array $pageOrder): void
    {
        foreach ($pageOrder as $pageId) {
            $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class)->init($pageId);
            self::assertSame(self::ALLOWED_PER_PAGE[$pageId], $this->allowedCTypes($layoutSetup), 'page ' . $pageId);
            self::assertSame(
                self::ALLOWED_PER_PAGE[$pageId],
                array_keys($layoutSetup->getLayoutColumns('pageContext')['allowed'][0]['CType'] ?? []),
                'page ' . $pageId
            );
        }
    }

    #[Test]
    public function reinitialisingOneInstanceSwitchesTheRestrictionsBackAndForth(): void
    {
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class);
        foreach ([2, 3, 2, 3] as $step => $pageId) {
            self::assertSame(self::ALLOWED_PER_PAGE[$pageId], $this->allowedCTypes($layoutSetup->init($pageId)), 'step ' . $step);
        }
    }

    #[Test]
    public function aLayoutWithoutColumnsDoesNotReturnTheColumnsOfAnotherPage(): void
    {
        GeneralUtility::makeInstance(LayoutSetup::class)->init(3);

        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class);
        $layoutSetup->setLayoutSetup(['pageContext' => ['title' => 'Without columns']]);

        self::assertSame([], $layoutSetup->getLayoutColumns('pageContext'));
    }
}
