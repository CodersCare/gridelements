<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Compatibility;

use GridElementsTeam\Gridelements\Backend\LocalizationController;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class VersionStateTest extends UnitTestCase
{
    #[Test]
    public function stateTwoIsDeletePlaceholder(): void
    {
        self::assertTrue(LocalizationController::isDeletePlaceholder(2));
    }

    #[Test]
    public function stateZeroIsNotDeletePlaceholder(): void
    {
        self::assertFalse(LocalizationController::isDeletePlaceholder(0));
    }

    #[Test]
    public function skipVersionStateRecordInLocalizationSummary(): void
    {
        $row = ['t3ver_state' => 2];
        self::assertTrue(LocalizationController::isDeletePlaceholder((int)$row['t3ver_state']));
    }

    #[Test]
    public function keepVersionStateRecordInLocalizationSummary(): void
    {
        $row = ['t3ver_state' => 0];
        self::assertFalse(LocalizationController::isDeletePlaceholder((int)$row['t3ver_state']));
    }

    #[Test]
    public function gridelementUsesVersionStateCompatibilityCheck(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/Classes/Backend/LocalizationController.php'
        );
        self::assertStringContainsString('VersionState::tryFrom(', $source);
        self::assertStringContainsString('VersionState::cast(', $source);
        self::assertStringContainsString("method_exists(VersionState::class, 'tryFrom')", $source);
    }
}
