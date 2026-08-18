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
 * Page 1 selects a DB-record backend layout (uid=1) whose own columns carry
 * allowed/disallowed/maxitems restrictions -- the "ordinary page-level colPos"
 * branch of processCmdmap_beforeStart()/processDatamap_beforeStart(), as opposed
 * to DataHandlerRestrictionTest's grid-container-child column branch. colPos 500
 * allows CType 'header' only, colPos 501 has maxitems=1 and already holds uid=11.
 * See Fixtures/backend_layout_restrictions.csv and
 * Fixtures/tt_content_page_level_restrictions.csv.
 */
class DataHandlerPageLevelRestrictionTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    private DataHandler $hook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/be_users.csv');
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(\TYPO3\CMS\Core\Localization\LanguageServiceFactory::class)
            ->createFromUserPreferences($GLOBALS['BE_USER']);
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('language', new SiteLanguage(0, 'en_US', new Uri('https://example.com/'), []));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/backend_layout_restrictions.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_page_level_restrictions.csv');
        $this->hook = new DataHandler();
    }

    #[Test]
    public function datamapRejectsNewDisallowedCTypeIntoRestrictedPageColumn(): void
    {
        $dataHandler = $this->createStub(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
        $dataHandler->datamap = [
            'tt_content' => [
                'NEW1' => [
                    'pid' => 1,
                    'CType' => 'text',
                    'colPos' => 500,
                ],
            ],
        ];
        $dataHandler->bypassAccessCheckForRecords = false;
        $dataHandler->isImporting = false;

        $this->hook->processDatamap_beforeStart($dataHandler);

        self::assertArrayNotHasKey('NEW1', $dataHandler->datamap['tt_content']);
    }

    #[Test]
    public function datamapAllowsNewAllowedCTypeIntoRestrictedPageColumn(): void
    {
        $dataHandler = $this->createStub(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
        $dataHandler->datamap = [
            'tt_content' => [
                'NEW1' => [
                    'pid' => 1,
                    'CType' => 'header',
                    'colPos' => 500,
                ],
            ],
        ];
        $dataHandler->bypassAccessCheckForRecords = false;
        $dataHandler->isImporting = false;

        $this->hook->processDatamap_beforeStart($dataHandler);

        self::assertArrayHasKey('NEW1', $dataHandler->datamap['tt_content']);
    }

    #[Test]
    public function datamapRejectsNewRecordWhenPageColumnMaxItemsAlreadyReached(): void
    {
        $dataHandler = $this->createStub(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
        $dataHandler->datamap = [
            'tt_content' => [
                'NEW1' => [
                    'pid' => 1,
                    'CType' => 'text',
                    'colPos' => 501,
                ],
            ],
        ];
        $dataHandler->bypassAccessCheckForRecords = false;
        $dataHandler->isImporting = false;

        $this->hook->processDatamap_beforeStart($dataHandler);

        self::assertArrayNotHasKey('NEW1', $dataHandler->datamap['tt_content']);
    }

    private function makeCmdmapForPasteIntoPageColumn(int $id, int $colPos): \TYPO3\CMS\Core\DataHandling\DataHandler
    {
        $dataHandler = $this->createStub(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
        $dataHandler->cmdmap = [
            'tt_content' => [
                $id => [
                    'move' => [
                        'action' => 'paste',
                        'target' => 1,
                        'update' => [
                            'colPos' => $colPos,
                        ],
                    ],
                ],
            ],
        ];
        $dataHandler->bypassAccessCheckForRecords = false;

        return $dataHandler;
    }

    #[Test]
    public function cmdmapRejectsDisallowedCTypeIntoRestrictedPageColumn(): void
    {
        // uid=13 is CType 'text', colPos 500 only allows 'header'
        $dataHandler = $this->makeCmdmapForPasteIntoPageColumn(13, 500);
        $this->hook->processCmdmap_beforeStart($dataHandler);

        self::assertArrayNotHasKey(13, $dataHandler->cmdmap['tt_content']);
    }

    #[Test]
    public function cmdmapAllowsAllowedCTypeIntoRestrictedPageColumn(): void
    {
        // uid=12 is CType 'header', colPos 500 only allows 'header'
        $dataHandler = $this->makeCmdmapForPasteIntoPageColumn(12, 500);
        $this->hook->processCmdmap_beforeStart($dataHandler);

        self::assertArrayHasKey(12, $dataHandler->cmdmap['tt_content']);
    }

    #[Test]
    public function cmdmapRejectsWhenPageColumnMaxItemsAlreadyReached(): void
    {
        // colPos 501 has maxitems=1 and already holds uid=11
        $dataHandler = $this->makeCmdmapForPasteIntoPageColumn(12, 501);
        $this->hook->processCmdmap_beforeStart($dataHandler);

        self::assertArrayNotHasKey(12, $dataHandler->cmdmap['tt_content']);
    }
}
