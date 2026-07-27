<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Backend\ItemsProcFuncs;

use GridElementsTeam\Gridelements\Backend\ItemsProcFuncs\ColPosList;
use GridElementsTeam\Gridelements\Helper\GridElementsHelper;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ColPosListTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private function callAddColPosListLayoutItems(
        ColPosList $instance,
        int $pageId,
        array $items,
        string $contentType = '',
        string $listType = '',
        string $gridType = '',
        int $container = 0
    ): array {
        $method = new \ReflectionMethod(ColPosList::class, 'addColPosListLayoutItems');
        return $method->invoke($instance, $pageId, $items, $contentType, $listType, $gridType, $container);
    }

    #[Test]
    public function getBackendUserReturnsGlobalBeUser(): void
    {
        $backendUser = new BackendUserAuthentication();
        $GLOBALS['BE_USER'] = $backendUser;
        self::assertSame($backendUser, GridElementsHelper::getBackendUser());
    }

    #[Test]
    public function addColPosListLayoutItemsReturnsOriginalItemsWhenNoLayoutFound(): void
    {
        $instance = GeneralUtility::makeInstance(ColPosList::class);
        // seed the layout cache with a non-empty value that has no __items,
        // so getSelectedBackendLayout() returns from cache without hitting the DB
        $GLOBALS['tx_gridelements']['pageBackendLayoutData'][1] = ['__config' => []];
        $original = [['Content', '0', null, null]];

        $result = $this->callAddColPosListLayoutItems($instance, 1, $original, '', '', '', 0);

        self::assertSame($original, $result);
    }

    #[Test]
    public function addColPosListLayoutItemsFiltersItemsNotAllowedByCType(): void
    {
        $instance = GeneralUtility::makeInstance(ColPosList::class);
        // layout with two columns: column 0 allows 'text', column 1 allows '*'
        $GLOBALS['tx_gridelements']['pageBackendLayoutData'][2] = [
            '__config' => [],
            '__items' => [
                ['Text column', 0, null, null],
                ['Any column', 1, null, null],
            ],
            'allowed' => [
                0 => ['CType' => ['text' => 0]],
                1 => ['CType' => ['*' => 0]],
            ],
        ];

        $result = array_values(
            $this->callAddColPosListLayoutItems($instance, 2, [], 'image', '', '', 0)
        );

        // column 0 disallows 'image'; column 1 allows everything — only column 1 survives
        self::assertCount(1, $result);
        self::assertSame(1, $result[0][1]);
    }
}
