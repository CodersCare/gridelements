<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\EventListener;

use GridElementsTeam\Gridelements\EventListener\AfterBackendPageRendererEventListener;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Controller\Event\AfterBackendPageRenderEvent;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Covers AfterBackendPageRendererEventListener. AfterBackendPageRenderEvent doesn't exist on
 * TYPO3 11 (no legacy hook equivalent), so only the two tests that construct it are TYPO3 12+-only.
 */
class AfterBackendPageRendererEventListenerTest extends UnitTestCase
{
    private function skipUnlessTypo3TwelvePlus(): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            self::markTestSkipped(
                'AfterBackendPageRenderEvent does not exist on TYPO3 11 and has no hook equivalent.'
            );
        }
    }

    private function makeEvent(): AfterBackendPageRenderEvent
    {
        $view = $this->createMock(ViewInterface::class);
        return new AfterBackendPageRenderEvent('', $view);
    }

    #[Test]
    public function addsInlineLanguageLabelFileWhenPageRendererIsSet(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $pageRenderer = $this->createMock(PageRenderer::class);
        $pageRenderer->expects(self::once())
            ->method('addInlineLanguageLabelFile')
            ->with(
                'EXT:gridelements/Resources/Private/Language/locallang_db.xlf',
                'tx_gridelements_js'
            );

        (new AfterBackendPageRendererEventListener($pageRenderer))($this->makeEvent());
    }

    #[Test]
    public function doesNotCallPageRendererWhenPageRendererIsNull(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        (new AfterBackendPageRendererEventListener(null))($this->makeEvent());
        self::assertTrue(true);
    }

    #[Test]
    public function usesHardcodedLocallangDbFileAndJsPrefix(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(AfterBackendPageRendererEventListener::class))->getFileName()
        );
        self::assertStringContainsString('locallang_db.xlf', $source);
        self::assertStringContainsString('tx_gridelements_js', $source);
    }
}
