.. include:: /Includes.rst.txt

.. _CoreConcepts:

=============
Core Concepts
=============

This page introduces the vocabulary used throughout the rest of the
documentation, and the authoring model that vocabulary serves. It
assumes no prior reading; the technical pages under Data Model &
Architecture pick up where this one ends.

Vocabulary
^^^^^^^^^^

.. list-table::
   :header-rows: 1
   :widths: 22 78

   * - Term
     - Meaning
   * - Grid Element
     - A content element that defines a grid-based structure in which
       other content elements can be organized.
   * - Container
     - A Grid Element in its role as parent. The term appears
       throughout the technical pages for the record on the parent
       side of a relation.
   * - Child
     - A content element placed inside a container. A child can be any
       content element type, including another Grid Element.
   * - Row / Column
     - The grid structure a layout defines, the same concept as a
       page's Backend Layout, applied to a container instead of a
       page.
   * - Cell
     - A single position within that grid, where a row and column
       meet. A container's children each occupy one cell.
   * - Nesting
     - Placement of a Grid Element inside another Grid Element, adding
       another structural level. No separate setting is required to
       enable structural nesting, see :ref:`Nesting`.
   * - Restriction
     - Configuration on a cell that governs what may be placed there
       and how many elements are allowed, see :ref:`Restrictions`.
   * - Grid Wizard
     - The visual tool for defining a grid's row and column structure.
       See below.
   * - New Content Element Wizard
     - TYPO3 Core's standard tool for selecting and creating a content
       element. See below.
   * - Drag-In Wizard
     - Grid Elements' tool for creating and placing a content element
       directly in its structural area, in one interaction. See below.

Structure-first Authoring
^^^^^^^^^^^^^^^^^^^^^^^^^^

Structure is not merely where content is placed. Structure can be part
of what content means.

With Grid Elements, an editor can create a structure, a container with
its rows, columns and cells, before any content values exist inside
it. The structure describes a hierarchy and a grouping, which elements
belong together and in what arrangement, before it describes any
specific content.

That structure can also carry more than arrangement. A cell can
declare which content types belong in it and how many, giving it a
functional role rather than just a position, see :ref:`Restrictions`.
None of this depends on how the structure is rendered. The parent-child
relationships and structural assignments created while editing remain
available to frontend processing and templates, while restrictions
govern what editors are allowed to create and place in that structure.
See :ref:`DataModel` for how the persistent structural relationships
are stored, and :ref:`ParentChildIrre` for how the parent-child
relation is maintained as editors work. Structure-first Authoring is
the description of that authoring model: relationships, roles and
constraints defined through the structure itself, available to be
filled with content, rather than inferred afterwards from where
elements happen to sit.

Defining structure, then filling it
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Grid Elements provides three related but distinct tools that put
Structure-first Authoring into practice. They are easy to conflate
because all three appear while an editor is building a page.

The Grid Wizard belongs to the structure-definition layer. It is a
visual tool for defining a grid's rows and columns and how they
relate, the same concept TYPO3 Core provides for page-level Backend
Layouts, carried forward by Grid Elements to content-element level.
Using it does not place any content; it defines the structural area
content will later go into.

The New Content Element Wizard is TYPO3 Core's standard mechanism for
selecting and creating a content element. Grid Elements integrates
with it like any other content element type.

The Drag-In Wizard is Grid Elements' own direct-authoring tool. It
exposes suitable elements right inside the page layout and lets an
editor create one by dragging it straight into the structural area it
belongs in, combining selection, creation and placement in a single
interaction. Used consistently, it covers that workflow directly,
without needing to open the New Content Element Wizard separately, so
an editor builds structure and fills it with content in the same
place, in one continuous act of authoring rather than two separate
steps.
