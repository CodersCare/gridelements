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
        self::assertSame('gridelements_pi1', $wizardItems['gridelements_my_layout']['defaultValues']['CType']);
        self::assertSame('my_layout', $wizardItems['gridelements_my_layout']['defaultValues']['tx_gridelements_backend_layout']);
        // TYPO3 v12's NewContentElementController reads 'tt_content_defValues', not 'defaultValues' -
        // both must carry the same CType or dragging in a container creates the TCA-default CType instead.
        self::assertSame('gridelements_pi1', $wizardItems['gridelements_my_layout']['tt_content_defValues']['CType']);
        self::assertSame('my_layout', $wizardItems['gridelements_my_layout']['tt_content_defValues']['tx_gridelements_backend_layout']);
    }

    #[Test]
    public function txGridelementsBackendLayoutSurvivesTheFullWizardItemPipeline(): void
    {
        $gridItems = [
            'my_layout' => [
                'uid' => 'my_layout',
                'alias' => 'my_layout',
                'title' => 'My Layout',
            ],
        ];
        $wizardItems = [];
        $listener = $this->makeListener();
        $listener->addGridItemsToWizard($gridItems, $wizardItems);
        // addGridValuesToWizardItems runs after addGridItemsToWizard in the real event
        // flow (__invoke()) and must not drop tx_gridelements_backend_layout while merging
        // in the container/column context - that value is what selects the actual grid
        // layout, so losing it here reproduces the "container becomes a plain CType" bug.
        $listener->addGridValuesToWizardItems($wizardItems, 5, 2);

        foreach (['defaultValues', 'tt_content_defValues'] as $key) {
            self::assertSame('gridelements_pi1', $wizardItems['gridelements_my_layout'][$key]['CType']);
            self::assertSame('my_layout', $wizardItems['gridelements_my_layout'][$key]['tx_gridelements_backend_layout']);
            self::assertSame(5, $wizardItems['gridelements_my_layout'][$key]['tx_gridelements_container']);
            self::assertSame(2, $wizardItems['gridelements_my_layout'][$key]['tx_gridelements_columns']);
        }
    }

    // --- addGridValuesToWizardItems ---

    #[Test]
    public function addGridValuesToWizardItemsMirrorsContainerAndColumnIntoBothDefaultValueKeys(): void
    {
        $wizardItems = [
            'gridelements_my_layout' => [
                'title' => 'My Layout',
                'defaultValues' => ['CType' => 'gridelements_pi1'],
                'tt_content_defValues' => ['CType' => 'gridelements_pi1'],
            ],
        ];

        $this->makeListener()->addGridValuesToWizardItems($wizardItems, 5, 2);

        foreach (['defaultValues', 'tt_content_defValues'] as $key) {
            self::assertSame(5, $wizardItems['gridelements_my_layout'][$key]['tx_gridelements_container']);
            self::assertSame(2, $wizardItems['gridelements_my_layout'][$key]['tx_gridelements_columns']);
        }
    }

    #[Test]
    public function addGridValuesToWizardItemsAlsoUpdatesItemsThatOnlyHadTtContentDefValues(): void
    {
        // Mirrors a core-native wizard item as it looks under a real TYPO3 v12 core -
        // only 'tt_content_defValues' is present, no 'defaultValues' key at all.
        $wizardItems = [
            'textmedia' => [
                'title' => 'Text',
                'tt_content_defValues' => ['CType' => 'textmedia'],
            ],
        ];

        $this->makeListener()->addGridValuesToWizardItems($wizardItems, 5, 2);

        self::assertSame(5, $wizardItems['textmedia']['defaultValues']['tx_gridelements_container']);
        self::assertSame(5, $wizardItems['textmedia']['tt_content_defValues']['tx_gridelements_container']);
    }

    // --- removeDisallowedWizardItems ---

    #[Test]
    public function removeDisallowedWizardItemsFiltersByCTypeWhenOnlyTtContentDefValuesIsPresent(): void
    {
        $wizardItems = [
            'gridelements_my_layout' => [
                'title' => 'My Layout',
                'tt_content_defValues' => ['CType' => 'gridelements_pi1'],
            ],
            'textmedia' => [
                'title' => 'Text',
                'tt_content_defValues' => ['CType' => 'textmedia'],
            ],
        ];

        $this->makeListener()->removeDisallowedWizardItems(
            ['CType' => ['gridelements_pi1' => 1]],
            [],
            $wizardItems
        );

        self::assertArrayHasKey('gridelements_my_layout', $wizardItems);
        self::assertArrayNotHasKey('textmedia', $wizardItems);
    }
}
