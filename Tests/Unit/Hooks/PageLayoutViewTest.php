<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Hooks;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Documents the dead-code state of Hooks\PageLayoutView in CMS 12/13.
 *
 * The record_is_used hook point was removed in CMS 12 (Breaking-98375).
 * The type-hinted parent object class (PageLayoutView) was removed in CMS 13.
 * The hook class and its ext_tables registration were not cleaned up in gridelements13.
 */
class PageLayoutViewTest extends UnitTestCase
{
    #[Test]
    public function hookClassStillExistsAsDeadCode(): void
    {
        self::assertTrue(
            class_exists(\GridElementsTeam\Gridelements\Hooks\PageLayoutView::class),
            'Hook class exists even though its hook point and type-hinted parent class are removed in CMS 12/13'
        );
    }

    #[Test]
    public function hookPointWasRemovedInTypo3Version12(): void
    {
        $changelogEntry = glob(
            dirname(__DIR__, 3) . '/.Build/vendor/typo3/cms-core/Documentation/Changelog/12.0/Breaking-98375-*.rst'
        );
        self::assertNotEmpty($changelogEntry, 'Breaking change 98375 must be documented in installed vendor');
        $content = file_get_contents($changelogEntry[0]);
        self::assertStringContainsString('record_is_used', $content);
    }

    #[Test]
    public function parentObjectTypeHintClassWasRemovedInCms13(): void
    {
        self::assertFalse(
            class_exists(\TYPO3\CMS\Backend\View\PageLayoutView::class, false),
            'TYPO3\CMS\Backend\View\PageLayoutView was removed in CMS 13 — the type hint in contentIsUsed() is a dead reference'
        );
    }

    #[Test]
    public function extTablesRegistersHookAgainstDeadHookPoint(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/ext_tables.php'
        );
        self::assertStringContainsString('record_is_used', $source);
        self::assertStringContainsString('Hooks\\PageLayoutView', $source);
    }
}
