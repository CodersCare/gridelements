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
    protected $collapsed = false;

    /**
     * @var string
     */
    protected $allowedContentType = '';

    /**
     * @var string
     */
    protected $allowedListType = '';

    /**
     * @var string
     */
    protected $allowedGridType = '';

    /**
     * @var string
     */
    protected $disallowedContentType = '';

    /**
     * @var string
     */
    protected $disallowedListType = '';

    /**
     * @var string
     */
    protected $disallowedGridType = '';

    /**
     * @var int
     */
    protected $maxitems = 0;

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

    public function setDisallowedContentType(string $disallowedContentType)
    {
        $this->disallowedContentType = $disallowedContentType;
    }

    public function getDisallowedContentType(): ?string
    {
        return $this->disallowedContentType;
    }

    public function setDisallowedListType(string $disallowedListType)
    {
        $this->disallowedListType = $disallowedListType;
    }

    public function getDisallowedListType(): ?string
    {
        return $this->disallowedListType;
    }

    public function setDisallowedGridType(string $disallowedGridType)
    {
        $this->disallowedGridType = $disallowedGridType;
    }

    public function getDisallowedGridType(): ?string
    {
        return $this->disallowedGridType;
    }

    public function setAllowedContentType(string $allowedContentType)
    {
        $this->allowedContentType = $allowedContentType;
    }

    public function getAllowedContentType(): ?string
    {
        return $this->allowedContentType;
    }

    public function setAllowedListType(string $allowedListType)
    {
        $this->allowedListType = $allowedListType;
    }

    public function getAllowedListType(): ?string
    {
        return $this->allowedListType;
    }

    public function setAllowedGridType(string $allowedGridType)
    {
        $this->allowedGridType = $allowedGridType;
    }

    public function getAllowedGridType(): ?string
    {
        return $this->disallowedGridType;
    }

    public function setMaxitems(int $maxitems)
    {
        $this->maxitems = $maxitems;
    }

    public function getMaxitems(): ?int
    {
        return $this->maxitems;
    }

    public function setRestrictions(array $layoutColumns)
    {
        if (empty($layoutColumns)) {
            return;
        }

        $disallowed = [];
        $allowed = [];
        $disallowedContentTypes = [];
        $disallowedListTypes = [];
        $disallowedGridTypes = [];
        $allowedContentTypes = [];
        $allowedListTypes = [];
        $allowedGridTypes = [];

        // first get disallowed CTypes
        if (isset($layoutColumns['disallowed']) && isset($layoutColumns['disallowed'][$this->columnNumber])) {
            $disallowed = $layoutColumns['disallowed'][$this->columnNumber];
        }
        if (isset($layoutColumns['allowed']) && isset($layoutColumns['allowed'][$this->columnNumber])) {
            $allowed = $layoutColumns['allowed'][$this->columnNumber];
        }
        if (isset($disallowed['CType'])) {
            $disallowedContentTypes = $disallowed['CType'];
        }
        if (!isset($disallowedContentTypes['*']) && !empty($disallowedContentTypes)) {
            foreach ($disallowedContentTypes as $key => &$ctype) {
                $ctype = $key;
            }
        } else {
            if (isset($disallowedContentTypes['*'])) {
                $disallowedGridTypes['*'] = '*';
                $disallowedListTypes['*'] = '*';
            }
        }
        // when everything is disallowed, no further checks are necessary
        if (!isset($disallowedContentTypes['*'])) {
            if (isset($allowed['CType'])) {
                $allowedContentTypes = $allowed['CType'];
            }
            if (!isset($allowedContentTypes['*']) && !empty($allowedContentTypes)) {
                // set allowed CTypes unless they are disallowed
                foreach ($allowedContentTypes as $key => &$ctype) {
                    if (isset($disallowedContentTypes[$key])) {
                        unset($allowedContentTypes[$key]);
                        unset($disallowedContentTypes[$key]);
                    } else {
                        $ctype = $key;
                    }
                }
            }
            // get disallowed list types
            $disallowedListTypes = $disallowed['list_type'];
            if (!isset($disallowedListTypes['*']) && !empty($disallowedListTypes)) {
                foreach ($disallowedListTypes as $key => &$ctype) {
                    $ctype = $key;
                }
            } else {
                if (isset($disallowedListTypes['*'])) {
                    // when each list type is disallowed, no CType list is necessary anymore
                    $disallowedListTypes['*'] = '*';
                    unset($allowedContentTypes['list']);
                }
            }
            // when each list type is disallowed, no further list type checks are necessary
            if (!isset($disallowedListTypes['*'])) {
                if (isset($allowed['list_type'])) {
                    $allowedListTypes = $allowed['list_type'];
                }
                if (!isset($allowedListTypes['*']) && !empty($allowedListTypes)) {
                    foreach ($allowedListTypes as $listType => &$listTypeData) {
                        // set allowed list types unless they are disallowed
                        if (isset($disallowedListTypes[$listType])) {
                            unset($allowedListTypes[$listType]);
                            unset($disallowedListTypes[$listType]);
                        } else {
                            $listTypeData = $listType;
                        }
                    }
                } else {
                    if (!empty($allowedContentTypes) && !empty($allowedListTypes)) {
                        $allowedContentTypes['list'] = 'list';
                    }
                    unset($allowedListTypes);
                }
            }
            // get disallowed grid types
            $disallowedGridTypes = $disallowed['tx_gridelements_backend_layout'];
            if (!isset($disallowedGridTypes['*']) && !empty($disallowedGridTypes)) {
                foreach ($disallowedGridTypes as $key => &$ctype) {
                    $ctype = $key;
                }
            } else {
                if (isset($disallowedGridTypes['*'])) {
                    // when each list type is disallowed, no CType gridelements_pi1 is necessary anymore
                    $disallowedGridTypes['*'] = '*';
                    unset($allowedContentTypes['gridelements_pi1']);
                }
            }
            // when each list type is disallowed, no further grid types checks are necessary
            if (!isset($disallowedGridTypes['*'])) {
                if (isset($allowed['tx_gridelements_backend_layout'])) {
                    $allowedGridTypes = $allowed['tx_gridelements_backend_layout'];
                }
                if (!isset($allowedGridTypes['*']) && !empty($allowedGridTypes)) {
                    foreach ($allowedGridTypes as $gridType => &$gridTypeData) {
                        // set allowed grid types unless they are disallowed
                        if (isset($disallowedGridTypes[$gridType])) {
                            unset($allowedGridTypes[$gridType]);
                            unset($disallowedGridTypes[$gridType]);
                        } else {
                            $gridTypeData = $gridType;
                        }
                    }
                } else {
                    if (!empty($allowedContentTypes) && !empty($allowedGridTypes)) {
                        $allowedContentTypes['gridelements_pi1'] = 'gridelements_pi1';
                    }
                    unset($allowedGridTypes);
                }
            }
        }

        if (!empty($disallowedContentTypes)) {
            $this->setDisallowedContentType(implode(',', $disallowedContentTypes));
        }
        if (!empty($disallowedListTypes)) {
            $this->setDisallowedListType(implode(',', $disallowedListTypes));
        }
        if (!empty($disallowedGridTypes)) {
            $this->setDisallowedGridType(implode(',', $disallowedGridTypes));
        }

        if (!empty($allowedContentTypes)) {
            $this->setAllowedContentType(implode(',', $allowedContentTypes));
        }
        if (!empty($allowedListTypes)) {
            $this->setAllowedListType(implode(',', $allowedListTypes));
        }
        if (!empty($allowedGridTypes)) {
            $this->setAllowedGridType(implode(',', $allowedGridTypes));
        }

        if (isset($layoutColumns['maxitems']) && isset($layoutColumns['maxitems'][$this->columnNumber])) {
            $this->setMaxitems((int)$layoutColumns['maxitems'][$this->columnNumber]);
        }
    }
}
