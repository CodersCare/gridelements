<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Helper;

use GridElementsTeam\Gridelements\Helper\GridElementsHelper;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class HelperTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // getSpecificIds() checks the current backend user's workspace, unlike gridelements13's
        // version of this method, which dropped the workspace-overlay handling entirely.
        $beUser = $this->createMock(BackendUserAuthentication::class);
        $beUser->workspace = 0;
        $GLOBALS['BE_USER'] = $beUser;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
        parent::tearDown();
    }

    // --- getSpecificIds ---

    #[Test]
    public function getSpecificIdsReturnsCastUidAndPid(): void
    {
        $result = GridElementsHelper::getSpecificIds(['uid' => '1', 'pid' => '2', 't3ver_oid' => '0']);
        self::assertSame(['uid' => 1, 'pid' => 2], $result);
    }

    #[Test]
    public function getSpecificIdsIgnoresTverOidWhenNotInWorkspace(): void
    {
        $result = GridElementsHelper::getSpecificIds(['uid' => '1', 'pid' => '2', 't3ver_oid' => '3']);
        self::assertSame(['uid' => 1, 'pid' => 2], $result);
    }

    #[Test]
    public function getSpecificIdsUsesTverOidWhenInWorkspace(): void
    {
        $GLOBALS['BE_USER']->workspace = 5;
        $result = GridElementsHelper::getSpecificIds(['uid' => '1', 'pid' => '2', 't3ver_oid' => '3']);
        self::assertSame(['uid' => 3, 'pid' => -1], $result);
    }

    #[Test]
    public function getSpecificIdsReturnsIntegersForStringInput(): void
    {
        $result = GridElementsHelper::getSpecificIds(['uid' => '42', 'pid' => '7', 't3ver_oid' => '0']);
        self::assertIsInt($result['uid']);
        self::assertIsInt($result['pid']);
    }

    // --- mergeAllowedDisallowedSettings ---

    #[Test]
    public function mergeAllowedDisallowedSettingsReturnUnchangedWhenNoAllowedKey(): void
    {
        $layout = ['title' => 'My Layout'];
        self::assertSame($layout, GridElementsHelper::mergeAllowedDisallowedSettings($layout));
    }

    #[Test]
    public function mergeAllowedDisallowedSettingsSetsCTypeWildcardWhenMissing(): void
    {
        $layout = ['allowed' => [0 => ['list_type' => '']]];
        $result = GridElementsHelper::mergeAllowedDisallowedSettings($layout, true);
        self::assertSame('*', $result['allowed'][0]['CType']);
    }

    #[Test]
    public function mergeAllowedDisallowedSettingsAddsListToCTypeWhenListTypePresent(): void
    {
        $layout = ['allowed' => [0 => ['CType' => 'text,image', 'list_type' => 'myPlugin']]];
        $result = GridElementsHelper::mergeAllowedDisallowedSettings($layout, true);
        self::assertStringContainsString('list', $result['allowed'][0]['CType']);
    }

    #[Test]
    public function mergeAllowedDisallowedSettingsDoesNotDuplicateListInCType(): void
    {
        $layout = ['allowed' => [0 => ['CType' => 'text,list', 'list_type' => 'myPlugin']]];
        $result = GridElementsHelper::mergeAllowedDisallowedSettings($layout, true);
        self::assertSame(1, substr_count($result['allowed'][0]['CType'], 'list'));
    }

    #[Test]
    public function mergeAllowedDisallowedSettingsAddsGridelementsPi1WhenGridTypePresent(): void
    {
        $layout = ['allowed' => [0 => ['CType' => 'text', 'tx_gridelements_backend_layout' => '1']]];
        $result = GridElementsHelper::mergeAllowedDisallowedSettings($layout, true);
        self::assertStringContainsString('gridelements_pi1', $result['allowed'][0]['CType']);
    }

    #[Test]
    public function mergeAllowedDisallowedSettingsConvertsCTypeToArrayByDefault(): void
    {
        $layout = ['allowed' => [0 => ['CType' => 'text,image']]];
        $result = GridElementsHelper::mergeAllowedDisallowedSettings($layout);
        self::assertIsArray($result['allowed'][0]['CType']);
        self::assertArrayHasKey('text', $result['allowed'][0]['CType']);
        self::assertArrayHasKey('image', $result['allowed'][0]['CType']);
    }

    #[Test]
    public function mergeAllowedDisallowedSettingsKeepsCTypeAsStringWithCsvValues(): void
    {
        $layout = ['allowed' => [0 => ['CType' => 'text,image']]];
        $result = GridElementsHelper::mergeAllowedDisallowedSettings($layout, true);
        self::assertIsString($result['allowed'][0]['CType']);
    }

    #[Test]
    public function mergeAllowedDisallowedSettingsConvertsDisallowedCTypeToArray(): void
    {
        $layout = ['disallowed' => [0 => ['CType' => 'gridelements_pi1,list']]];
        $result = GridElementsHelper::mergeAllowedDisallowedSettings($layout);
        self::assertIsArray($result['disallowed'][0]['CType']);
        self::assertArrayHasKey('gridelements_pi1', $result['disallowed'][0]['CType']);
    }

    #[Test]
    public function mergeAllowedDisallowedSettingsLeavesWildcardCTypeUntouched(): void
    {
        $layout = ['allowed' => [0 => ['CType' => '*']]];
        $result = GridElementsHelper::mergeAllowedDisallowedSettings($layout, true);
        self::assertSame('*', $result['allowed'][0]['CType']);
    }
}
