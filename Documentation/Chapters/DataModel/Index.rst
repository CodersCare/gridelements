.. include:: /Includes.rst.txt

.. _DataModel:

==========
Data Model
==========

A Grid Element is a regular ``tt_content`` record with
``CType = gridelements_pi1``. What makes it a *grid* is that it can act
as a container for other ``tt_content`` records, its children, which
are themselves ordinary content elements and may in turn be Grid
Elements (see :ref:`Nesting`).

This page describes how that container/child structure is actually
stored: which fields exist, which one carries the relation itself, and
why ``colPos`` is still involved even though it is not that relation.
:ref:`ParentChildIrre` builds on this to explain how TYPO3's IRRE
(Inline Relational Record Editing) and the DataHandler cooperate to
keep these fields consistent while editors work.

Overview
^^^^^^^^

.. code-block:: text

   Grid Element (tt_content, CType = gridelements_pi1)
       |
       | tx_gridelements_children (IRRE, parent side)
       v
   Child tt_content
       tx_gridelements_container = <parent uid>   -- the relation
       tx_gridelements_columns   = <cell number>  -- position inside the parent's grid
       colPos                    = -1             -- sentinel, not the relation
       backupColPos                               -- saved colPos while unused

.. list-table::
   :header-rows: 1
   :widths: 28 14 58

   * - Field
     - Stored on
     - Role
   * - ``tx_gridelements_container``
     - child
     - The parent-child relation. Holds the ``uid`` of the container record.
   * - ``tx_gridelements_columns``
     - child
     - Which cell of the container's own grid layout the child occupies.
   * - ``tx_gridelements_children``
     - container
     - IRRE parent-side declaration plus a maintained child count. Not an
       independent relation, see below.
   * - ``colPos``
     - both
     - TYPO3 Core placement and compatibility state, not the Grid
       Elements relation. Carries sentinel values, see below.
   * - ``backupColPos``
     - both
     - The ``colPos`` value to restore once an element's current column
       becomes available again.

The relation: ``tx_gridelements_container``
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

``tx_gridelements_container`` is a ``select`` field on the child record
that points, by ``uid``, at its parent Grid Element. This is the parent-child
relation. Every query that needs to find the children of a given container,
including the frontend rendering pipeline in :ref:`Nesting`, filters on
this field:

.. code-block:: sql

   -- GridChildrenProcessor::process(), simplified
   SELECT * FROM tt_content WHERE tx_gridelements_container = <container uid>

Its TCA (``Configuration/TCA/Overrides/tt_content.php``) restricts the
selectable containers to ``tt_content`` records with
``CType = gridelements_pi1`` on the same page and in a matching language, and
excludes the record itself and anything the record is already the
container of, which prevents the most direct kind of self-reference at
the form level (the full cycle guard is described in
:ref:`ParentChildIrre`).

The cell: ``tx_gridelements_columns``
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

``tx_gridelements_columns`` identifies which cell of the *container's
own* grid layout the child sits in. Its value corresponds to a
``colPos`` key defined inside that layout's own grid configuration
(``rows.<n>.columns.<m>.colPos``, see the Grid TS Syntax reference), not
to the child's own page-level ``colPos``. A child's position is
therefore fully described by the pair (``tx_gridelements_container``,
``tx_gridelements_columns``): which container, and which cell of that
container.

The parent side: ``tx_gridelements_children``
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

``tx_gridelements_children`` is declared in TCA as an ``inline`` field
(``foreign_table = tt_content``,
``foreign_field = tx_gridelements_container``). This is what makes the container's edit
form present and manage its children through TYPO3's IRRE machinery, see
:ref:`ParentChildIrre` for how that works.

The field is also a stored integer column, and it is easy to read that
as a second, parent-side copy of the relation. It is not. TYPO3's IRRE
already determines a container's children purely by querying
``tx_gridelements_container``, exactly as shown above; no code in Grid
Elements ever reads which children exist from
``tx_gridelements_children``. What the stored integer holds is a
maintained count, kept in sync by
``AbstractDataHandler::doGridContainerUpdate()`` every time a child is
added to, removed from, or moved out of a container. It exists so the
count is available without a separate query, not as a source of truth.
If it ever drifts out of sync with the actual number of children, see
:ref:`DataConsistencyTools`.

``colPos`` and its sentinel values
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

``colPos`` does not establish the Grid Elements parent-child relation.
It is TYPO3 Core's own placement field, and Grid Elements keeps writing
it for every content element, container children included, because
Core and its Page module, list module, and workspace preview all group
``tt_content`` by ``colPos`` and need a value there regardless of
whether an element also happens to be a grid child. The field's
underlying database column had to change from an unsigned integer to a
signed one to make this possible at all, since Grid Elements relies on
two negative sentinel values:

.. list-table::
   :header-rows: 1
   :widths: 12 88

   * - Value
     - Meaning
   * - ``>= 0``
     - Page-level placement. The element is placed in a column of the
       page-level Backend Layout and is not a child of another Grid
       Element.
   * - ``-1``
     - Grid element column. Set on every child of every container,
       regardless of which container or which cell,
       ``tx_gridelements_columns`` carries that detail instead.
   * - ``-2``
     - Non-used elements column. The element currently has no valid
       column, typically because a layout change removed the column it
       used to occupy. Surfaced in the backend by giving a page's own
       backend layout a column configured with ``colPos = -2``.

**This column type change must never be reverted by a later upgrade
script or manual schema fix.** Reverting it back to an unsigned integer
means the negative sentinels can no longer be stored. This does not
detach a child from its Grid Element: ``tx_gridelements_container``,
the actual parent-child relation, is untouched by a ``colPos`` schema
change. What breaks is TYPO3 Core's own placement handling and backend
presentation, which read ``colPos`` directly and, unable to see the
sentinel value, interpret the affected record incorrectly, typically
as if it were an ordinary page-level element rather than a grid child.
If this happens, see :ref:`DataConsistencyTools` for the repair tool,
which restores the ``-1`` sentinel for any row whose
``tx_gridelements_container`` relation is still intact.

``backupColPos`` exists to make the ``-2`` state reversible. When
``AfterDatabaseOperations::setUnusedElements()`` detects that a layout
change has removed an element's column, it moves the element to
``colPos = -2`` and saves the previous value in ``backupColPos``. When a
later layout change makes a matching column available again, the two
values are swapped back, restoring the element to its original
position without editor intervention.

A related case is a child being detached from its container entirely
(``tx_gridelements_container`` reset to ``0`` while ``colPos`` was
``-1``). Rather than defaulting the freed element to column 0,
``PreProcessFieldArray::checkForRootColumn()`` walks up the chain of
former containers to find the ``colPos`` of the outermost page-level
ancestor, so the element reappears in the same major page column it
structurally originated from.

One container-record field and one separate layout table are worth
naming here, since they appear throughout this section: the
container's own selected grid layout is stored in
``tt_content.tx_gridelements_backend_layout`` as a string identifier.
Database-based layout definitions are stored separately in the
``tx_gridelements_backend_layout`` table, which contains their title,
configuration and icons. Layouts may also be defined through TSconfig,
so the value in ``tt_content.tx_gridelements_backend_layout`` is a
layout identifier rather than an unconditional reference to a row in
that table.

.. toctree::
   :maxdepth: 1
   :titlesonly:
   :hidden:

   ParentChildIrre/Index
   Nesting/Index
   Restrictions/Index
   DataConsistencyTools/Index
