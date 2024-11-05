<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\EventListener;

use TYPO3\CMS\Backend\View\Event\IsContentUsedOnPageLayoutEvent;

class IsContentUsedOnPageLayoutListener
{
    /**
     * Tell TYPO3 that gridelements are actually used
     *
     * @param IsContentUsedOnPageLayoutEvent $event
     */
    public function __invoke(IsContentUsedOnPageLayoutEvent $event)
    {
        $record = $event->getRecord();
        if ($event->isRecordUsed()) {
            $event->setUsed(true);
            return;
        }

        $event->setUsed(((int)$record['colPos']) === -1 && !empty($record['tx_gridelements_container']));
    }
}
