<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\EventListener;

use GridElementsTeam\Gridelements\EventListener\ModifyNewContentElementWizardItemsListener;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class ModifyNewContentElementWizardItemsListenerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    private function makeListener(): ModifyNewContentElementWizardItemsListener
    {
        return (new \ReflectionClass(ModifyNewContentElementWizardItemsListener::class))
            ->newInstanceWithoutConstructor();
    }

    // --- getExcludeLayouts ---

    #[Test]
    public function getExcludeLayoutsReturnsZeroStringWhenNoPagesConfig(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');

        // container=0, page has no TSconfig → excludeArray stays empty → returns '0'
        $result = $this->makeListener()->getExcludeLayouts(0, 1);

        self::assertSame('0', $result);
    }

    #[Test]
    public function getExcludeLayoutsWithNonZeroContainerAlsoReturnsZeroWhenNoTopLevelLayouts(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');

        // container=5 (truthy) but no topLevelLayouts TS key → excludeArray still empty → returns '0'
        $result = $this->makeListener()->getExcludeLayouts(5, 1);

        self::assertSame('0', $result);
    }

    // --- addGridItemsToWizard ---

    #[Test]
    public function addGridItemsToWizardAddsHeaderAndLayoutEntryForSingleItem(): void
    {
        $gridItems = [
            'my_layout' => [
                'uid' => 'my_layout',
                'alias' => 'my_layout',
                'title' => 'My Layout',
                'description' => 'A test layout',
                'iconIdentifier' => 'some-icon',
                'iconIdentifierLarge' => 'some-large-icon',
            ],
        ];
        $wizardItems = [];
        $this->makeListener()->addGridItemsToWizard($gridItems, $wizardItems);

        self::assertArrayHasKey('gridelements', $wizardItems);
        self::assertArrayHasKey('gridelements_my_layout', $wizardItems);
        self::assertSame('My Layout', $wizardItems['gridelements_my_layout']['title']);
        self::assertSame('gridelements_pi1', $wizardItems['gridelements_my_layout']['tt_content_defValues']['CType']);
        self::assertSame('my_layout', $wizardItems['gridelements_my_layout']['tt_content_defValues']['tx_gridelements_backend_layout']);
    }
}
