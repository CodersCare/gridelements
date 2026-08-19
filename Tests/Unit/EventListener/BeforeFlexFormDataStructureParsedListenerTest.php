<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\EventListener;

use GridElementsTeam\Gridelements\EventListener\BeforeFlexFormDataStructureParsedListener;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\Event\BeforeFlexFormDataStructureParsedEvent;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Covers BeforeFlexFormDataStructureParsedListener. BeforeFlexFormDataStructureParsedEvent
 * doesn't exist on TYPO3 11; the equivalent is Hooks\TtContentFlexForm, covered by
 * Tests/Unit/Hooks/TtContentFlexFormTest.php.
 */
class BeforeFlexFormDataStructureParsedListenerTest extends UnitTestCase
{
    private function skipUnlessTypo3TwelvePlus(): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            self::markTestSkipped(
                'BeforeFlexFormDataStructureParsedEvent does not exist on TYPO3 11; the equivalent '
                . 'feature is implemented by Hooks\TtContentFlexForm instead, covered by '
                . 'Tests/Unit/Hooks/TtContentFlexFormTest.php.'
            );
        }
    }

    #[Test]
    public function setsDefaultFlexformFileReferenceForGridelementsDummyType(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $event = new BeforeFlexFormDataStructureParsedEvent(['type' => 'gridelements-dummy']);
        (new BeforeFlexFormDataStructureParsedListener())($event);
        self::assertSame(
            'FILE:EXT:gridelements/Configuration/FlexForms/default_flexform_configuration.xml',
            $event->getDataStructure()
        );
    }

    #[Test]
    public function setsFlexformFromIdentifierFlexformDsKey(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $ds = '<T3DataStructure><sheets/></T3DataStructure>';
        $event = new BeforeFlexFormDataStructureParsedEvent(['type' => 'record', 'flexformDS' => $ds]);
        (new BeforeFlexFormDataStructureParsedListener())($event);
        self::assertSame($ds, $event->getDataStructure());
    }

    #[Test]
    public function doesNothingForUnrelatedIdentifierType(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $event = new BeforeFlexFormDataStructureParsedEvent(['type' => 'record']);
        (new BeforeFlexFormDataStructureParsedListener())($event);
        self::assertNull($event->getDataStructure());
    }

    #[Test]
    public function doesNothingForEmptyIdentifier(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $event = new BeforeFlexFormDataStructureParsedEvent([]);
        (new BeforeFlexFormDataStructureParsedListener())($event);
        self::assertNull($event->getDataStructure());
    }

    #[Test]
    public function flexformDsKeyTakesPrecedenceAndOverridesDefaultFlexformWhenBothMatch(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $customDs = 'FILE:EXT:my_ext/flexform.xml';
        $event = new BeforeFlexFormDataStructureParsedEvent([
            'type' => 'gridelements-dummy',
            'flexformDS' => $customDs,
        ]);
        (new BeforeFlexFormDataStructureParsedListener())($event);
        // flexformDS is checked second in the listener, so it wins
        self::assertSame($customDs, $event->getDataStructure());
    }
}
