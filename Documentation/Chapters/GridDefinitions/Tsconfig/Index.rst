.. include:: /Includes.rst.txt

.. _TSconfig:


TSconfig
--------

Grid definitions live in TSconfig. :ref:`Grid TS Syntax
<GridTsSyntax>` documents the notation itself, rows, columns, cells
and their restrictions; this page documents the TSconfig options
around that notation: where a grid layout's configuration is looked
up, how several sources of layouts combine, and how a page tree is
pointed at the page holding them. It is not a general introduction to
TYPO3 TSconfig.

.. ### BEGIN~OF~TABLE ###

.. _tsconfig-tx-gridelements:

tx_gridelements
^^^^^^^^^^^^^^^^

.. _tsconfig-tx-gridelements-setup:

tx_gridelements.setup
""""""""""""""""""""""

.. container:: table-row

   Property
         tx_gridelements.setup

   Data type
         Grid TS structure

   Description
         Container for one or more grid layout definitions, each keyed
         by a layout ID under ``tx_gridelements.setup.<id>``.

   Default
         N/A

.. _tsconfig-tx-gridelements-setup-id:

tx_gridelements.setup.<id>
""""""""""""""""""""""""""""

.. container:: table-row

   Property
         tx_gridelements.setup.<id>

   Data type
         Grid TS structure, plus the options below

   Description
         Defines a grid layout entirely through TSconfig, without a CE
         backend layout database record. ``<id>`` is the layout
         identifier a Grid Element's own
         ``tx_gridelements_backend_layout`` field selects. Build the
         structure with the :ref:`Grid Wizard <GridWizard>` and copy
         it here, or write it by hand following :ref:`Grid TS Syntax
         <GridTsSyntax>`. A layout defined this way and a layout
         stored as a database record can coexist under the same ID,
         see ``overruleRecords`` below for which one wins.

   Default
         N/A

Alongside the grid structure itself
(``tx_gridelements.setup.<id>.config``), a TSconfig-defined layout
accepts a small number of further keys, mirroring fields available on
a CE backend layout database record:

.. list-table::
   :header-rows: 1
   :widths: 30 70

   * - Key
     - Role
   * - ``config``
     - The layout's own :ref:`Grid TS Syntax <GridTsSyntax>`
       (``colCount``, ``rowCount``, ``rows``, and so on).
   * - ``icon``
     - One or more icon references shown for this layout in the
       backend.
   * - ``topLevelLayout``
     - Restricts whether a Grid Element using this layout may itself
       be nested inside another container, see :ref:`Restrictions`.
   * - ``flexformDS``
     - A FlexForm data structure for Grid Elements using this layout,
       see :ref:`Flexform`.

.. _tsconfig-tx-gridelements-overrulerecords:

tx_gridelements.overruleRecords
""""""""""""""""""""""""""""""""

.. container:: table-row

   Property
         tx_gridelements.overruleRecords

   Data type
         boolean

   Description
         When a layout ID exists both as a ``tx_gridelements.setup``
         entry and as a CE backend layout database record, this
         setting decides which one wins where they overlap. ``0``
         (default) lets the database record's values take precedence;
         ``1`` lets the TSconfig values take precedence instead.

   Default
         0

.. _tsconfig-tx-gridelements-excludelayoutids:

tx_gridelements.excludeLayoutIds
"""""""""""""""""""""""""""""""""

.. container:: table-row

   Property
         tx_gridelements.excludeLayoutIds

   Data type
         List of strings

   Description
         A comma-separated list of layout IDs, TSconfig-defined or
         database records, to exclude from this branch of the page
         tree. Excluded layouts are filtered out before use, so they
         are unavailable in this branch even if defined further up
         the page tree or globally.

   Default
         N/A

.. _tsconfig-TCEFORM-tt-content-tx-gridelements-backend-layout-PAGE-TSCONFIG-ID:

TCEFORM.tt_content.tx_gridelements_backend_layout.PAGE_TSCONFIG_ID
""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""

.. container:: table-row

   Property
         TCEFORM.tt_content.tx_gridelements_backend_layout.PAGE_TSCONFIG_ID

   Data type
         Integer

   Description
         The uid of the page whose storage folder holds a project's CE
         backend layout database records, so Grid Elements can find
         them regardless of where in the page tree they are used.
         Leave unset to resolve layout records from the current page
         and its own storage folder instead.

   Default
         N/A

.. ###### END~OF~TABLE ######

Obsolete option: removeChildrenFromList
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Earlier documentation described
``TCEFORM.tt_content.tx_gridelements_backend_layout.removeChildrenFromList``
as a setting that hides a Grid Element's children from the list
module, needed at the time to work around problems with the list
module's up/down sorting arrows on grid children.

.. code-block:: typoscript

   TCEFORM.tt_content.tx_gridelements_backend_layout {
     removeChildrenFromList = 1
   }

This key is not read by any current code path; setting it has no
effect. The underlying problem it addressed is instead handled by the
extension configuration option ``nestingInListModule``, which, once
enabled, nests grid children under their container in the list module
with per-user expand and collapse state rather than hiding them, see
:ref:`DeveloperReferenceListModuleIntegration`. Remove this key from
any project's TSconfig; it does nothing.

.. _TSconfigGridElementsScope:

Where Grid Elements uses TSconfig
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Every option on this page is page TSconfig, resolved for the page a
Grid Element or its container lives on, the same mechanism TYPO3 Core
uses for page-level Backend Layouts and other editor-facing
configuration. Frontend rendering output is configured separately,
through TypoScript, see :ref:`Rendering`; the two are never
interchangeable, and a grid definition never lives in TypoScript
``setup``.
