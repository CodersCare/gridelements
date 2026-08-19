<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Hooks;

use GridElementsTeam\Gridelements\Hooks\DatabaseRecordList;
use GridElementsTeam\Gridelements\Xclass\DatabaseRecordList as DatabaseRecordListXclass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Covers Hooks\DatabaseRecordList. contentCollapseIcon() type-hints the TYPO3 12+-only
 * Xclass\DatabaseRecordList, so only its two tests are TYPO3 12+-only.
 * See Tests/Unit/Hooks/DatabaseRecordList11Test.php for the TYPO3 11 counterpart.
 */
class DatabaseRecordListTest extends UnitTestCase
{
    private function skipUnlessTypo3TwelvePlus(): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            self::markTestSkipped(
                'contentCollapseIcon() type-hints Xclass\DatabaseRecordList, which extends a core class '
                . 'that only exists on TYPO3 12+; TYPO3 11 uses Hooks\DatabaseRecordList11 instead, '
                . 'covered by Tests/Unit/Hooks/DatabaseRecordList11Test.php.'
            );
        }
    }

    private function makeHook(): DatabaseRecordList
    {
        return (new \ReflectionClass(DatabaseRecordList::class))->newInstanceWithoutConstructor();
    }

    #[Test]
    public function setLanguageServiceAndGetLanguageService(): void
    {
        $hook = $this->makeHook();
        $lang = $this->createMock(LanguageService::class);
        $hook->setLanguageService($lang);
        self::assertSame($lang, $hook->getLanguageService());
    }

    #[Test]
    public function deadHookMethodsMakeClipMakeControlRenderListHeaderWereRemoved(): void
    {
        // makeClip, makeControl, renderListHeader, renderListHeaderActions were pass-through
        // stubs for a hook point (SC_OPTIONS typo3/class.db_list_extra.inc) that TYPO3 core
        // no longer dispatches. Only contentCollapseIcon remains, dispatched by the Xclass.
        self::assertFalse(method_exists(DatabaseRecordList::class, 'makeClip'));
        self::assertFalse(method_exists(DatabaseRecordList::class, 'makeControl'));
        self::assertFalse(method_exists(DatabaseRecordList::class, 'renderListHeader'));
        self::assertFalse(method_exists(DatabaseRecordList::class, 'renderListHeaderActions'));
    }

    #[Test]
    public function contentCollapseIconDoesNotModifyIconWhenExpandTableIsNotTtContent(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

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
        $this->skipUnlessTypo3TwelvePlus();

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
        $source = file_get_contents(
            (new \ReflectionClass(DatabaseRecordList::class))->getFileName()
        );
        self::assertStringContainsString('data-state="expanded"', $source);
        self::assertStringContainsString('data-state="collapsed"', $source);
        self::assertStringContainsString('gridelementsExpand', $source);
    }

    #[Test]
    public function contentCollapseIconSourceUsesParentObjectExpandedGridelements(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(DatabaseRecordList::class))->getFileName()
        );
        self::assertStringContainsString('getExpandedGridelements', $source);
        self::assertStringContainsString('listURL', $source);
    }
}
