.. include:: /Includes.rst.txt

.. _DeveloperReferenceListModuleIntegration:

===========================
List Module Integration
===========================

By default, TYPO3's Web > List module lists ``tt_content`` records the
same way it lists any other table: a flat list per ``colPos``, with no
awareness that some of those rows are Grid Element children. Grid
Elements can instead nest a container's children under it in the List
module, with per-user expand and collapse state, but this is off by
default and must be enabled explicitly per project through the
``nestingInListModule`` boolean extension configuration option
(disabled: ``0``).

What identifies a row's place in the list
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

As throughout Grid Elements, it is not ``colPos`` that the nested list
is built from, see :ref:`DataModel`. Two separate fields do that work,
and they stay distinct here exactly as they do everywhere else:
``tx_gridelements_container``, stored on the child, is the parent
Grid Element relation and is what determines which container a row is
nested under; ``tx_gridelements_columns``, also stored on the child,
is the structural cell/column assignment within that container and is
what groups a container's children by cell once they are nested under
it. ``colPos`` still plays a role here, but only its own Core role:
every grid child carries ``colPos = -1`` regardless of which container
or cell it belongs to, and that sentinel is what the query filtering
below uses to decide whether a row is a page-level, top-of-list record
or only appears nested inside its container's expanded row.

How the three pieces fit together
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Once ``nestingInListModule`` is enabled, three separate mechanisms
combine to produce the nested list, none of them sufficient on its
own:

.. list-table::
   :header-rows: 1
   :widths: 30 70

   * - Mechanism
     - Role
   * - ``ModifyDatabaseQueryForRecordListingListener``
       (PSR-14 event listener)
     - Adds ``colPos != -1`` to the List module's base query for
       ``tt_content``, so grid children do not also appear as their
       own top-level rows.
   * - ``Xclass\DatabaseRecordList`` /
       ``Xclass\DatabaseRecordList12``
     - Renders each ``gridelements_pi1`` container with an expand and
       collapse control (``contentCollapseIcon()``, implemented on
       ``Hooks\DatabaseRecordList`` and called directly, not through a
       hook point) and, when expanded, lists its children indented
       beneath it, grouped by ``tx_gridelements_columns``.
   * - ``ModifyRecordListElementDataEvent`` /
       ``ModifyRecordListElementDataListener``
     - Dispatched per row by the XCLASS to attach the data the
       template needs to know a row is expandable and to find its
       children: ``_CONTAINER_COLUMNS_``, ``_EXPANDABLE_``,
       ``_EXPAND_ID_``, ``_EXPAND_TABLE_``, ``_LEVEL_`` and
       ``_CHILDREN_``. ``_CHILDREN_`` itself is fetched through
       ``GridElementsHelper::getChildren()``, the same
       ``tx_gridelements_container`` query used everywhere else in
       Grid Elements, see :ref:`DataModel`.

Why this needs an XCLASS at all
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

The query filtering and the per-row data are both handled through
PSR-14 events, consistent with the rest of Grid Elements, see
:ref:`DeveloperReferenceExtensionPoints`. Building the actual nested,
expandable row output is not: TYPO3 Core's List module does not
currently expose an event or hook for restructuring how rows are
grouped and rendered relative to each other, only for adjusting query
conditions, header columns and per-row actions. XCLASSing
``DatabaseRecordList`` is how Grid Elements reaches that part of the
rendering; it is the one place in the codebase this happens, and it
only takes effect for a project that has opted in, see
:ref:`DeveloperReferenceExtensionPoints` for the caution around
extending it further.
