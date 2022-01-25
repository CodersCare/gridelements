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
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Grid Column Item
 *
 * Model/proxy around a single record which appears in a grid column
 * in the page layout. Returns titles, urls etc. and performs basic
 * assertions on the contained content element record such as
 * is-versioned, is-editable, is-delible and so on.
 *
 * Accessed from Fluid templates.
 *
 * @internal this is experimental and subject to change in TYPO3 v10 / v11
 */
class GridelementsGridColumnItem extends GridColumnItem
{
    /**
     * @var array
     */
    protected $layoutColumns;

    public function __construct(PageLayoutContext $context, GridColumn $column, array $record, array $layoutColumns)
    {
        parent::__construct($context, $column, $record);
        $this->layoutColumns = $layoutColumns;
    }

    public function getWrapperClassName(): string
    {
        $wrapperClassNames = [];
        if ($this->isDisabled()) {
            $wrapperClassNames[] = 't3-page-ce-hidden t3js-hidden-record';
        } elseif ($this->record['colPos'] !== -1) {
            $wrapperClassNames[] = 't3-page-ce-warning';
        } elseif (!GeneralUtility::inList($this->layoutColumns['CSV'], $this->record['tx_gridelements_columns'])) {
            $wrapperClassNames[] = 't3-page-ce-warning';
        }
        if ($this->isInconsistentLanguage()) {
            $wrapperClassNames[] = 't3-page-ce-danger';
        }

        return implode(' ', $wrapperClassNames);
    }
}
