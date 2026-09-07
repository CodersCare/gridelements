.. include:: /Includes.rst.txt

.. _ParentChildIrre:

===============================
Parent-Child Relations and IRRE
===============================

:ref:`DataModel` describes which fields exist and what each one means.
This page describes how those fields are kept consistent while editors
work: TYPO3's IRRE (Inline Relational Record Editing) infrastructure on
the backend-editing side, and the ``DataHandler`` hook pipeline that
runs on every save, move, copy, and delete.

IRRE in TYPO3, briefly
^^^^^^^^^^^^^^^^^^^^^^^

IRRE is TYPO3 Core's mechanism for editing a one-to-many relation
inline, inside the parent record's own edit form. With
``foreign_table`` and ``foreign_field``, the relation is stored
directly through a field on the child record rather than through an
intermediate ``MM`` table. A TCA field of ``type => inline`` with
``foreign_table`` and ``foreign_field`` tells FormEngine: this
parent's children are whichever rows in ``foreign_table`` have
``foreign_field`` pointing back at this record's ``uid``.

Grid Elements uses exactly this configuration, and nothing more exotic.
``tx_gridelements_children`` is that inline field, with
``foreign_table = tt_content`` and
``foreign_field = tx_gridelements_container``. This matters
architecturally, because it means the backend editing widget and the
frontend data-fetching path described in :ref:`Nesting` resolve a
container's children through the same underlying relation. There is no
separate storage format for "children as edited in the backend" versus
"children as rendered on the frontend".

Backend editing behavior
^^^^^^^^^^^^^^^^^^^^^^^^^

A few TCA settings on ``tx_gridelements_children`` shape how the inline
widget behaves, worth naming because they explain what an editor sees:

* ``appearance.enabledControls`` disables the widget's own ``new`` and
  ``dragdrop`` controls. Children are not created by clicking "new"
  inside the container's form, they are created through the New
  Content Element wizard or dragged in from the page module, both of
  which write ``tx_gridelements_container`` directly.
* ``overrideChildTca`` defaults a newly inline-created child's
  ``colPos`` to ``-1``, matching the sentinel described on
  :ref:`DataModel`.
* ``foreign_sortby = sorting`` means children use the ordinary
  ``tt_content`` sorting field. There is no separate Grid Elements
  sort order to keep in sync.

The DataHandler pipeline
^^^^^^^^^^^^^^^^^^^^^^^^^

Persistent consistency is not left to TCA and IRRE alone. Grid Elements
hooks into TYPO3's ``DataHandler`` at three points, routed through
``Classes/Hooks/DataHandler.php`` and implemented in
``Classes/DataHandler/``:

.. list-table::
   :header-rows: 1
   :widths: 30 70

   * - Hook
     - Responsibility
   * - ``processDatamap_preProcessFieldArray``
       (``PreProcessFieldArray``)
     - Runs before a ``tt_content``/``pages`` save is persisted. Keeps
       ``colPos``, ``tx_gridelements_container`` and
       ``tx_gridelements_columns`` consistent with each other, guards
       against container cycles and dangerous shortcut references
       before they can be written, and keeps a child's language in
       sync with its container's language.
   * - ``processDatamap_afterDatabaseOperations``
       (``AfterDatabaseOperations``)
     - Runs after the save. Unless automatic unused-column correction
       has been disabled in the extension configuration, it
       re-evaluates affected elements against a changed grid or
       backend layout, moving elements to or from the "unused
       elements" state described on :ref:`DataModel`, and propagates
       relation changes to translated copies via
       ``checkAndUpdateTranslatedElements()``.
   * - ``processCmdmap`` (``ProcessCmdmap``)
     - Runs on copy/move commands. Implements "Paste as Reference" by
       creating a ``CType = shortcut`` record instead of duplicating
       content, and keeps container child counts in sync on copy, move
       and delete.

A separate pair of hooks, ``processDatamap_beforeStart`` and
``processCmdmap_beforeStart``, gate these same operations against a
column's allowed/disallowed/maxitems configuration before any of the
above runs. That is a restriction-enforcement concern, not a
relation-consistency one, and is described on :ref:`Restrictions`.

Keeping counts and translations in sync
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Whenever a save or command changes which container an element belongs
to, ``AbstractDataHandler::doGridContainerUpdate()`` adjusts the
affected containers' ``tx_gridelements_children`` counters (see
:ref:`DataModel` for what that counter means). It is called from the
preprocessing and command-map handling paths as needed when children
are moved, copied, attached or detached.

Translations need their own synchronization, because IRRE's
``foreign_field`` relation does not automatically re-parent a
translated child to a translated container. ``checkAndUpdateTranslatedElements()``
propagates a container or child's relation change to its translated
counterparts, re-pointing them at the matching container in their own
language where one exists, and clearing the relation where it does
not, rather than leaving a translated child silently attached to a
different language's container.

Cycle protection
^^^^^^^^^^^^^^^^^

A container becoming its own direct or indirect container would make
both backend editing and frontend rendering recurse forever.
``Classes/Helper/ContainerCycleGuard.php`` prevents this at two points:

* **Direct or indirect self-reference.** ``getContainerAncestorChain()``
  walks ``tx_gridelements_container`` upward from a candidate container
  to the root, and ``wouldCreateContainerCycle()`` rejects an assignment
  that would place a container inside its own ancestor chain. This runs
  both on a direct field edit (``PreProcessFieldArray::setFieldEntries()``)
  and on a drag-and-drop or cut/paste move
  (``Hooks\DataHandler::processCmdmap_beforeStart()``).
* **Shortcut references into an ancestor.** A ``CType = shortcut``
  element referencing one of its own current or prospective ancestor
  containers would create the same kind of rendering loop indirectly.
  ``shortcutReferencesForbiddenContainer()`` protects individual
  shortcut edits and moves. When an entire Grid Element subtree is
  relocated, ``subtreeReferencesForbiddenContainer()`` scans its
  descendants for shortcut references that would become cyclic in the
  new ancestor chain.

A blocked cmdmap operation is rejected and removed from the command
map. During direct datamap editing, the offending relation or
reference fields are removed from the incoming field array so the
invalid relationship cannot be persisted. In both cases a flash
message is shown to the editor.