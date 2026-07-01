<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Compatibility;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Versioning\VersionState;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class VersionStateTest extends UnitTestCase
{
    #[Test]
    public function castTwoReturnsDeletePlaceholder(): void
    {
        self::assertTrue(VersionState::cast(2)->equals(VersionState::DELETE_PLACEHOLDER));
    }

    #[Test]
    public function castZeroDoesNotReturnDeletePlaceholder(): void
    {
        self::assertFalse(VersionState::cast(0)->equals(VersionState::DELETE_PLACEHOLDER));
    }

    #[Test]
    public function versionStateHasCastMethod(): void
    {
        self::assertTrue(method_exists(VersionState::class, 'cast'));
    }

    #[Test]
    public function skipVersionStateRecordInLocalizationSummary(): void
    {
        $row = ['t3ver_state' => 2];
        $shouldSkip = VersionState::cast((int)$row['t3ver_state'])->equals(VersionState::DELETE_PLACEHOLDER);
        self::assertTrue($shouldSkip);
    }

    #[Test]
    public function keepVersionStateRecordInLocalizationSummary(): void
    {
        $row = ['t3ver_state' => 0];
        $shouldSkip = VersionState::cast((int)$row['t3ver_state'])->equals(VersionState::DELETE_PLACEHOLDER);
        self::assertFalse($shouldSkip);
    }

    #[Test]
    public function gridelementUsesVersionStateCastNotFrom(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/Classes/Backend/LocalizationController.php'
        );
        self::assertStringContainsString('VersionState::cast(', $source);
        self::assertStringNotContainsString('VersionState::from(', $source);
    }
}
