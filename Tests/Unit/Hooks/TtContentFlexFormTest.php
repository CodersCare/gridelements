<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Hooks;

use GridElementsTeam\Gridelements\Hooks\TtContentFlexForm;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Covers TtContentFlexForm::parseDataStructureByIdentifierPreProcess(), the legacy hook
 * counterpart to BeforeFlexFormDataStructureParsedListener (see
 * Tests/Unit/EventListener/BeforeFlexFormDataStructureParsedListenerTest.php).
 * getDataStructureIdentifierPreProcess() needs a real TCA/page context, so it's functional-test-only.
 */
class TtContentFlexFormTest extends UnitTestCase
{
    #[Test]
    public function returnsDefaultFlexformFileReferenceForGridelementsDummyType(): void
    {
        $hook = new TtContentFlexForm();
        self::assertSame(
            'FILE:EXT:gridelements/Configuration/FlexForms/default_flexform_configuration.xml',
            $hook->parseDataStructureByIdentifierPreProcess(['type' => 'gridelements-dummy'])
        );
    }

    #[Test]
    public function returnsFlexformDsForRecordType(): void
    {
        $hook = new TtContentFlexForm();
        $ds = '<T3DataStructure><sheets/></T3DataStructure>';
        self::assertSame(
            $ds,
            $hook->parseDataStructureByIdentifierPreProcess(['type' => 'record', 'flexformDS' => $ds])
        );
    }

    #[Test]
    public function returnsEmptyStringForUnrelatedIdentifierType(): void
    {
        $hook = new TtContentFlexForm();
        self::assertSame('', $hook->parseDataStructureByIdentifierPreProcess(['type' => 'record']));
    }

    #[Test]
    public function returnsEmptyStringForEmptyIdentifier(): void
    {
        $hook = new TtContentFlexForm();
        self::assertSame('', $hook->parseDataStructureByIdentifierPreProcess([]));
    }

    #[Test]
    public function dummyTypeTakesPrecedenceOverFlexformDsKey(): void
    {
        // Unlike the TYPO3 12+ listener (where flexformDS wins), this hook checks
        // 'type' === 'gridelements-dummy' first - a real, pre-existing branch difference.
        $hook = new TtContentFlexForm();
        $customDs = 'FILE:EXT:my_ext/flexform.xml';
        self::assertSame(
            'FILE:EXT:gridelements/Configuration/FlexForms/default_flexform_configuration.xml',
            $hook->parseDataStructureByIdentifierPreProcess([
                'type' => 'gridelements-dummy',
                'flexformDS' => $customDs,
            ])
        );
    }
}
