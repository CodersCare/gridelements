<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\Backend;

use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use GridElementsTeam\Gridelements\Backend\TtContent;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class TtContentTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function lookForChildContainersRecursivelyRemovesDirectAndNestedChildrenFromPossibleContainers(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_nested_containers.csv');

        $ttContent = GeneralUtility::makeInstance(TtContent::class);

        // All three containers start as candidates. We look for children of container 1.
        // Expected: 2 (direct child) and 3 (grandchild via 2) are removed; 1 stays.
        $possibleContainers = [
            1 => ['Top Container', 1],
            2 => ['Child Container', 2],
            3 => ['Grandchild Container', 3],
        ];
        $ttContent->lookForChildContainersRecursively('1', $possibleContainers);

        self::assertArrayHasKey(1, $possibleContainers);
        self::assertArrayNotHasKey(2, $possibleContainers);
        self::assertArrayNotHasKey(3, $possibleContainers);
    }

    #[Test]
    public function lookForChildContainersRecursivelyLeavesUnrelatedContainersUntouched(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_nested_containers.csv');

        $ttContent = GeneralUtility::makeInstance(TtContent::class);

        // Container 3 has no children in DB → nothing should be removed
        $possibleContainers = [
            1 => ['Top Container', 1],
            2 => ['Child Container', 2],
        ];
        $ttContent->lookForChildContainersRecursively('3', $possibleContainers);

        self::assertCount(2, $possibleContainers);
    }

    // --- containerItemsProcFunc ---

    #[Test]
    public function containerItemsProcFuncAddsSlashItemForNonGridCType(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');

        $ttContent = GeneralUtility::makeInstance(TtContent::class);
        $params = [
            'items' => [],
            'row' => ['CType' => 'text', 'uid' => 0, 'pid' => 1, 'tx_gridelements_columns' => ''],
        ];
        $ttContent->containerItemsProcFunc($params);

        // Non-grid CType → only the "/" root item is present, associative on TYPO3 12+ and
        // positional on TYPO3 11, matching containerItemsProcFunc()'s own Typo3Version branch.
        self::assertCount(1, $params['items']);
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::assertSame(0, $params['items'][0]['value']);
        } else {
            self::assertSame(0, $params['items'][0][1]);
        }
    }

    // --- deleteDisallowedContainers ---

    #[Test]
    public function deleteDisallowedContainersKeepsItemsWhenLayoutSetupHasNoRestrictions(): void
    {
        $ttContent = new TtContent();
        $layoutStub = $this->createStub(LayoutSetup::class);
        $layoutStub->method('getLayoutSetup')->willReturn([]);
        $ttContent->injectLayoutSetup($layoutStub);

        $params = [
            'items' => [['label' => '/', 'value' => 0]],
            'row' => ['CType' => 'text', 'tx_gridelements_columns' => '0', 'list_type' => ''],
        ];
        $ttContent->deleteDisallowedContainers($params, '0');

        self::assertCount(1, $params['items']);
    }
}
