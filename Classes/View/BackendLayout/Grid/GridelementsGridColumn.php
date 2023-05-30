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

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumn;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
    protected int $gridContainerId;

    /**
     * @var bool
     */
    protected bool $collapsed = false;

    /**
     * @var array
     */
    protected array $allowed = [];

    /**
     * @var string
     */
    protected string $allowedContentType = '';

    /**
     * @var string
     */
    protected string $allowedListType = '';

    /**
     * @var string
     */
    protected string $allowedGridType = '';

    /**
     * @var array
     */
    protected array $disallowed = [];

    /**
     * @var string
     */
    protected string $disallowedContentType = '';

    /**
     * @var string
     */
    protected string $disallowedListType = '';

    /**
     * @var string
     */
    protected string $disallowedGridType = '';

    /**
     * @var int
     */
    protected int $maxitems = 0;

    /**
     * @var bool
     */
    protected bool $disableNewContent = false;

    /**
     * @var bool
     */
    protected bool $tooManyItems = false;

    /**
     * @var bool
     */
    protected bool $active = false;

    /**
     * @var string
     */
    protected string $maxItemsClass = '';

    /**
     * @param PageLayoutContext $context
     * @param array $columnDefinition
     * @param int $gridContainerId
     */
    public function __construct(PageLayoutContext $context, array $columnDefinition, int $gridContainerId)
    {
        parent::__construct($context, $columnDefinition);
        $this->gridContainerId = $gridContainerId;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->columnNumber !== null && $this->active === true;
    }

    /**
     * @return int
     */
    public function getColSpan(): int
    {
        return $this->colSpan;
    }

    /**
     * @return int
     */
    public function getRowSpan(): int
    {
        return $this->rowSpan;
    }

    /**
     * @return int|null
     */
    public function getGridContainerId(): ?int
    {
        return $this->gridContainerId;
    }

    public function setActive()
    {
        $this->active = true;
    }

    /**
     * @param bool $collapsed
     */
    public function setCollapsed(bool $collapsed)
    {
        $this->collapsed = $collapsed;
    }

    /**
     * @return bool|null
     */
    public function getCollapsed(): ?bool
    {
        return $this->collapsed;
    }

    /**
     * @param array $allowed
     */
    public function setAllowed(array $allowed)
    {
        $this->allowed = $allowed;
    }

    /**
     * @return array|null
     */
    public function getAllowed(): ?array
    {
        return $this->allowed;
    }

    /**
     * @param string $allowedContentType
     */
    public function setAllowedContentType(string $allowedContentType)
    {
        $this->allowedContentType = $allowedContentType;
    }

    /**
     * @return string|null
     */
    public function getAllowedContentType(): ?string
    {
        return $this->allowedContentType;
    }

    /**
     * @param string $allowedListType
     */
    public function setAllowedListType(string $allowedListType)
    {
        $this->allowedListType = $allowedListType;
    }

    /**
     * @return string|null
     */
    public function getAllowedListType(): ?string
    {
        return $this->allowedListType;
    }

    /**
     * @param string $allowedGridType
     */
    public function setAllowedGridType(string $allowedGridType)
    {
        $this->allowedGridType = $allowedGridType;
    }

    /**
     * @return string|null
     */
    public function getAllowedGridType(): ?string
    {
        return $this->allowedGridType;
    }

    /**
     * @param array $disallowed
     */
    public function setDisallowed(array $disallowed)
    {
        $this->disallowed = $disallowed;
    }

    /**
     * @return array|null
     */
    public function getDisallowed(): ?array
    {
        return $this->disallowed;
    }

    /**
     * @param string $disallowedContentType
     */
    public function setDisallowedContentType(string $disallowedContentType)
    {
        $this->disallowedContentType = $disallowedContentType;
    }

    /**
     * @return string|null
     */
    public function getDisallowedContentType(): ?string
    {
        return $this->disallowedContentType;
    }

    /**
     * @param string $disallowedListType
     */
    public function setDisallowedListType(string $disallowedListType)
    {
        $this->disallowedListType = $disallowedListType;
    }

    /**
     * @return string|null
     */
    public function getDisallowedListType(): ?string
    {
        return $this->disallowedListType;
    }

    /**
     * @param string $disallowedGridType
     */
    public function setDisallowedGridType(string $disallowedGridType)
    {
        $this->disallowedGridType = $disallowedGridType;
    }

    /**
     * @return string|null
     */
    public function getDisallowedGridType(): ?string
    {
        return $this->disallowedGridType;
    }

    /**
     * @param int $maxitems
     */
    public function setMaxitems(int $maxitems)
    {
        $this->maxitems = $maxitems;
    }

    /**
     * @return int|null
     */
    public function getMaxitems(): ?int
    {
        return $this->maxitems;
    }

    /**
     * @return int|null
     */
    public function getNumberOfItems(): ?int
    {
        return count($this->items);
    }

    /**
     * @param bool $disableNewContent
     */
    public function setDisableNewContent(bool $disableNewContent)
    {
        $this->disableNewContent = $disableNewContent;
    }

    /**
     * @return bool|null
     */
    public function getDisableNewContent(): ?bool
    {
        return $this->disableNewContent;
    }

    /**
     * @param bool $tooManyItems
     */
    public function setTooManyItems(bool $tooManyItems)
    {
        $this->tooManyItems = $tooManyItems;
    }

    /**
     * @return bool|null
     */
    public function getTooManyItems(): ?bool
    {
        return $this->tooManyItems;
    }

    /**
     * @param string $maxItemsClass
     */
    public function setMaxItemsClass(string $maxItemsClass)
    {
        $this->maxItemsClass = $maxItemsClass;
    }

    /**
     * @return string|null
     */
    public function getMaxItemsClass(): ?string
    {
        return $this->maxItemsClass;
    }

    /**
     * @param array $layoutColumns
     */
    public function setRestrictions(array $layoutColumns)
    {
        if (empty($layoutColumns)) {
            return;
        }

        $disallowedContentTypes = [];
        $disallowedListTypes = [];
        $disallowedGridTypes = [];
        $allowedContentTypes = [];
        $allowedListTypes = [];
        $allowedGridTypes = [];

        // first get disallowed CTypes
        $this->setDisallowed($layoutColumns['disallowed'][$this->columnNumber] ?? []);
        $this->setAllowed($layoutColumns['allowed'][$this->columnNumber] ?? []);
        $disallowedContentTypes = $this->getDisallowed()['CType'] ?? [];
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
            $allowedContentTypes = $this->getAllowed()['CType'] ?? [];
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
            $disallowedListTypes = $this->getDisallowed()['list_type'] ?? [];
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
                $allowedListTypes = $this->getAllowed()['list_type'] ?? [];
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
            $disallowedGridTypes = $this->getDisallowed()['tx_gridelements_backend_layout'] ?? [];
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
                $allowedGridTypes = $this->getAllowed()['tx_gridelements_backend_layout'] ?? [];
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
            $this->setDisallowedContentType(implode(',', array_keys($disallowedContentTypes)));
        }
        if (!empty($disallowedListTypes)) {
            $this->setDisallowedListType(implode(',', array_keys($disallowedListTypes)));
        }
        if (!empty($disallowedGridTypes)) {
            $this->setDisallowedGridType(implode(',', array_keys($disallowedGridTypes)));
        }

        if (!empty($allowedContentTypes)) {
            $this->setAllowedContentType(implode(',', array_keys($allowedContentTypes)));
        }
        if (!empty($allowedListTypes)) {
            $this->setAllowedListType(implode(',', array_keys($allowedListTypes)));
        }
        if (!empty($allowedGridTypes)) {
            $this->setAllowedGridType(implode(',', array_keys($allowedGridTypes)));
        }

        if (isset($layoutColumns['maxitems']) && isset($layoutColumns['maxitems'][$this->columnNumber])) {
            $this->setMaxitems((int)$layoutColumns['maxitems'][$this->columnNumber]);
        }
    }

    /**
     * @param GridColumnItem $item
     */
    public function addItem(GridColumnItem $item): void
    {
        $this->items[] = $item;
        $this->setDisableNewContent($this->getNumberOfItems() >= $this->getMaxitems() && $this->getMaxitems() > 0);
        $this->setTooManyItems($this->getNumberOfItems() > $this->maxitems && $this->maxitems > 0);
        $this->setMaxItemsClass($this->getDisableNewContent() ? ' warning' : ' success');
        $this->setMaxItemsClass($this->getTooManyItems() ? ' danger' : $this->getMaxItemsClass());
    }

    /**
     * @return string
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    public function getNewContentUrlWithRestrictions(): string
    {
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $pageId = $this->context->getPageId();

        $allowed = base64_encode(json_encode($this->getAllowed()));
        $disallowed = base64_encode(json_encode($this->getDisallowed()));

        if ($this->context->getDrawingConfiguration()->getShowNewContentWizard()) {
            $urlParameters = [
                'id' => $pageId,
                'sys_language_uid' => $this->context->getSiteLanguage()->getLanguageId(),
                'tx_gridelements_allowed' => $allowed,
                'tx_gridelements_disallowed' => $disallowed,
                'tx_gridelements_container' => $this->getGridContainerId(),
                'tx_gridelements_columns' => $this->getColumnNumber(),
                'colPos' => -1,
                'uid_pid' => $pageId,
                'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI'),

            ];
            $routeName = BackendUtility::getPagesTSconfig($pageId)['mod.']['newContentElementWizard.']['override']
                ?? 'new_content_element_wizard';
        } else {
            $urlParameters = [
                'edit' => [
                    'tt_content' => [
                        $pageId => 'new',
                    ],
                ],
                'defVals' => [
                    'tt_content' => [
                        'colPos' => $this->getColumnNumber(),
                        'sys_language_uid' => $this->context->getSiteLanguage()->getLanguageId(),
                    ],
                ],
                'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI'),
            ];
            $routeName = 'record_edit';
        }

        return (string)$uriBuilder->buildUriFromRoute($routeName, $urlParameters);
    }
}
