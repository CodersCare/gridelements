<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Backend;

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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Domain\Repository\Localization\LocalizationRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Versioning\VersionState;

/**
 * LocalizationController handles the AJAX requests for record localization
 *
 * @internal This class is a specific Backend controller implementation and is not considered part of the Public TYPO3 API.
 */
class LocalizationController
{
    /**
     * @var string
     */
    public const ACTION_COPY = 'copyFromLanguage';

    /**
     * @var string
     */
    public const ACTION_LOCALIZE = 'localize';

    /**
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * @var LocalizationRepository
     */
    protected $localizationRepository;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $this->localizationRepository = GeneralUtility::makeInstance(LocalizationRepository::class);
    }

    /**
     * Get a prepared summary of records being translated
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getRecordLocalizeSummary(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        if (!isset($params['pageId'], $params['destLanguageId'], $params['languageId'])) {
            return new JsonResponse(null, 400);
        }

        $pageId = (int)$params['pageId'];
        $destLanguageId = (int)$params['destLanguageId'];
        $languageId = (int)$params['languageId'];

        $records = [];
        $filteredRecords = [];
        $containers = [];
        $result = $this->localizationRepository->getRecordsToCopyDatabaseResult(
            $pageId,
            $destLanguageId,
            $languageId
        );

        while ($row = $result->fetch()) {
            BackendUtility::workspaceOL('tt_content', $row, -99, true);
            if (!$row || VersionState::cast($row['t3ver_state'])->equals(VersionState::DELETE_PLACEHOLDER)) {
                continue;
            }
            if ($row['CType'] === 'gridelements_pi1') {
                $containers[$row['uid']] = true;
            }
            $colPos = $row['colPos'];
            $container = $row['tx_gridelements_container'];
            $uid = $row['uid'];
            if (!isset($records[$colPos]) && !isset($containers[$container])) {
                $records[$colPos] = [];
            }
            if (!isset($containers[$container])) {
                $records[$colPos][] = [
                    'icon' => $this->iconFactory->getIconForRecord('tt_content', $row, Icon::SIZE_SMALL)->render(),
                    'title' => $row[$GLOBALS['TCA']['tt_content']['ctrl']['label']] ?? '',
                    'uid' => $uid,
                    'container' => $container,
                ];
            }
        }

        // keep only those items, that are not translated by their parent container anyway
        foreach ($records as $colPos => $columnRecords) {
            foreach ($columnRecords as $record) {
                if (!isset($containers[$record['container']])) {
                    if (!isset($filteredRecords[$colPos])) {
                        $filteredRecords[$colPos] = [];
                    }
                    $filteredRecords[$colPos][] = $record;
                }
            }
        }

        return (new JsonResponse())->setPayload([
            'records' => $filteredRecords,
            'columns' => $this->getPageColumns($pageId),
            'containers' => $containers,
        ]);
    }

    /**
     * @param int $pageId
     * @return array
     */
    protected function getPageColumns(int $pageId): array
    {
        $columns = [];
        $backendLayoutView = GeneralUtility::makeInstance(BackendLayoutView::class);
        $backendLayouts = $backendLayoutView->getSelectedBackendLayout($pageId);

        $columns[-1] = 'Gridelements';

        if (empty($backendLayouts['__items'])) {
            return [];
        }
        foreach ($backendLayouts['__items'] as $backendLayout) {
            $columns[(int)$backendLayout[1]] = $backendLayout[0];
        }
        if (isset($backendLayouts['__colPosList'])) {
            $backendLayouts['__colPosList'][] = -1;
        }

        return [
            'columns' => $columns,
            'columnList' => $backendLayouts['__colPosList'] ?? [],
        ];
    }
}
