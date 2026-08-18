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

/**
 * Container 1 (uid=1, layout 'ctg006_test') has two columns: colPos 300 allows
 * CType 'header' only, colPos 301 has maxitems=1 and already holds one child
 * (uid=2) -- see Fixtures/tx_gridelements_backend_layout_restrictions.csv and
 * Fixtures/tt_content_restrictions.csv.
 */
class DataHandlerRestrictionTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    private DataHandler $hook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/be_users.csv');
        $this->setUpBackendUser(1);
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('language', new SiteLanguage(0, 'en_US', new Uri('https://example.com/'), []));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tx_gridelements_backend_layout_restrictions.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_restrictions.csv');
        $this->hook = new DataHandler();
    }

    private function makeCmdmapForPasteIntoContainer(int $id, int $gridColumn): \TYPO3\CMS\Core\DataHandling\DataHandler
    {
        $dataHandler = $this->createStub(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
        $dataHandler->cmdmap = [
            'tt_content' => [
                $id => [
                    'move' => [
                        'action' => 'paste',
                        'target' => 1,
                        'update' => [
                            'colPos' => -1,
                            'tx_gridelements_container' => 1,
                            'tx_gridelements_columns' => $gridColumn,
                        ],
                    ],
                ],
            ],
        ];
        $dataHandler->bypassAccessCheckForRecords = false;

        return $dataHandler;
    }

    #[Test]
    public function cmdmapAllowsAllowedCTypeIntoRestrictedColumn(): void
    {
        // uid=3 is CType 'header', column 300 only allows 'header'
        $dataHandler = $this->makeCmdmapForPasteIntoContainer(3, 300);
        $this->hook->processCmdmap_beforeStart($dataHandler);

        self::assertArrayHasKey(3, $dataHandler->cmdmap['tt_content']);
    }

    #[Test]
    public function cmdmapRejectsDisallowedCTypeIntoRestrictedColumn(): void
    {
        // uid=4 is CType 'text', column 300 only allows 'header'
        $dataHandler = $this->makeCmdmapForPasteIntoContainer(4, 300);
        $this->hook->processCmdmap_beforeStart($dataHandler);

        self::assertArrayNotHasKey(4, $dataHandler->cmdmap['tt_content']);
    }

    #[Test]
    public function cmdmapRejectsWhenMaxItemsAlreadyReached(): void
    {
        // column 301 has maxitems=1 and already holds uid=2
        $dataHandler = $this->makeCmdmapForPasteIntoContainer(3, 301);
        $this->hook->processCmdmap_beforeStart($dataHandler);

        self::assertArrayNotHasKey(3, $dataHandler->cmdmap['tt_content']);
    }

    private function makeDatamapForNewChild(string $newId, int $gridColumn, string $CType): \TYPO3\CMS\Core\DataHandling\DataHandler
    {
        $dataHandler = $this->createStub(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
        $dataHandler->datamap = [
            'tt_content' => [
                $newId => [
                    'pid' => 1,
                    'CType' => $CType,
                    'tx_gridelements_container' => 1,
                    'tx_gridelements_columns' => $gridColumn,
                    'colPos' => -1,
                ],
            ],
        ];
        $dataHandler->bypassAccessCheckForRecords = false;
        $dataHandler->isImporting = false;

        return $dataHandler;
    }

    #[Test]
    public function datamapAllowsNewAllowedCTypeIntoRestrictedColumn(): void
    {
        $dataHandler = $this->makeDatamapForNewChild('NEW1', 300, 'header');
        $this->hook->processDatamap_beforeStart($dataHandler);

        self::assertArrayHasKey('NEW1', $dataHandler->datamap['tt_content']);
    }

    #[Test]
    public function datamapRejectsNewDisallowedCTypeIntoRestrictedColumn(): void
    {
        $dataHandler = $this->makeDatamapForNewChild('NEW1', 300, 'text');
        $this->hook->processDatamap_beforeStart($dataHandler);

        self::assertArrayNotHasKey('NEW1', $dataHandler->datamap['tt_content']);
    }

    #[Test]
    public function datamapRejectsNewChildWhenMaxItemsAlreadyReached(): void
    {
        $dataHandler = $this->makeDatamapForNewChild('NEW1', 301, 'text');
        $this->hook->processDatamap_beforeStart($dataHandler);

        self::assertArrayNotHasKey('NEW1', $dataHandler->datamap['tt_content']);
    }

    #[Test]
    public function datamapAllowsEditingTheExistingOccupantOfAFullColumn(): void
    {
        // uid=2 already occupies the maxitems=1 column 301 -- editing it must not
        // self-block on its own occupancy
        $dataHandler = $this->createStub(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
        $dataHandler->datamap = [
            'tt_content' => [
                2 => [
                    'header' => 'Renamed',
                ],
            ],
        ];
        $dataHandler->bypassAccessCheckForRecords = false;
        $dataHandler->isImporting = false;

        $this->hook->processDatamap_beforeStart($dataHandler);

        self::assertArrayHasKey(2, $dataHandler->datamap['tt_content']);
    }

    #[Test]
    public function datamapSkipsWhenImporting(): void
    {
        $dataHandler = $this->makeDatamapForNewChild('NEW1', 300, 'text');
        $dataHandler->isImporting = true;

        $this->hook->processDatamap_beforeStart($dataHandler);

        self::assertArrayHasKey('NEW1', $dataHandler->datamap['tt_content']);
    }
}
