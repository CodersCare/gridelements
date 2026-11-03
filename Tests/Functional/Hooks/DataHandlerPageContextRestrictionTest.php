<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\Hooks;

use GridElementsTeam\Gridelements\Hooks\DataHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class DataHandlerPageContextRestrictionTest extends FunctionalTestCase
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/page_context_restrictions.csv');
        $this->hook = new DataHandler();
    }

    private function makeCmdmapForPasteIntoContainers(string $command, array $pastes): \TYPO3\CMS\Core\DataHandling\DataHandler
    {
        $cmdmap = [];
        foreach ($pastes as $uid => [$targetPage, $targetContainer]) {
            $cmdmap[$uid][$command] = [
                'action' => 'paste',
                'target' => $targetPage,
                'update' => [
                    'colPos' => -1,
                    'tx_gridelements_container' => $targetContainer,
                    'tx_gridelements_columns' => 0,
                ],
            ];
        }
        $dataHandler = self::createStub(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
        $dataHandler->cmdmap = ['tt_content' => $cmdmap];
        $dataHandler->bypassAccessCheckForRecords = false;

        return $dataHandler;
    }

    public static function pasteIntoContainerProvider(): array
    {
        $cases = [];
        foreach (['move', 'copy'] as $command) {
            $cases[$command . ': text from image page into text page'] = [$command, 22, 2, 10, true];
            $cases[$command . ': image from image page into text page'] = [$command, 21, 2, 10, false];
            $cases[$command . ': image from text page into image page'] = [$command, 12, 3, 20, true];
            $cases[$command . ': text from text page into image page'] = [$command, 11, 3, 20, false];
            $cases[$command . ': text within text page'] = [$command, 11, 2, 10, true];
            $cases[$command . ': image within text page'] = [$command, 12, 2, 10, false];
            $cases[$command . ': image within image page'] = [$command, 21, 3, 20, true];
            $cases[$command . ': text within image page'] = [$command, 22, 3, 20, false];
        }
        return $cases;
    }

    #[Test]
    #[DataProvider('pasteIntoContainerProvider')]
    public function pasteIntoContainerUsesTheRestrictionsOfTheTargetPage(
        string $command,
        int $uid,
        int $targetPage,
        int $targetContainer,
        bool $expectedToBeKept
    ): void {
        $dataHandler = $this->makeCmdmapForPasteIntoContainers($command, [$uid => [$targetPage, $targetContainer]]);
        $this->hook->processCmdmap_beforeStart($dataHandler);

        self::assertSame($expectedToBeKept, isset($dataHandler->cmdmap['tt_content'][$uid]));
    }

    #[Test]
    public function oneCmdmapChecksEachCommandAgainstItsOwnTargetPage(): void
    {
        $dataHandler = $this->makeCmdmapForPasteIntoContainers('move', [
            22 => [2, 10],
            21 => [2, 10],
            12 => [3, 20],
            11 => [2, 10],
        ]);
        $this->hook->processCmdmap_beforeStart($dataHandler);

        self::assertSame([22, 12, 11], array_keys($dataHandler->cmdmap['tt_content']));
    }

    public static function datamapOrderProvider(): array
    {
        return [
            'image page first' => [['NEW1' => [3, 20], 'NEW2' => [2, 10]]],
            'text page first' => [['NEW2' => [2, 10], 'NEW1' => [3, 20]]],
        ];
    }

    #[Test]
    #[DataProvider('datamapOrderProvider')]
    public function datamapChecksEachNewChildAgainstTheRestrictionsOfItsOwnPage(array $newImages): void
    {
        $datamap = [];
        foreach ($newImages as $newId => [$page, $container]) {
            $datamap[$newId] = [
                'pid' => $page,
                'CType' => 'image',
                'colPos' => -1,
                'tx_gridelements_container' => $container,
                'tx_gridelements_columns' => 0,
            ];
        }
        $dataHandler = self::createStub(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
        $dataHandler->datamap = ['tt_content' => $datamap];
        $dataHandler->bypassAccessCheckForRecords = false;
        $dataHandler->isImporting = false;

        $this->hook->processDatamap_beforeStart($dataHandler);

        self::assertSame(['NEW1'], array_keys($dataHandler->datamap['tt_content']));
    }
}
