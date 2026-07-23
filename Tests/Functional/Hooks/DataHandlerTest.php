<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\Hooks;

use GridElementsTeam\Gridelements\Hooks\DataHandler;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class DataHandlerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    private DataHandler $hook;

    protected function setUp(): void
    {
        parent::setUp();
        // ContainerCycleGuard uses GridElementsHelper::getQueryBuilder(), which applies
        // a WorkspaceRestriction based on $GLOBALS['BE_USER']->workspace; the recursion-guard
        // flash messages also need a real backend-user session to enqueue into
        $this->importCSVDataSet(__DIR__ . '/Fixtures/be_users.csv');
        $this->setUpBackendUser(1);
        $this->hook = new DataHandler();
    }

    private function makeCmdmapForPasteIntoContainer(int $id, int $gridContainer, string $command = 'move'): \TYPO3\CMS\Core\DataHandling\DataHandler
    {
        $dataHandler = $this->createStub(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
        $dataHandler->cmdmap = [
            'tt_content' => [
                $id => [
                    $command => [
                        'action' => 'paste',
                        'target' => 1,
                        'update' => [
                            'colPos' => -1,
                            'tx_gridelements_container' => $gridContainer,
                            'tx_gridelements_columns' => 0,
                        ],
                    ],
                ],
            ],
        ];
        $dataHandler->bypassAccessCheckForRecords = false;

        return $dataHandler;
    }

    #[Test]
    public function processCmdmapBeforeStartRejectsContainerBecomingItsOwnDescendantsContainer(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_cycle_cmdmap.csv');

        // container 2's ancestor chain is [2, 1]; moving container 1 into
        // container 2 would make container 1 its own (indirect) container
        $dataHandler = $this->makeCmdmapForPasteIntoContainer(1, 2);
        $this->hook->processCmdmap_beforeStart($dataHandler);

        self::assertArrayNotHasKey(1, $dataHandler->cmdmap['tt_content']);
    }

    #[Test]
    public function processCmdmapBeforeStartAllowsContainerCopiedIntoItsOwnDescendant(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_cycle_cmdmap.csv');

        // LayoutSetup, reached once the cycle guard lets a copy through, needs
        // a backend request to resolve the current language
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('language', new SiteLanguage(0, 'en_US', new Uri('https://example.com/'), []));

        // a copy creates a brand-new uid, which can never already be its own
        // ancestor -- unlike move, this must not be rejected
        $dataHandler = $this->makeCmdmapForPasteIntoContainer(1, 2, 'copy');
        $this->hook->processCmdmap_beforeStart($dataHandler);

        self::assertArrayHasKey(1, $dataHandler->cmdmap['tt_content']);
    }

    #[Test]
    public function processCmdmapBeforeStartRejectsShortcutMovedIntoAncestorItReferences(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_cycle_cmdmap.csv');

        // uid=3's 'records' already reference uid=1, which is in container 2's
        // ancestor chain [2, 1] -- moving the shortcut there would loop
        $dataHandler = $this->makeCmdmapForPasteIntoContainer(3, 2);
        $this->hook->processCmdmap_beforeStart($dataHandler);

        self::assertArrayNotHasKey(3, $dataHandler->cmdmap['tt_content']);
    }

    #[Test]
    public function processCmdmapBeforeStartRejectsContainerCarryingNestedDangerousShortcut(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_cycle_cmdmap.csv');

        // container 4 doesn't create a cycle by itself, but its nested child (uid=40)
        // is a shortcut referencing uid=1, which is in container 2's ancestor chain
        $dataHandler = $this->makeCmdmapForPasteIntoContainer(4, 2);
        $this->hook->processCmdmap_beforeStart($dataHandler);

        self::assertArrayNotHasKey(4, $dataHandler->cmdmap['tt_content']);
    }
}
