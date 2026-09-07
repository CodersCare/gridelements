.. include:: /Includes.rst.txt

.. _DeveloperReferenceExtensionPoints:

=================
Extension Points
=================

Grid Elements integrates with TYPO3's backend and DataHandler almost
entirely through PSR-14 events, all registered in
``Configuration/Services.yaml``. A handful of legacy ``$GLOBALS`` hooks
remain in use where Core does not yet offer an event for the relevant
operation. XCLASSing is exceptional: exactly one core class is
XCLASSed, and only when a project explicitly opts in. This page lists
the actual current mechanisms by class and event name, and calls out
the two places where an older registration has become dead code
rather than describing it as if it still ran.

PSR-14 events Grid Elements listens to
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

.. list-table::
   :header-rows: 1
   :widths: 34 30 36

   * - Core event
     - Listener
     - Purpose
   * - ``AfterTcaCompilationEvent``
     - ``EventListener\ExtTablesInclusionPostProcessing``
     - Registers Grid Elements' Page Module preview renderers
       (``GridelementsPreviewRenderer``, ``ShortcutPreviewRenderer``)
       into the compiled TCA.
   * - ``BeforeFlexFormDataStructureIdentifierInitializedEvent``
     - ``EventListener\BeforeFlexFormDataStructureIdentifierInitializedListener``
     - Resolves the FlexForm data-structure identifier for
       ``gridelements_pi1`` containers.
   * - ``BeforeFlexFormDataStructureParsedEvent``
     - ``EventListener\BeforeFlexFormDataStructureParsedListener``
     - Supplies the default Grid Elements FlexForm data structure (or
       a project-defined ``flexformDS``) for that identifier.
   * - ``TYPO3\CMS\Backend\View\Event\ModifyDatabaseQueryForRecordListingEvent``
     - ``EventListener\ModifyDatabaseQueryForRecordListingListener``
     - Excludes grid children (``colPos = -1``) from the List module's
       base query, see :ref:`DeveloperReferenceListModuleIntegration`.
   * - ``TYPO3\CMS\Backend\Controller\Event\ModifyNewContentElementWizardItemsEvent``
     - ``EventListener\ModifyNewContentElementWizardItemsListener``
     - Filters the New Content Element Wizard's item list against the
       target cell's ``allowed``/``disallowed`` restrictions, see
       :ref:`Restrictions`.
   * - ``TYPO3\CMS\Backend\View\Event\IsContentUsedOnPageLayoutEvent``
     - ``EventListener\IsContentUsedOnPageLayoutListener``
     - Marks a grid child (``colPos = -1`` with a set
       ``tx_gridelements_container``) as used. This is the current,
       functioning replacement for the ``record_is_used`` hook, see
       below.
   * - ``TYPO3\CMS\Backend\Controller\Event\AfterBackendPageRenderEvent``
     - ``EventListener\AfterBackendPageRendererEventListener``
     - Injects Grid Elements' JavaScript language labels into the
       backend page.
   * - ``TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent``
     - ``EventListener\ModifyPageLayoutContentEventListener``
     - Exposes clipboard paste settings and loads the Drag-In Wizard
       JavaScript module in the Page Module, see
       :ref:`EditorGuideDragInWizard`.

Grid Elements' own event
^^^^^^^^^^^^^^^^^^^^^^^^^

``GridElementsTeam\Gridelements\Event\ModifyRecordListElementDataEvent``
is dispatched by Grid Elements itself, from the List module XCLASS
described below, once per row, and implements
``Psr\EventDispatcher\StoppableEventInterface``. Its own listener,
``EventListener\ModifyRecordListElementDataListener``, attaches the
container/child markers (``_CONTAINER_COLUMNS_``, ``_EXPANDABLE_``,
``_EXPAND_ID_``, ``_EXPAND_TABLE_``, ``_LEVEL_``, ``_CHILDREN_``) the
List module rendering needs, but it is a regular PSR-14 event on the
event bus like any other: another extension can listen to it too, for
example to add its own markers to the same row data.

TYPO3 hooks still in use
^^^^^^^^^^^^^^^^^^^^^^^^^

These are legacy ``$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']`` hook
registrations, kept because Core has not replaced them with PSR-14
events for the operations they cover.

.. list-table::
   :header-rows: 1
   :widths: 40 24 36

   * - Hook point
     - Class
     - Purpose
   * - ``t3lib/class.t3lib_tcemain.php`` ``processDatamapClass``
     - ``Hooks\DataHandler``
     - Field-array preprocessing and post-save consistency work, see
       :ref:`ParentChildIrre`, plus restriction enforcement, see
       :ref:`Restrictions`.
   * - ``t3lib/class.t3lib_tcemain.php`` ``processCmdmapClass``
     - ``Hooks\DataHandler``
     - Copy/move command handling ("Paste as Reference", child-count
       maintenance, cycle protection) and restriction enforcement for
       commands, see :ref:`ParentChildIrre` and :ref:`Restrictions`.
   * - ``t3lib/class.t3lib_tcemain.php`` ``moveRecordClass``
     - ``Hooks\DataHandler``
     - Registered against the same class, but ``Hooks\DataHandler``
       does not currently implement a ``moveRecord()`` method, so this
       particular registration has no effect. Move-time consistency is
       instead handled through ``processCmdmap()`` and
       ``processCmdmap_beforeStart()``, which do run on a
       drag-and-drop or cut/paste move.

Two further hook registrations exist in ``ext_tables.php`` but no
longer run, because the TYPO3 Core hook points they target have been
removed in the versions this branch supports (``^12.4 || ^13.4.7``):

* **``cms/layout/class.tx_cms_layout.php`` ``record_is_used``**
  (``Hooks\PageLayoutView``) is only registered for TYPO3 < 13, but
  the ``record_is_used`` hook point it targets was already removed
  from Core in TYPO3 12 (Breaking-98375), and the core class its
  method type-hints was itself removed in TYPO3 13. The class and its
  registration remain in the codebase as dead code across the whole
  supported version range. ``IsContentUsedOnPageLayoutListener`` above
  is the current, functioning mechanism; do not treat
  ``Hooks\PageLayoutView`` as active.
* **``typo3/class.db_list_extra.inc`` ``actions``**
  (``Hooks\DatabaseRecordList``, registered only when
  ``nestingInListModule`` is enabled) targets a hook point Core no
  longer dispatches either. Only one method on that class,
  ``contentCollapseIcon()``, remains functional, and it is reached by
  a direct call from the XCLASS below, not through this hook point,
  see :ref:`DeveloperReferenceListModuleIntegration`.

XCLASS (exceptional)
^^^^^^^^^^^^^^^^^^^^^

Grid Elements XCLASSes exactly one Core class,
``TYPO3\CMS\Backend\RecordList\DatabaseRecordList``, and only when a
project has explicitly enabled the ``nestingInListModule`` extension
configuration option. Two implementations are registered depending on
the running Core version, since the parent class's own API differs
between them:

.. list-table::
   :header-rows: 1
   :widths: 20 30 50

   * - TYPO3
     - Class
     - Notes
   * - 13
     - ``Xclass\DatabaseRecordList``
     - Marked ``@internal`` in its own docblock: it extends a Core
       class that is explicitly not part of Core's public API, and can
       change in a minor release. Do not XCLASS it further; extend
       Grid Elements' own event (above) instead where possible.
   * - 12
     - ``Xclass\DatabaseRecordList12``
     - Same role, adapted to the TYPO3 12 version of the parent class.

See :ref:`DeveloperReferenceListModuleIntegration` for what this
XCLASS actually renders and why an event alone was not enough for it.

Other Core registration points
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

A few further integrations use Core's own configuration-array
registries rather than a hook or an event. They are listed here for
completeness, since each is a place a project could otherwise assume
Grid Elements uses a hook where it does not:

* **FormEngine node registry**
  (``$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry']``)
  registers ``Wizard\GridelementsBackendLayoutWizardElement`` (TYPO3
  13) or ``Wizard\GridelementsBackendLayoutWizardElement12`` (TYPO3
  12) as the ``belayoutwizard`` FormEngine node, the Grid Wizard's
  backend rendering, see :ref:`GridWizard`.
* **Context menu item providers**
  (``$GLOBALS['TYPO3_CONF_VARS']['BE']['ContextMenu']['ItemProviders']``)
  registers ``ContextMenu\ItemProvider`` for the "Paste as Reference"
  action, see :ref:`EditorGuideReferences`.
* **Scheduler task types and console commands** register the data
  consistency tools described in full at :ref:`DataConsistencyTools`.

For how these mechanisms compare to EXT:container's own hooks, events
and class-replacement overrides, and the technically meaningful sense
in which "Core-native" does and does not apply to either extension,
see :ref:`GridElementsAndContainer`.
