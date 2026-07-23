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
import interact from "interactjs";
import DocumentService from "@typo3/core/document-service.js";
import DataHandler from "@typo3/backend/ajax-data-handler.js";
import Icons from "@typo3/backend/icons.js";

class DragInWizard {
    constructor() {
        DocumentService.ready().then(() => DragInWizard.initialize());
    }

    static initialize() {
        if (!document.querySelector('typo3-backend-new-content-element-wizard-button[url]')) {
            return;
        }
        DragInWizard.addToggleButton();
        if (sessionStorage.getItem('gridelements-drag-in-wizard-active') === '1') {
            DragInWizard.showWizard();
        }
    }

    static addToggleButton() {
        const docHeaderBtnGroup = document.querySelector('.module-docheader-bar-column-left .btn-group')
            ?? document.querySelector('.module-docheader-bar .btn-group')
            ?? document.querySelector('.module-docheader .btn-toolbar');
        if (!docHeaderBtnGroup) {
            return;
        }

        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.id = 'gridelements-drag-in-wizard-toggle';
        toggleBtn.className = 'btn btn-default btn-sm';
        toggleBtn.title = TYPO3.lang['gridelements.toggleDragInWizard'] || 'Toggle Drag In Wizard';

        Icons.getIcon('actions-move-to-page', Icons.sizes.small).then(markup => {
            toggleBtn.innerHTML = markup;
        });

        toggleBtn.addEventListener('click', () => {
            DragInWizard.toggleWizard();
            toggleBtn.blur();
        });

        docHeaderBtnGroup.appendChild(toggleBtn);
    }

    static toggleWizard() {
        const panel = document.getElementById('gridelements-drag-in-wizard');
        if (!panel) {
            DragInWizard.showWizard();
        } else if (panel.classList.contains('active')) {
            panel.classList.remove('active');
            sessionStorage.setItem('gridelements-drag-in-wizard-active', '0');
        } else {
            panel.classList.add('active');
            sessionStorage.setItem('gridelements-drag-in-wizard-active', '1');
        }
    }

    static showWizard() {
        const existing = document.getElementById('gridelements-drag-in-wizard');
        if (existing) {
            existing.classList.add('active');
            sessionStorage.setItem('gridelements-drag-in-wizard-active', '1');
            return;
        }

        const firstButton = document.querySelector('typo3-backend-new-content-element-wizard-button[url]');
        if (!firstButton) {
            return;
        }
        const url = new URL(firstButton.getAttribute('url'), window.location.origin);
        url.searchParams.delete('colPos');
        url.searchParams.delete('tx_gridelements_container');
        url.searchParams.delete('tx_gridelements_columns');

        fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const wizardEl = doc.querySelector('typo3-backend-new-content-element-wizard');
                if (!wizardEl) {
                    return;
                }
                const raw = wizardEl.getAttribute('categories');
                if (!raw) {
                    return;
                }
                const categories = JSON.parse(raw);
                const columnRestrictions = DragInWizard.buildColumnRestrictions();
                const filteredCategories = {};
                for (const [key, cat] of Object.entries(categories)) {
                    if (!Array.isArray(cat.items)) {
                        filteredCategories[key] = cat;
                        continue;
                    }
                    const items = cat.items.filter(item => {
                        const dv = item.defaultValues ?? {};
                        return columnRestrictions.length === 0 ||
                            columnRestrictions.some(col => DragInWizard.columnAllows(
                                col,
                                dv.CType || '',
                                dv.list_type || '',
                                dv.tx_gridelements_backend_layout || ''
                            ));
                    });
                    if (items.length > 0) {
                        filteredCategories[key] = { ...cat, items };
                    }
                }
                DragInWizard.renderPanel(filteredCategories);
                DragInWizard.setupDraggable();
            });
    }

    static renderPanel(categories) {
        const categoryList = Object.values(categories).filter(
            cat => Array.isArray(cat.items) && cat.items.length > 0
        );
        if (categoryList.length === 0) {
            return;
        }

        const panel = document.createElement('div');
        panel.id = 'gridelements-drag-in-wizard';

        const tabBar = document.createElement('ul');
        tabBar.className = 'drag-in-wizard-tabs';
        tabBar.setAttribute('role', 'tablist');

        const panelsWrapper = document.createElement('div');
        panelsWrapper.className = 'drag-in-wizard-panels';

        const infoZone = document.createElement('div');
        infoZone.className = 'drag-in-wizard-info';
        const infoLabel = document.createElement('span');
        infoLabel.className = 'drag-in-wizard-info-label';
        const infoDesc = document.createElement('span');
        infoDesc.className = 'drag-in-wizard-info-desc';
        infoZone.appendChild(infoLabel);
        infoZone.appendChild(infoDesc);

        categoryList.forEach((category, index) => {
            const catId = 'drag-in-wizard-cat-' + index;
            const isFirst = index === 0;

            const tab = document.createElement('li');
            tab.className = 'drag-in-wizard-tab' + (isFirst ? ' active' : '');
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-controls', catId);
            tab.setAttribute('aria-selected', isFirst ? 'true' : 'false');
            tab.textContent = category.label;
            tab.addEventListener('click', () => DragInWizard.switchTab(tab, catId));
            tabBar.appendChild(tab);

            const tabPanel = document.createElement('div');
            tabPanel.className = 'drag-in-wizard-panel' + (isFirst ? ' active' : '');
            tabPanel.id = catId;
            tabPanel.setAttribute('role', 'tabpanel');
            tabPanel.hidden = !isFirst;

            for (const item of category.items) {
                const itemEl = document.createElement('div');
                itemEl.className = 'gridelements-drag-in-wizard-item';
                itemEl.dataset.defaultValues = JSON.stringify(item.defaultValues ?? {});
                itemEl.dataset.label = item.label ?? '';
                itemEl.dataset.description = item.description ?? '';

                const iconSpan = document.createElement('span');
                iconSpan.className = 'drag-in-wizard-item-icon';
                if (item.icon) {
                    Icons.getIcon(item.icon, Icons.sizes.medium).then(svg => {
                        iconSpan.innerHTML = svg;
                    });
                }

                itemEl.appendChild(iconSpan);

                itemEl.addEventListener('mouseenter', () => {
                    infoLabel.textContent = item.label ?? '';
                    infoDesc.textContent = item.description ?? '';
                    infoZone.classList.add('visible');
                });
                itemEl.addEventListener('mouseleave', () => {
                    infoZone.classList.remove('visible');
                });

                tabPanel.appendChild(itemEl);
            }

            panelsWrapper.appendChild(tabPanel);
        });

        panel.appendChild(tabBar);
        panel.appendChild(panelsWrapper);
        panel.appendChild(infoZone);

        document.body.appendChild(panel);

        panel.style.width = 'max-content';
        tabBar.style.flexWrap = 'nowrap';
        const naturalWidth = panel.offsetWidth + 10;
        tabBar.style.flexWrap = '';
        const maxWidth = Math.floor(window.innerWidth * 0.6);
        panel.style.width = Math.min(naturalWidth, maxWidth) + 'px';

        // eslint-disable-next-line no-unused-expressions
        panel.offsetHeight;
        panel.classList.add('active');
        sessionStorage.setItem('gridelements-drag-in-wizard-active', '1');
    }

    static switchTab(clickedTab, panelId) {
        const panel = document.getElementById('gridelements-drag-in-wizard');
        if (!panel) {
            return;
        }
        panel.querySelectorAll('.drag-in-wizard-tab').forEach(t => {
            t.classList.remove('active');
            t.setAttribute('aria-selected', 'false');
        });
        panel.querySelectorAll('.drag-in-wizard-panel').forEach(p => {
            p.classList.remove('active');
            p.hidden = true;
        });
        clickedTab.classList.add('active');
        clickedTab.setAttribute('aria-selected', 'true');
        const activePanel = document.getElementById(panelId);
        if (activePanel) {
            activePanel.classList.add('active');
            activePanel.hidden = false;
        }
    }

    static setupDraggable() {
        interact('.gridelements-drag-in-wizard-item').draggable({
            inertia: false,
            autoScroll: true,
            onstart: DragInWizard.onDragStart,
            onmove: DragInWizard.onDragMove,
            onend: DragInWizard.onDragEnd,
        });
    }

    static onDragStart(e) {
        const clone = e.target.cloneNode(true);
        clone.id = 'gridelements-drag-in-wizard-clone';
        clone.style.cssText = 'position:fixed;z-index:20001;opacity:0.85;pointer-events:none;left:-9999px;top:-9999px;';
        document.body.appendChild(clone);

        document.getElementById('gridelements-drag-in-wizard')?.classList.add('dragged');

        const defaultValues = JSON.parse(e.target.dataset.defaultValues || '{}');
        const ctype = defaultValues.CType || '';
        const listType = defaultValues.list_type || '';
        const gridType = defaultValues.tx_gridelements_backend_layout || '';

        document.querySelectorAll('.t3js-page-ce-dropzone-available').forEach(zone => {
            const addBtn = zone.parentElement?.querySelector('.t3js-page-new-ce');
            if (addBtn) {
                addBtn.hidden = true;
                if (DragInWizard.isDropAllowed(zone, ctype, listType, gridType)) {
                    zone.classList.add('active');
                }
            }
        });
    }

    static onDragMove(e) {
        const clone = document.getElementById('gridelements-drag-in-wizard-clone');
        if (clone) {
            clone.style.left = `${e.client.x - 20}px`;
            clone.style.top = `${e.client.y - 20}px`;
        }

        let hit = null;
        document.querySelectorAll('.t3js-page-ce-dropzone-available.active').forEach(zone => {
            const rect = zone.getBoundingClientRect();
            if (e.client.x >= rect.left && e.client.x <= rect.right
                && e.client.y >= rect.top && e.client.y <= rect.bottom) {
                hit = zone;
            }
        });

        document.querySelectorAll('.t3-page-ce-dropzone-possible').forEach(z => z.classList.remove('t3-page-ce-dropzone-possible'));
        if (hit) {
            hit.classList.add('t3-page-ce-dropzone-possible');
        }
        DragInWizard.currentDropZone = hit;
    }

    static onDragEnd(e) {
        document.getElementById('gridelements-drag-in-wizard-clone')?.remove();
        document.getElementById('gridelements-drag-in-wizard')?.classList.remove('dragged');

        const dropZone = DragInWizard.currentDropZone;
        DragInWizard.currentDropZone = null;

        document.querySelectorAll('.t3js-page-ce-dropzone-available').forEach(zone => {
            const addBtn = zone.parentElement?.querySelector('.t3js-page-new-ce');
            if (addBtn) {
                addBtn.hidden = false;
            }
            zone.classList.remove('active', 't3-page-ce-dropzone-possible');
        });

        if (dropZone) {
            DragInWizard.handleDrop(e.target, dropZone);
        }
    }

    static buildColumnRestrictions() {
        const getTypes = (col, attr) => {
            const val = col.getAttribute(attr);
            return val ? val.split(',').map(s => s.trim()).filter(Boolean) : null;
        };
        return [...document.querySelectorAll('.t3js-page-column')]
            .filter(col => !col.classList.contains('t3-page-ce-disable-new-ce'))
            .map(col => ({
                allowedCtype: getTypes(col, 'data-allowed-ctype'),
                disallowedCtype: getTypes(col, 'data-disallowed-ctype'),
                allowedListType: getTypes(col, 'data-allowed-list_type'),
                disallowedListType: getTypes(col, 'data-disallowed-list_type'),
                allowedGridType: getTypes(col, 'data-allowed-tx_gridelements_backend_layout'),
                disallowedGridType: getTypes(col, 'data-disallowed-tx_gridelements_backend_layout'),
            }));
    }

    static columnAllows(col, ctype, listType, gridType) {
        const ctypeOk = (
            (!col.allowedCtype || col.allowedCtype.includes('*') || col.allowedCtype.includes(ctype)) &&
            (!col.disallowedCtype || (!col.disallowedCtype.includes('*') && !col.disallowedCtype.includes(ctype)))
        );
        if (!ctypeOk) return false;
        if (listType) {
            const listTypeOk = (
                (!col.allowedListType || col.allowedListType.includes('*') || col.allowedListType.includes(listType)) &&
                (!col.disallowedListType || (!col.disallowedListType.includes('*') && !col.disallowedListType.includes(listType)))
            );
            if (!listTypeOk) return false;
        }
        if (gridType) {
            const gridTypeOk = (
                (!col.allowedGridType || col.allowedGridType.includes('*') || col.allowedGridType.includes(gridType)) &&
                (!col.disallowedGridType || (!col.disallowedGridType.includes('*') && !col.disallowedGridType.includes(gridType)))
            );
            if (!gridTypeOk) return false;
        }
        return true;
    }

    static resolveEffectiveAllowedCtype(allowedCtype, allowedListType, allowedGridType) {
        if (!allowedCtype || allowedCtype.includes('*')) {
            return allowedCtype;
        }
        const list = [...allowedCtype];
        if (allowedListType && !list.includes('list')) {
            list.push('list');
        }
        if (allowedGridType && !list.includes('gridelements_pi1')) {
            list.push('gridelements_pi1');
        }
        return list;
    }

    static isDropAllowed(zone, ctype, listType, gridType) {
        const column = zone.closest('.t3js-page-column');
        if (!column) return true;
        if (column.classList.contains('t3-page-ce-disable-new-ce')) return false;

        const getTypes = (attr) => {
            const val = column.getAttribute(attr);
            return val ? val.split(',').map(s => s.trim()).filter(Boolean) : null;
        };
        const allowedListType = getTypes('data-allowed-list_type');
        const allowedGridType = getTypes('data-allowed-tx_gridelements_backend_layout');
        return DragInWizard.columnAllows({
            allowedCtype: DragInWizard.resolveEffectiveAllowedCtype(getTypes('data-allowed-ctype'), allowedListType, allowedGridType),
            disallowedCtype: getTypes('data-disallowed-ctype'),
            allowedListType,
            disallowedListType: getTypes('data-disallowed-list_type'),
            allowedGridType,
            disallowedGridType: getTypes('data-disallowed-tx_gridelements_backend_layout'),
        }, ctype, listType, gridType);
    }

    static handleDrop(draggedItem, dropZone) {
        const defaultValues = JSON.parse(draggedItem.dataset.defaultValues || '{}');

        const precedingElement = dropZone.closest('.t3js-page-ce');
        const precedingUid = precedingElement?.dataset.uid;
        const pid = parseInt(dropZone.closest('[data-page]')?.dataset.page ?? '0', 10);
        const targetPid = precedingUid ? (0 - parseInt(precedingUid, 10)) : pid;

        const gridContainerEl = dropZone.closest('.t3-grid-element-container')?.closest('.t3js-page-ce');
        const container = gridContainerEl ? parseInt(gridContainerEl.dataset.uid || '0', 10) : 0;

        const colPosData = parseInt(dropZone.closest('[data-colpos]')?.dataset.colpos ?? '0', 10);
        const language = parseInt(dropZone.closest('[data-language-uid]')?.dataset.languageUid ?? '0', 10);

        const colPos = container > 0 ? -1 : (targetPid !== 0 ? colPosData : 0);
        const gridColumn = container > 0 ? colPosData : 0;

        const newElementData = Object.assign({}, defaultValues, {
            pid: targetPid,
            colPos: colPos,
            sys_language_uid: language,
            tx_gridelements_container: container,
            tx_gridelements_columns: gridColumn,
        });

        if (!newElementData.header) {
            newElementData.header = TYPO3.lang['tx_gridelements_js.newcontentelementheader'] || '';
        }

        const moduleEl = document.querySelector('.module');
        const scrollTarget = moduleEl
            ? moduleEl.scrollTop + dropZone.getBoundingClientRect().top - moduleEl.getBoundingClientRect().top - 20
            : dropZone.getBoundingClientRect().top + window.scrollY - 20;
        DataHandler.process(
            { data: { tt_content: { NEW234134: newElementData } }, DDinsertNew: 1 }
        ).then(result => {
            if (!result.hasErrors) {
                sessionStorage.setItem('gridelements-drag-in-wizard-scroll', Math.round(scrollTarget).toString());
                self.location.reload(true);
            }
        });
    }
}

DragInWizard.currentDropZone = null;

const _dragInWizardScroll = sessionStorage.getItem('gridelements-drag-in-wizard-scroll');
if (_dragInWizardScroll !== null) {
    sessionStorage.removeItem('gridelements-drag-in-wizard-scroll');
    const _scrollTo = parseInt(_dragInWizardScroll, 10);
    let _scrollAttempts = 20;
    const _applyScroll = () => {
        document.querySelector('.module')?.scrollTo({ top: _scrollTo, behavior: 'instant' });
        if (--_scrollAttempts > 0) {
            requestAnimationFrame(_applyScroll);
        }
    };
    requestAnimationFrame(_applyScroll);
}

export default new DragInWizard();
