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

namespace GridElementsTeam\Gridelements\EventListener;

use TYPO3\CMS\Backend\Clipboard\Clipboard;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ModifyPageLayoutContentEventListener
{
    /**
     * @param array<string, mixed> $gridElementsExtensionConfiguration
     */
    public function __construct(
        private readonly array $gridElementsExtensionConfiguration
    ) {
    }

    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        $this->exposeClipboardPasteSettings($event);
        $this->loadDragInWizardModule();
    }

    private function exposeClipboardPasteSettings(ModifyPageLayoutContentEvent $event): void
    {
        $request = $event->getRequest();

        /** @var Clipboard $clipObj */
        $clipObj = GeneralUtility::makeInstance(Clipboard::class);
        $clipObj->initializeClipboard($request);
        $clipObj->lockToNormal();
        $clipBoard = $clipObj->clipData['normal'] ?? [];

        $clipBoardElementCType = '';
        $clipBoardElementListType = '';
        $clipBoardElementGridType = '';

        if (!empty($clipBoard['el'])) {
            $parts = GeneralUtility::trimExplode('|', key($clipBoard['el']));
            if (($parts[0] ?? '') === 'tt_content') {
                $row = BackendUtility::getRecord('tt_content', (int)($parts[1] ?? 0));
                if (!empty($row)) {
                    $clipBoardElementCType = $row['CType'] ?? '';
                    $clipBoardElementListType = $row['list_type'] ?? '';
                    $clipBoardElementGridType = ($row['CType'] === 'gridelements_pi1')
                        ? ($row['tx_gridelements_backend_layout'] ?? '')
                        : '';
                }
            }
        }

        $backendUser = $GLOBALS['BE_USER'] ?? null;
        $pasteReferenceAllowed = ($backendUser instanceof BackendUserAuthentication)
            && $backendUser->checkAuthMode('tt_content', 'CType', 'shortcut');

        GeneralUtility::makeInstance(PageRenderer::class)->addInlineSettingArray('gridelements', [
            'clipBoardElementCType' => $clipBoardElementCType,
            'clipBoardElementListType' => $clipBoardElementListType,
            'clipBoardElementTxGridelementsBackendLayout' => $clipBoardElementGridType,
            'pasteReferenceAllowed' => $pasteReferenceAllowed,
        ]);
    }

    private function loadDragInWizardModule(): void
    {
        if (!empty($this->gridElementsExtensionConfiguration['disableDragInWizard'])) {
            return;
        }

        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!empty($backendUser->uc['disableDragInWizard'] ?? null)) {
            return;
        }

        GeneralUtility::makeInstance(PageRenderer::class)
            ->loadJavaScriptModule('@gridelementsteam/gridelements/drag-in-wizard.js');
    }
}
