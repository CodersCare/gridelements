<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Hooks;

use GridElementsTeam\Gridelements\Hooks\DatabaseRecordList11;
use GridElementsTeam\Gridelements\Xclass\DatabaseRecordList11 as DatabaseRecordListXclass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Covers Hooks\DatabaseRecordList11, the TYPO3 11 counterpart to Hooks\DatabaseRecordList
 * (see Tests/Unit/Hooks/DatabaseRecordListTest.php). getDBlistQuery() is this branch's
 * counterpart to the 12+ ModifyDatabaseQueryForRecordListingEvent listener.
 */
class DatabaseRecordList11Test extends UnitTestCase
{
    private function skipUnlessTypo3Eleven(): void
    {
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::markTestSkipped(
                'Hooks\DatabaseRecordList11 is only registered on TYPO3 11; TYPO3 12+ uses '
                . 'Hooks\DatabaseRecordList instead, covered by Tests/Unit/Hooks/DatabaseRecordListTest.php.'
            );
        }
    }

    private function makeHook(): DatabaseRecordList11
    {
        return (new \ReflectionClass(DatabaseRecordList11::class))->newInstanceWithoutConstructor();
    }

    #[Test]
    public function setLanguageServiceAndGetLanguageService(): void
    {
        $this->skipUnlessTypo3Eleven();

        $hook = $this->makeHook();
        $lang = $this->createMock(LanguageService::class);
        $hook->setLanguageService($lang);
        self::assertSame($lang, $hook->getLanguageService());
    }

    #[Test]
    public function legacyHookMethodsMakeClipMakeControlRenderListHeaderStillExist(): void
    {
        $this->skipUnlessTypo3Eleven();

        // Unlike the 12+ hook class, these remain real implementations on TYPO3 11, which
        // still dispatches the legacy SC_OPTIONS['typo3/class.db_list_extra.inc'] hook point.
        self::assertTrue(method_exists(DatabaseRecordList11::class, 'makeClip'));
        self::assertTrue(method_exists(DatabaseRecordList11::class, 'makeControl'));
        self::assertTrue(method_exists(DatabaseRecordList11::class, 'renderListHeader'));
        self::assertTrue(method_exists(DatabaseRecordList11::class, 'renderListHeaderActions'));
    }

    #[Test]
    public function makeClipReturnsCellsUnmodified(): void
    {
        $this->skipUnlessTypo3Eleven();

        $hook = $this->makeHook();
        $parentObject = null;
        $cells = ['copy' => 'copy-icon', 'cut' => 'cut-icon'];
        self::assertSame($cells, $hook->makeClip('tt_content', ['uid' => 1], $cells, $parentObject));
    }

    #[Test]
    public function makeControlReturnsCellsUnmodified(): void
    {
        $this->skipUnlessTypo3Eleven();

        $hook = $this->makeHook();
        $parentObject = null;
        $cells = ['edit' => 'edit-icon'];
        self::assertSame($cells, $hook->makeControl('tt_content', ['uid' => 1], $cells, $parentObject));
    }

    #[Test]
    public function renderListHeaderReturnsHeaderColumnsUnmodified(): void
    {
        $this->skipUnlessTypo3Eleven();

        $hook = $this->makeHook();
        $parentObject = null;
        $headerColumns = ['title' => 'Title'];
        self::assertSame(
            $headerColumns,
            $hook->renderListHeader('tt_content', [1, 2], $headerColumns, $parentObject)
        );
    }

    #[Test]
    public function renderListHeaderActionsReturnsCellsUnmodified(): void
    {
        $this->skipUnlessTypo3Eleven();

        $hook = $this->makeHook();
        $parentObject = null;
        $cells = ['paste' => 'paste-icon'];
        self::assertSame(
            $cells,
            $hook->renderListHeaderActions('tt_content', [1, 2], $cells, $parentObject)
        );
    }

    #[Test]
    public function getDBlistQueryAppendsColPosExclusionForTtContent(): void
    {
        $this->skipUnlessTypo3Eleven();

        $hook = $this->makeHook();
        $additionalWhereClause = '';
        $selectedFieldsList = '*';
        $parentObject = null;
        $hook->getDBlistQuery('tt_content', 1, $additionalWhereClause, $selectedFieldsList, $parentObject);
        self::assertSame(' AND colPos != -1 ', $additionalWhereClause);
    }

    #[Test]
    public function getDBlistQueryLeavesOtherTablesUnmodified(): void
    {
        $this->skipUnlessTypo3Eleven();

        $hook = $this->makeHook();
        $additionalWhereClause = '';
        $selectedFieldsList = '*';
        $parentObject = null;
        $hook->getDBlistQuery('pages', 1, $additionalWhereClause, $selectedFieldsList, $parentObject);
        self::assertSame('', $additionalWhereClause);
    }

    #[Test]
    public function contentCollapseIconDoesNotModifyIconWhenExpandTableIsNotTtContent(): void
    {
        $this->skipUnlessTypo3Eleven();

        $hook = $this->makeHook();
        $parentObj = $this->createMock(DatabaseRecordListXclass::class);
        $icon = 'original-icon';
        $data = ['_EXPAND_TABLE_' => 'pages', 'uid' => 1];
        $hook->contentCollapseIcon(
            $data,
            'title',
            0,
            $icon,
            $parentObj
        );
        self::assertSame('original-icon', $icon);
    }

    #[Test]
    public function contentCollapseIconDoesNotModifyIconWhenExpandTableKeyMissing(): void
    {
        $this->skipUnlessTypo3Eleven();

        $hook = $this->makeHook();
        $parentObj = $this->createMock(DatabaseRecordListXclass::class);
        $icon = 'original-icon';
        $data = ['uid' => 1];
        $hook->contentCollapseIcon(
            $data,
            'title',
            0,
            $icon,
            $parentObj
        );
        self::assertSame('original-icon', $icon);
    }

    #[Test]
    public function contentCollapseIconSourceContainsExpandAndCollapseStates(): void
    {
        $this->skipUnlessTypo3Eleven();

        $source = file_get_contents(
            (new \ReflectionClass(DatabaseRecordList11::class))->getFileName()
        );
        self::assertStringContainsString('data-state="expanded"', $source);
        self::assertStringContainsString('data-state="collapsed"', $source);
        self::assertStringContainsString('gridelementsExpand', $source);
    }

    #[Test]
    public function contentCollapseIconSourceUsesParentObjectExpandedGridelements(): void
    {
        $this->skipUnlessTypo3Eleven();

        $source = file_get_contents(
            (new \ReflectionClass(DatabaseRecordList11::class))->getFileName()
        );
        self::assertStringContainsString('getExpandedGridelements', $source);
        self::assertStringContainsString('listURL', $source);
    }
}
