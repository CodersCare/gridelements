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

use Psr\EventDispatcher\StoppableEventInterface;

class ModifyRecordListElementDataEvent implements StoppableEventInterface
{
    private array|null $returnData = null;

    public function __construct(
        private readonly string $table,
        private readonly array $row,
        private readonly int $level,
        private readonly array $inputData,
        private readonly \TYPO3\CMS\Backend\RecordList\DatabaseRecordList $parentObject,
    ) {
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getRow(): array
    {
        return $this->row;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getInputData(): array
    {
        return $this->inputData;
    }

    /**
     * @return \TYPO3\CMS\Backend\RecordList\DatabaseRecordList
     */
    public function getParentObject(): \TYPO3\CMS\Backend\RecordList\DatabaseRecordList
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
