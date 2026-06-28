<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\Backend\ItemsProcFuncs;

use GridElementsTeam\Gridelements\Backend\ItemsProcFuncs\ColPosList;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class ColPosListTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    #[Test]
    public function itemsProcFuncContainerBranchReturnsSingleGridElementItem(): void
    {
        $colPosList = GeneralUtility::makeInstance(ColPosList::class);
        $params = [
            'items' => [['label' => 'Normal column', 'value' => 0]],
            'row' => [
                'pid' => 1,
                'CType' => 'text',
                'list_type' => '',
                'tx_gridelements_backend_layout' => '',
                'tx_gridelements_container' => 1,
            ],
        ];

        $colPosList->itemsProcFunc($params);

        self::assertCount(1, $params['items']);
        self::assertSame('-1', $params['items'][0][1]);
    }
}
