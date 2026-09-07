.. include:: /Includes.rst.txt

.. _Introduction:

============
Introduction
============

Grid Elements carries forward the structural authoring concept of TYPO3
Backend Layouts from page level into content structures. A Grid
Element is a content element that defines an explicit structural area
in which editors place other content elements, including further Grid
Elements. Grid definitions themselves are configured with TSconfig,
the same configuration language used for Backend Layouts, which keeps
them reusable, maintainable and suitable for version control alongside
the rest of a project's configuration.

What it contains and organizes
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

A Grid Element's children are ordinary ``tt_content`` records related
to it through a dedicated parent-child relation, not through their
position on the page, see :ref:`DataModel` for exactly how that is
stored. Because a child can be any content element type, including
another Grid Element, Grid Elements can be nested structurally across
multiple levels without a separate setting being required to enable
nesting, see :ref:`Nesting`.

More than visual placement
^^^^^^^^^^^^^^^^^^^^^^^^^^^

These structural areas can express hierarchy, grouping, functional
roles, restrictions and context, not only visual arrangement. A cell
can declare which content types belong there and how many, or whether
a given layout may be nested inside another at all. These rules are
applied by the backend authoring workflow when editors create, place
and move content, see :ref:`Restrictions`. Structure therefore does
more than determine where content appears; it can become part of what
that content means and how it behaves. :ref:`CoreConcepts` describes
this authoring model, Structure-first Authoring, in more depth.

Authoring in the backend
^^^^^^^^^^^^^^^^^^^^^^^^^

The Grid Wizard is TYPO3 Core's visual tool for defining a page's
Backend Layout structure. Grid Elements provides its own Grid Wizard,
carrying that same structural-definition concept to content-element
level and allowing a Grid Element's row and column structure to be
defined visually.

Editors add content to that structure with drag and drop and TYPO3's
New Content Element Wizard, or more directly with the sophisticated
Drag-In Wizard. It exposes suitable elements right inside the page
layout and combines their selection, creation and placement in a
single drag-in interaction. Used consistently, the Drag-In Wizard
covers that workflow directly without requiring the New Content
Element Wizard to be opened separately, letting an editor build
structure and content together in place. Existing content can also be
referenced into a structure instead of duplicated, letting the same
content participate in more than one structure while remaining a
single record.

Where Grid Elements comes from
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Grid Elements continues a line of structural authoring work that
began in 2009 with the Grid View, Grid Wizard and Backend Layout
concepts. Part of that page-level approach entered TYPO3 Core with
TYPO3 4.5, while Grid Elements carried the broader concept forward to
content-element level. See :ref:`History` for that background, and
the Coders.Care article
`Grid Elements: The Idea <https://coders.care/blog/article/grid-elements-the-idea>`_
for the fuller narrative.

How Grid Elements relates to other extensions
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Grid Elements is sometimes discussed as interchangeable with
narrower, container-only extensions such as ``b13/container``
(EXT:container). It is not: container behavior is one part of the
broader Structure-first Authoring model described above, not the
whole of it. See :ref:`GridElementsAndContainer` for a direct,
technical comparison.

.. toctree::
   :maxdepth: 1
   :titlesonly:
   :hidden:

   CoreConcepts/Index
   History/Index
   GridElementsAndContainer/Index
