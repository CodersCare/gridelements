<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace GridElementsTeam\Gridelements\Event;

use GridElementsTeam\Gridelements\Xclass\DatabaseRecordList as DatabaseRecordListXclass;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Listeners to this event are able to specify a flex form data structure that
 * corresponds to a given identifier.
 *
 * Listeners should call ->setDataStructure() to set the data structure (this
 * can either be a resolved data structure string, a "FILE:" reference or a
 * fully parsed data structure as array) or ignore the event to allow other
 * listeners to set it. Do not set an empty array or string as this will
 * immediately stop event propagation!
 *
 * See the note on FlexFormTools regarding the schema of $dataStructure.
 */
class ModifyRecordListElementDataEvent implements StoppableEventInterface
{
    private array|null $returnData = null;

    public function __construct(
        private readonly string $table,
        private readonly array $row,
        private readonly int $level,
        private readonly array $inputData,
        private readonly DatabaseRecordListXclass $parentObject,
    ) {
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @return array
     */
    public function getRow(): array
    {
        return $this->row;
    }

    /**
     * @return int
     */
    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * @return array
     */
    public function getInputData(): array
    {
        return $this->inputData;
    }

    /**
     * @return DatabaseRecordListXclass
     */
    public function getParentObject(): DatabaseRecordListXclass
    {
        return $this->parentObject;
    }

    /**
     * Returns the current data structure, which will always be `null`
     * for listeners, since the event propagation is stopped as soon as
     * a listener sets a data structure.
     */
    public function getReturnData(): array|null
    {
        return $this->returnData ?? null;
    }

    /**
     * Allows to either set an already parsed data structure as `array`,
     * a file reference or the XML structure as `string`. Setting a data
     * structure will immediately stop propagation. Avoid setting this parameter
     * to an empty array or string as this will also stop propagation.
     */
    public function setReturnData(array $returnData): void
    {
        $this->returnData = $returnData;
    }

    public function isPropagationStopped(): bool
    {
        return isset($this->returnData);
    }
}
