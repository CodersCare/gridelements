<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\EventListener;

use TYPO3\CMS\Core\Configuration\Event\BeforeFlexFormDataStructureParsedEvent;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

final class BeforeFlexFormDataStructureParsedListener
{
    /**
     * @param BeforeFlexFormDataStructureParsedEvent $event
     */
    public function __invoke(BeforeFlexFormDataStructureParsedEvent $event)
    {
        $identifier = $event->getIdentifier();

        if (!empty($identifier['type']) && $identifier['type'] === 'gridelements-dummy') {
            $event->setDataStructure('FILE:EXT:gridelements/Configuration/FlexForms/default_flexform_configuration.xml');
        }
        if (!empty($identifier['flexformDS'])) {
            $event->setDataStructure($identifier['flexformDS']);
        }
    }

    /**
     * Get path to default flex form configuration
     *
     * @return string
     */
    protected function getPath(): string
    {
        return sprintf('%s/Configuration/Flexforms/default_flexform_configuration.xml', ExtensionManagementUtility::extPath('gridelements'));
    }
}
