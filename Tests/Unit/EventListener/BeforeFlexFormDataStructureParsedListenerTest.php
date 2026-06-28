<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\EventListener;

use GridElementsTeam\Gridelements\EventListener\BeforeFlexFormDataStructureParsedListener;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\Event\BeforeFlexFormDataStructureParsedEvent;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class BeforeFlexFormDataStructureParsedListenerTest extends UnitTestCase
{
    #[Test]
    public function setsDefaultFlexformFileReferenceForGridelementsDummyType(): void
    {
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
        $ds = '<T3DataStructure><sheets/></T3DataStructure>';
        $event = new BeforeFlexFormDataStructureParsedEvent(['type' => 'record', 'flexformDS' => $ds]);
        (new BeforeFlexFormDataStructureParsedListener())($event);
        self::assertSame($ds, $event->getDataStructure());
    }

    #[Test]
    public function doesNothingForUnrelatedIdentifierType(): void
    {
        $event = new BeforeFlexFormDataStructureParsedEvent(['type' => 'record']);
        (new BeforeFlexFormDataStructureParsedListener())($event);
        self::assertNull($event->getDataStructure());
    }

    #[Test]
    public function doesNothingForEmptyIdentifier(): void
    {
        $event = new BeforeFlexFormDataStructureParsedEvent([]);
        (new BeforeFlexFormDataStructureParsedListener())($event);
        self::assertNull($event->getDataStructure());
    }

    #[Test]
    public function flexformDsKeyTakesPrecedenceAndOverridesDefaultFlexformWhenBothMatch(): void
    {
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
