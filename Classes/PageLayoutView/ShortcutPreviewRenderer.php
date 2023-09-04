<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\PageLayoutView;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;
use GridElementsTeam\Gridelements\Helper\GridElementsHelper;
use PDO;
use TYPO3\CMS\Backend\Preview\StandardContentPreviewRenderer;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ShortcutPreviewRenderer extends StandardContentPreviewRenderer
{
    /**
     * @var bool
     */
    protected bool $showHidden = true;

    /**
     * @var string
     */
    protected string $backPath = '';

    /**
     * @param QueryBuilder $ttContentQueryBuilder
     * @param array<string, string> $gridElementsExtensionConfiguration
     * @param IconFactory $iconFactory
     */
    public function __construct(
        protected QueryBuilder $ttContentQueryBuilder,
        protected array $gridElementsExtensionConfiguration,
        protected IconFactory $iconFactory,
    ) {
    }

    /**
     * Dedicated method for rendering preview body HTML for
     * the page module only. Receives the GridColumnItem
     * that contains the record for which a preview should be
     * rendered and returned.
     *
     * @param GridColumnItem $item
     * @return string
     */
    public function renderPageModulePreviewContent(GridColumnItem $item): string
    {
        $record = $item->getRecord();

        if (!empty($record['records'])) {
            $shortCutRenderItems = $this->addShortcutRenderItems($item);
            $preview = '';
            foreach ($shortCutRenderItems as $shortcutRecord) {
                $shortcutItem = GeneralUtility::makeInstance(GridColumnItem::class, $item->getContext(), $item->getColumn(), $shortcutRecord);
                $preview .= '<p class="pt-2 small"><b><a href="' . $shortcutItem->getEditUrl() . '">' . $this->getLanguageService()->sL('LLL:EXT:backend/Resources/Private/Language/locallang_layout.xlf:edit') . '</a></b></p>';
                $preview .= '<div class="mb-2 p-2 border reference">' . $shortcutItem->getPreview() . '<div class="reference-overlay"></div></div>';
            }
            return $preview;
        }
        return parent::renderPageModulePreviewContent($item);
    }

    /**
     * @param GridColumnItem $gridColumnItem
     * @return array
     */
    protected function addShortcutRenderItems(GridColumnItem $gridColumnItem): array
    {
        $renderItems = [];
        $record = $gridColumnItem->getRecord();
        $shortcutItems = explode(',', $record['records']);
        $collectedItems = [];
        foreach ($shortcutItems as $shortcutItem) {
            $shortcutItem = trim($shortcutItem);
            if (str_contains($shortcutItem, 'pages_')) {
                $this->collectContentDataFromPages(
                    $shortcutItem,
                    $collectedItems,
                    $record['recursive'],
                    $record['uid'],
                    $record['sys_language_uid']
                );
            } else {
                if (!str_contains($shortcutItem, '_')   || str_contains($shortcutItem, 'tt_content_')) {
                    $this->collectContentData(
                        $shortcutItem,
                        $collectedItems,
                        $record['uid'],
                        $record['sys_language_uid']
                    );
                }
            }
        }
        if (!empty($collectedItems)) {
            $record['shortcutItems'] = [];
            foreach ($collectedItems as $item) {
                if ($item) {
                    $renderItems[] = $item;
                }
            }
            $gridColumnItem->setRecord($record);
        }
        return $renderItems;
    }

    public function getTreeList($id, $depth, $begin = 0, $dontCheckEnableFields = false, $addSelectFields = '', $moreWhereClauses = '', array $prevId_array = [], $recursionLevel = 0)
    {
        $addCurrentPageId = false;
        $id = (int)$id;
        if ($id < 0) {
            $id = abs($id);
            $addCurrentPageId = true;
        }
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        if ($dontCheckEnableFields) {
            /** @phpstan-ignore-next-line **/
            $backupEnableFields = $pageRepository->where_hid_del;
            /** @phpstan-ignore-next-line **/
            $pageRepository->where_hid_del = '';
        }
        $result = $pageRepository->getDescendantPageIdsRecursive($id, (int)$depth, (int)$begin, [], (bool)$dontCheckEnableFields);
        if ($dontCheckEnableFields) {
            /** @phpstan-ignore-next-line **/
            $pageRepository->where_hid_del = $backupEnableFields;
        }
        if ($addCurrentPageId) {
            $result = array_merge([$id], $result);
        }
        return implode(',', $result);
    }

    /**
     * Collects tt_content data from a single page or a page tree starting at a given page
     *
     * @param string $shortcutItem : The single page to be used as the tree root
     * @param array $collectedItems : The collected item data rows ordered by parent position, column position and sorting
     * @param int $recursive : The number of levels for the recursion
     * @param int $parentUid : uid of the referencing tt_content record
     * @param int $language : sys_language_uid of the referencing tt_content record
     * @throws Exception
     */
    protected function collectContentDataFromPages(
        string $shortcutItem,
        array &$collectedItems,
        int $recursive = 0,
        int $parentUid = 0,
        int $language = 0
    ) {
        $itemList = str_replace('pages_', '', $shortcutItem);
        if ($recursive) {
            $itemList = $this->getTreeList($itemList, $recursive, 0, 1);
        }
        $itemList = GeneralUtility::intExplode(',', $itemList);

        $queryBuilder = $this->ttContentQueryBuilder;
        $queryBuilder->resetQueryParts();
        $queryBuilder->resetRestrictions();

        $items = $queryBuilder
            ->select('*')
            ->addSelectLiteral($queryBuilder->expr()->inSet(
                'pid',
                ':itemList',
            ) . ' AS inSet')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->neq(
                    'uid',
                    $queryBuilder->createNamedParameter($parentUid, PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->in(
                    'pid',
                    $queryBuilder->createNamedParameter($itemList, ArrayParameterType::INTEGER)
                ),
                $queryBuilder->expr()->gte('colPos', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)),
                $queryBuilder->expr()->in(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter([0, -1], ArrayParameterType::INTEGER)
                )
            )
            ->orderBy('inSet')
            ->addOrderBy('colPos')->addOrderBy('sorting')
            ->setParameter('itemList', $itemList, ArrayParameterType::INTEGER)
            ->executeQuery()->fetchAllAssociative();

        //->executeQuery()
        //->fetchAllAssociative();

        foreach ($items as $item) {
            if (!empty($this->gridElementsExtensionConfiguration['overlayShortcutTranslation']) && $language > 0) {
                $translatedItem = BackendUtility::getRecordLocalization('tt_content', $item['uid'], $language);
                if (!empty($translatedItem)) {
                    $item = array_shift($translatedItem);
                }
            }
            if (GridElementsHelper::getBackendUser()->workspace > 0) {
                unset($item['inSet']);
                BackendUtility::workspaceOL('tt_content', $item, GridElementsHelper::getBackendUser()->workspace);
            }
            $item['tx_gridelements_reference_container'] = $item['pid'];
            $collectedItems[] = $item;
        }
    }

    /**
     * Collects tt_content data from a single tt_content element
     *
     * @param string $shortcutItem : The tt_content element to fetch the data from
     * @param array $collectedItems : The collected item data row
     * @param int $parentUid : uid of the referencing tt_content record
     * @param int $language : sys_language_uid of the referencing tt_content record
     * @throws Exception
     */
    protected function collectContentData(string $shortcutItem, array &$collectedItems, int $parentUid, int $language)
    {
        $shortcutItem = str_replace('tt_content_', '', $shortcutItem);
        if ((int)$shortcutItem !== $parentUid) {
            $queryBuilder = $this->ttContentQueryBuilder;
            $queryBuilder->resetQueryParts();
            $queryBuilder->resetRestrictions();
            if ($this->showHidden) {
                $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
            }

            $queryBuilder->resetQueryParts();
            $item = $queryBuilder
                ->select('*')
                ->from('tt_content')
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter((int)$shortcutItem, PDO::PARAM_INT)
                    )
                )->setMaxResults(1)->executeQuery()
                ->fetchAssociative();

            if (!empty($this->gridElementsExtensionConfiguration['overlayShortcutTranslation']) && $language > 0) {
                $translatedItem = BackendUtility::getRecordLocalization('tt_content', $item['uid'], $language);
                if (!empty($translatedItem)) {
                    $item = array_shift($translatedItem);
                }
            }

            if (GridElementsHelper::getBackendUser()->workspace > 0) {
                BackendUtility::workspaceOL(
                    'tt_content',
                    $item,
                    GridElementsHelper::getBackendUser()->workspace
                );
            }
            $collectedItems[] = $item;
        }
    }
}
