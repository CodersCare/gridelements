<?php

declare(strict_types=1);
use GridElementsTeam\Gridelements\Helper\GridElementsHelper;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class HelperTest extends UnitTestCase
{
    /**
     * test get children
     *
     * @test
     */
    public function testGetChildren()
    {
    }

    /**
     * test get PID from negative UID
     *
     * @test
     */
    public function testGetPidFromNegativeUid()
    {
    }

    /**
     * test get specific ids
     *
     * @test
     */
    public function testGetSpecificIdsWithoutWorkspaceAndOriginalId()
    {
        $record = [
            'uid' => '1',
            'pid' => '2',
            't3ver_oid' => '0',
        ];
        $expected = [
            'uid' => 1,
            'pid' => 2,
        ];
        $result = GridElementsHelper::getSpecificIds($record);
        self::assertEquals($expected, $result);
    }

    /**
     * test get specific ids
     *
     * @test
     */
    public function testGetSpecificIdsWithoutWorkspaceButWithOriginalId()
    {
        $record = [
           'uid' => '1',
           'pid' => '2',
           't3ver_oid' => '3',
        ];
        $expected = [
            'uid' => 1,
            'pid' => 2,
        ];
        $result = GridElementsHelper::getSpecificIds($record);
        self::assertEquals($expected, $result);
    }

    /**
     * test get specific ids
     *
     * @test
     */
    public function testGetSpecificIdsWithWorkspaceAndWithOriginalId()
    {
        $GLOBALS['BE_USER'] = $this->getMock(BackendUserAuthentication::class);
        $GLOBALS['BE_USER']->workspace = 1;
        $record = [
            'uid' => '1',
            'pid' => '2',
            't3ver_oid' => '3',
        ];
        $expected = [
            'uid' => 3,
            'pid' => -1,
        ];
        $result = GridElementsHelper::getSpecificIds($record);
        self::assertEquals($expected, $result);
    }

    /**
     * test get specific ids
     *
     * @test
     */
    public function testGetSpecificIdsWithWorkspaceButWithoutOriginalId()
    {
        $GLOBALS['BE_USER'] = $this->getMock(BackendUserAuthentication::class);
        $GLOBALS['BE_USER']->workspace = 1;
        $record = [
            'uid' => '1',
            'pid' => '2',
            't3ver_oid' => '0',
        ];
        $expected = [
            'uid' => 1,
            'pid' => 2,
        ];
        $result = GridElementsHelper::getSpecificIds($record);
        self::assertEquals($expected, $result);
    }
}
