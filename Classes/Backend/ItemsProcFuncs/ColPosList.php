<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Backend\ItemsProcFuncs;

/***************************************************************
 *  Copyright notice
 *  (c) 2013 Jo Hasenau <info@cybercraft.de>
 *  All rights reserved
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use GridElementsTeam\Gridelements\Helper\GridElementsHelper;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class/Function which manipulates the item-array for table/field tt_content colPos.
 *
 * @author Jo Hasenau <info@cybercraft.de>
 */
class ColPosList implements SingletonInterface
{
    /**
     * ItemProcFunc for colpos items
     *
     * @param array $params The array of parameters that is used to render the item list
     */
    public function itemsProcFunc(array &$params)
    {
        if ((int)$params['row']['pid'] > 0) {
            if (isset($params['row']['CType'])) {
                $contentType = is_array($params['row']['CType']) ? ($params['row']['CType'][0] ?? '') : $params['row']['CType'];
            }
            if (isset($params['row']['list_type'])) {
                $listType = is_array($params['row']['list_type']) ? ($params['row']['list_type'][0] ?? '') : $params['row']['list_type'];
            }
            if (isset($params['row']['tx_gridelements_backend_layout'])) {
                $gridType = is_array($params['row']['tx_gridelements_backend_layout']) ? ($params['row']['tx_gridelements_backend_layout'][0] ?? '') : $params['row']['tx_gridelements_backend_layout'];
            }
            $params['items'] = $this->addColPosListLayoutItems(
                (int)$params['row']['pid'],
                $params['items'],
                $contentType ?? '',
                $listType ?? '',
                $gridType ?? '',
                (int)($params['row']['tx_gridelements_container'] ?? 0)
            );
        } else {
            // negative uid_pid values indicate that the element has been inserted after an existing element
            // so there is no pid to get the backendLayout for and we have to get that first
            $existingElement = BackendUtility::getRecordWSOL(
                'tt_content',
                -((int)$params['row']['pid']),
                'pid,CType,list_type,tx_gridelements_backend_layout,tx_gridelements_container'
            );
            if ($existingElement && $existingElement['pid'] > 0) {
                $params['items'] = $this->addColPosListLayoutItems(
                    $existingElement['pid'],
                    $params['items'],
                    $existingElement['CType'],
                    $existingElement['list_type'],
                    $existingElement['tx_gridelements_backend_layout'],
                    (int)$existingElement['tx_gridelements_container']
                );
            }
        }
    }

    /**
     * Adds items to a colpos list
     *
     * @param int $pageId The uid of the page we are currently working on
     * @param array $items The array of items before the action
     * @param string $contentType The content type of the item holding the colPosList
     * @param string $listType The list type of the item holding the colPosList
     * @param string $gridType The grid type of the item holding the colPosList
     * @param int $container
     *
     * @return array $items The ready made array of items
     */
    protected function addColPosListLayoutItems(
        int $pageId,
        array $items,
        string $contentType = '',
        string $listType = '',
        string $gridType = '',
        int $container = 0
    ): array {
        if (empty($container)) {
            $layout = GridElementsHelper::getSelectedBackendLayout($pageId);
            if ($layout) {
                if ($contentType !== '' && !empty($layout['__items'])) {
                    foreach ($layout['__items'] as $itemKey => $itemArray) {
                        $column = $itemArray['value'] ?? $itemArray[1];
                        if (
                            (
                                isset($layout['allowed'][$column]) &&
                                !isset($layout['allowed'][$column]['CType'][$contentType]) &&
                                !isset($layout['allowed'][$column]['CType']['*'])
                            ) ||
                            (
                                !empty($listType) &&
                                isset($layout['allowed'][$column]) &&
                                isset($layout['allowed'][$column]['list_type']) &&
                                !isset($layout['allowed'][$column]['list_type'][$listType]) &&
                                !isset($layout['allowed'][$column]['list_type']['*'])
                            ) ||
                            (
                                !empty($gridType) &&
                                isset($layout['allowed'][$column]) &&
                                isset($layout['allowed'][$column]['tx_gridelements_backend_layout']) &&
                                !isset($layout['allowed'][$column]['tx_gridelements_backend_layout'][$gridType]) &&
                                !isset($layout['allowed'][$column]['tx_gridelements_backend_layout']['*'])
                            ) ||
                            (
                                isset($layout['disallowed'][$column]) &&
                                (
                                    isset($layout['disallowed'][$column]['CType'][$contentType]) ||
                                    isset($layout['disallowed'][$column]['CType']['*'])
                                )
                            ) ||
                            (
                                !empty($listType) &&
                                isset($layout['disallowed'][$column]) &&
                                (
                                    isset($layout['disallowed'][$column]['list_type'][$listType]) ||
                                    isset($layout['disallowed'][$column]['list_type']['*'])
                                )
                            ) ||
                            (
                                !empty($gridType) &&
                                isset($layout['disallowed'][$column]) &&
                                (
                                    isset($layout['disallowed'][$column]['tx_gridelements_backend_layout'][$gridType]) ||
                                    isset($layout['disallowed'][$column]['tx_gridelements_backend_layout']['*'])
                                )
                            )
                        ) {
                            unset($layout['__items'][$itemKey]);
                        }
                    }
                }
                if (!empty($layout['__items'])) {
                    $items = $layout['__items'];
                }
            }
        } else {
            $items = [];
            $items[] = [
                LocalizationUtility::translate('LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xlf:tt_content.tx_gridelements_container'),
                '-1',
                null,
                null,
            ];
        }
        return $items;
    }
}
