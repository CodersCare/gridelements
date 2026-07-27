<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Hooks;

use GridElementsTeam\Gridelements\Hooks\DatabaseRecordList;
use GridElementsTeam\Gridelements\Xclass\DatabaseRecordList as DatabaseRecordListXclass;
use GridElementsTeam\Gridelements\Xclass\DatabaseRecordList12;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class DatabaseRecordListTest extends UnitTestCase
{
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
        $hook = $this->makeHook();
        $parentObj = $this->createMock(DatabaseRecordListXclass::class);
        $icon = 'original-icon';
        $hook->contentCollapseIcon(
            ['_EXPAND_TABLE_' => 'pages', 'uid' => 1],
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
        $hook = $this->makeHook();
        $parentObj = $this->createMock(DatabaseRecordListXclass::class);
        $icon = 'original-icon';
        $hook->contentCollapseIcon(
            ['uid' => 1],
            'title',
            0,
            $icon,
            $parentObj
        );
        self::assertSame('original-icon', $icon);
    }

    #[Test]
    public function contentCollapseIconAcceptsDatabaseRecordList12AsCms12ParentObj(): void
    {
        $hook = $this->makeHook();
        $parentObj = $this->createMock(DatabaseRecordList12::class);
        $icon = 'original-icon';
        $hook->contentCollapseIcon(
            ['_EXPAND_TABLE_' => 'pages', 'uid' => 1],
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
