.. include:: /Includes.rst.txt

.. _EditorGuide:

============
Editor Guide
============

The Page Module is where a Grid Element's structure becomes something
an editor actually works with. Its rows, columns and cells show up as
real areas on the page, and each one behaves like any other column an
editor already knows how to use: content can be created in it, moved
into it, or removed from it, right where it belongs.

This guide covers that everyday work. An editor can place content
directly in the structural area it belongs to, move existing content
between areas, nest one Grid Element inside another to build more
elaborate layouts, and bring in content that already exists elsewhere
by referencing it instead of duplicating it. Restrictions configured
on a cell narrow down what can be placed there, so the structure
itself steers an editor toward a valid result instead of leaving the
rules to memory.

Two tools cover most day-to-day content creation inside a grid.
TYPO3's standard New Content Element Wizard remains available, exactly
as on any page column. Grid Elements also provides its own Drag-In
Wizard, which lets an editor pick an element and place it in one
motion, without a detour through a separate dialog. Used consistently,
the Drag-In Wizard covers that workflow directly.

Where to go next
^^^^^^^^^^^^^^^^^

.. list-table::
   :header-rows: 0
   :widths: 30 70

   * - :ref:`EditorGuidePageModule`
     - How a Grid Element's structure appears in the Page Module, and
       what an editor can do with it directly.
   * - :ref:`EditorGuideDragAndDrop`
     - Moving content that already exists between columns, cells and
       nested structures.
   * - :ref:`EditorGuideDragInWizard`
     - Creating and placing a new element in a single interaction,
       directly inside the structural area it belongs to.
   * - :ref:`EditorGuideNewContentElementWizard`
     - TYPO3's standard element-creation dialog, and how Grid Elements
       fits into it.
   * - :ref:`EditorGuideReferences`
     - Letting an existing content record appear in more than one
       structure without duplicating it.
   * - :ref:`EditorGuideLocalization`
     - What happens to a Grid Element and its children when a page is
       translated.

.. toctree::
   :maxdepth: 1
   :titlesonly:
   :hidden:

   PageModule/Index
   DragAndDrop/Index
   DragInWizard/Index
   NewContentElementWizard/Index
   References/Index
   Localization/Index
