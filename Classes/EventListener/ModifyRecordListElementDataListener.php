<?php

namespace GridElementsTeam\Gridelements\EventListener;

use GridElementsTeam\Gridelements\Event\ModifyRecordListElementDataEvent;
use GridElementsTeam\Gridelements\Helper\GridElementsHelper;

class ModifyRecordListElementDataListener
{
    /**
     * @param array $gridElementsExtensionConfiguration
     */
    public function __construct(
        private readonly array $gridElementsExtensionConfiguration,
    ) {
    }

    /**
     * This listener should only be processed if nesting in the list module is enabled
     *
     * @return bool
     */
    private function canBeExecuted(): bool
    {
        return !empty($this->gridElementsExtensionConfiguration['nestingInListModule']);
    }

    /**
    /**
     * @param ModifyRecordListElementDataEvent $event
     */
    public function __invoke(ModifyRecordListElementDataEvent $event): void
    {
        if (!$this->canBeExecuted()) {
            return;
        }

        $row = $event->getRow();
        $theData = $event->getInputData();

        if (
            $row['CType'] === 'gridelements_pi1' &&
            !empty($row['tx_gridelements_backend_layout']) &&
            $event->getTable() === 'tt_content'
        ) {
            $elementChildren = GridElementsHelper::getChildren(
                $event->getTable(),
                $row['uid'],
                $row['pid'],
                '',
                0,
                $event->getParentObject()->selectFields
            );

            $layoutColumns = $event->getParentObject()->getGridelementsBackendLayouts()->getLayoutColumns((string)$row['tx_gridelements_backend_layout']);
            if (!empty($elementChildren)) {
                $theData['_CONTAINER_COLUMNS_'] = $layoutColumns;
                $theData['_EXPANDABLE_'] = true;
                $theData['_EXPAND_ID_'] = $event->getTable() . ':' . $row['uid'];
                $theData['_EXPAND_TABLE_'] = $event->getTable();
                $theData['_LEVEL_'] = $event->getLevel();
                $theData['_CHILDREN_'] = $elementChildren;
                $event->setReturnData($theData);
            }
        }
    }
}
