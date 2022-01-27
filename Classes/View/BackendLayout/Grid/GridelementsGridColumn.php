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

namespace GridElementsTeam\Gridelements\View\BackendLayout\Grid;

use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumn;
use TYPO3\CMS\Backend\View\PageLayoutContext;

/**
 * Grid Column
 *
 * Object representation (model/proxy) for a single column from a grid defined
 * in a BackendLayout. Stores GridColumnItem representations of content records
 * and provides getter methods which return various properties associated with
 * a single column, e.g. the "edit all elements in content" URL and the "add
 * new content element" URL of the button that is placed in the top of columns
 * in the page layout.
 *
 * Accessed from Fluid templates.
 *
 * @internal this is experimental and subject to change in TYPO3 v10 / v11
 */
class GridelementsGridColumn extends GridColumn
{
    /**
     * @var int
     */
    protected $gridContainerId;

    /**
     * @var bool
     */
    protected $collapsed;

    public function __construct(PageLayoutContext $context, array $columnDefinition, int $gridContainerId)
    {
        parent::__construct($context, $columnDefinition);
        $this->gridContainerId = $gridContainerId;
    }

    public function getGridContainerId(): ?int
    {
        return $this->gridContainerId;
    }

    public function setCollapsed($collapsed)
    {
        $this->collapsed = (bool)$collapsed;
    }

    public function getCollapsed(): ?bool
    {
        return $this->collapsed;
    }
}
