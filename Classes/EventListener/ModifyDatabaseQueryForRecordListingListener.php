<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\EventListener;

use TYPO3\CMS\Backend\View\Event\ModifyDatabaseQueryForRecordListingEvent;

class ModifyDatabaseQueryForRecordListingListener
{
    /**
     * @param array $gridElementsExtensionConfiguration
     */
    public function __construct(
        private readonly array $gridElementsExtensionConfiguration
    ) {
    }

    /**
     * Modify the database query for the record list
     * we are only displaying base level elements
     * if nestingInListModule is enabled
     *
     * @param ModifyDatabaseQueryForRecordListingEvent $event
     */
    public function __invoke(ModifyDatabaseQueryForRecordListingEvent $event)
    {
        if (empty($this->gridElementsExtensionConfiguration['nestingInListModule'])) {
            return;
        }

        if ($event->getTable() === 'tt_content') {
            $event->getQueryBuilder()->andWhere('colPos != -1');
        }
    }
}
