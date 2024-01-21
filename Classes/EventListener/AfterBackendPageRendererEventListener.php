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

use TYPO3\CMS\Backend\Controller\Event\AfterBackendPageRenderEvent;
use TYPO3\CMS\Core\Page\PageRenderer;

final class AfterBackendPageRendererEventListener
{
    /**
     * @param PageRenderer|null $pageRenderer
     */
    public function __construct(
        private readonly PageRenderer|null $pageRenderer = null
    ) {
    }

    /**
     * @param AfterBackendPageRenderEvent $event
     */
    public function __invoke(AfterBackendPageRenderEvent $event): void
    {
        if (!empty($this->pageRenderer)) {
            $this->pageRenderer->addInlineLanguageLabelFile(
                    'EXT:gridelements/Resources/Private/Language/locallang_db.xlf',
                    'tx_gridelements_js'
            );
        }
    }
}
