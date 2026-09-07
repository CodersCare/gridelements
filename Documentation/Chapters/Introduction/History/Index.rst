.. include:: /Includes.rst.txt

.. _History:

===============================
History and Design Background
===============================

Grid Elements did not set out to add a layout feature to content
elements. It continued a specific line of thinking about structural
authoring that began with the Grid View, Grid Wizard and Backend
Layout concepts developed in 2009. Part of that work later entered
TYPO3 Core at page level. That continuity is why the extension models
structure the way it does, through a dedicated parent-child relation
and per-cell restrictions rather than through page placement, see
:ref:`DataModel` and :ref:`Restrictions`, and why nesting was never
treated as a special case, see :ref:`Nesting`.

The line runs as follows. Work at the TYPO3 UX Week in 2009 produced
Grid View, an approach to describing a page's layout as an explicit
structure of rows and columns rather than a fixed template, together
with the Grid Wizard, the visual tool used to define that structure.
Part of that work was carried forward into TYPO3 Core with version
4.5, as Backend Layouts, giving editors a structural, point-and-click
way to define how a page's content is arranged.

The same line of thought was then carried forward again, from page
level to content level. Grid Elements emerged in 2011 as that
continuation, applying the same structural approach to individual
content elements instead of whole pages, with rows, columns, cells and
restrictions defining their structure and behavior. Its own Grid
Wizard carries the visual structure-definition concept forward to
content-element level. Grid Elements therefore represents a direct
continuation of the same design work that had already shaped Backend
Layouts, carried forward from page-level structure to
content-element-level structure.

For the fuller narrative behind this, see the Coders.Care article
`Grid Elements: The Idea <https://coders.care/blog/article/grid-elements-the-idea>`_.
