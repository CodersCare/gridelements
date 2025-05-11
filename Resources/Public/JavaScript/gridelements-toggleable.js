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

import DocumentService from '@typo3/core/document-service.js';
import $ from 'jquery';
import PersistentStorage from '@typo3/backend/storage/persistent.js';

/**
 * @exports @gridelementsteam/gridelements/gridelements-toggleable
 */
class GridelementsToggleable {
  static GridelementsColumnToggle = '.t3js-toggle-gridelements-column';

  constructor() {
    DocumentService.ready().then((() => {
      GridelementsToggleable.initialize()
    }))
  }

  static initialize() {
    if ($('.t3js-page-columns').length) {
      GridelementsToggleable.activateAllCollapseIcons();
    }
  }

  /**
   * activates the arrow icons to show/hide content previews within a certain grid column
   */
  static activateAllCollapseIcons () {
    GridelementsToggleable.activateCollapseIcons();

    const lastIconElement = document.querySelector('.module-docheader-bar-column-left .btn-group .icon:last-child');

    if (lastIconElement && lastIconElement.parentNode) {

      const lastIcon = lastIconElement.parentNode;
      const addNewIcon = document.querySelector('.t3js-toggle-gridelements-column');

      if (addNewIcon) {

        let newIcon = addNewIcon.cloneNode(true);
        newIcon.className = 'btn btn-default btn-sm t3js-gridcolumn-toggle t3js-gridcolumn-expand';
        lastIcon.insertAdjacentElement('afterend', newIcon);

        [...newIcon.childNodes].filter(node => node.nodeType === 3).forEach(node => node.remove());
        newIcon.querySelector('.icon-actions-view-list-collapse').remove();
        newIcon.removeAttribute('onclick');
        newIcon.setAttribute('title', 'Expand all grid columns');

        newIcon = addNewIcon.cloneNode(true);
        newIcon.className = 'btn btn-default btn-sm t3js-gridcolumn-toggle';
        lastIcon.insertAdjacentElement('afterend', newIcon);

        [...newIcon.childNodes].filter(node => node.nodeType === 3).forEach(node => node.remove());
        newIcon.querySelector('.icon-actions-view-list-expand').remove();
        newIcon.removeAttribute('onclick');
        newIcon.setAttribute('title', 'Collapse all grid columns');

        document.addEventListener('click', function (evt) {
          if (evt.target.closest('.t3js-gridcolumn-toggle')) {
            evt.preventDefault();

            const me = evt.target.closest('.t3js-gridcolumn-toggle');
            const collapse = me.classList.contains('t3js-gridcolumn-expand') ? 0 : 1;

            let storedModuleDataPage = {};

            if (PersistentStorage.isset('moduleData.page.gridelementsCollapsedColumns')) {
              storedModuleDataPage = PersistentStorage.get('moduleData.page.gridelementsCollapsedColumns');
            }

            const collapseConfig = {};
            document.querySelectorAll('[data-columnkey]').forEach(elem => {
              const columnKey = elem.getAttribute('data-columnkey');
              collapseConfig[columnKey] = collapse;
              elem.classList.remove('collapsed', 'expanded');
              elem.classList.add(collapse ? 'collapsed' : 'expanded');
            });

            storedModuleDataPage = {...storedModuleDataPage, ...collapseConfig};
            PersistentStorage.set('moduleData.page.gridelementsCollapsedColumns', storedModuleDataPage);
          }
        });
      }
    }
  };

  static activateCollapseIcons() {
    document.addEventListener('click', function(evt) {
      const me = evt.target.closest(GridelementsToggleable.GridelementsColumnToggle);
      if (me) {
        evt.preventDefault();

        const columnKey = me.closest('.t3js-page-column').getAttribute('data-columnkey');
        const isExpanded = me.getAttribute('data-state') === 'expanded';

        let storedModuleDataPage = {};

        if (PersistentStorage.isset('moduleData.page.gridelementsCollapsedColumns')) {
          storedModuleDataPage = PersistentStorage.get('moduleData.page.gridelementsCollapsedColumns');
        }

        const expandConfig = {};
        expandConfig[columnKey] = isExpanded ? 1 : 0;

        storedModuleDataPage = {...storedModuleDataPage, ...expandConfig};
        PersistentStorage.set('moduleData.page.gridelementsCollapsedColumns', storedModuleDataPage).then(() => {
          me.setAttribute('data-state', isExpanded ? 'collapsed' : 'expanded');
        });

        me.closest('.t3-grid-cell').classList.toggle('collapsed');
        me.closest('.t3-grid-cell').classList.toggle('expanded');

        const originalTitle = me.getAttribute('title');
        me.setAttribute('title', me.getAttribute('data-toggle-title'));
        me.setAttribute('data-toggle-title', originalTitle);
        me.blur();
      }
    });

    document.querySelectorAll('.t3-page-column-header-icons').forEach(elem => {
      elem.classList.add('btn-group', 'btn-group-sm');
      elem.querySelectorAll('a').forEach(aElem => aElem.classList.add('btn', 'btn-default'));
    });
  };
}

export default new GridelementsToggleable;
