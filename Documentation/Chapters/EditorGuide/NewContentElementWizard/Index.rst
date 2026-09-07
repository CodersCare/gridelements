.. include:: /Includes.rst.txt

.. _EditorGuideNewContentElementWizard:

============================
New Content Element Wizard
============================

The New Content Element Wizard is TYPO3 Core's standard dialog for
choosing and creating a content element. It opens from the *create new
content element* control on a page column or on a Grid Element's own
cell, and remains exactly what it has always been: a categorized list
of element types an editor picks from.

Grid Elements integrates with this dialog rather than replacing it.

Where Grid Elements appears in it
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Opened from a column or cell that allows Grid Elements at all, the
wizard's list includes the Grid Element layouts available there,
alongside TYPO3's own content element types and any installed plugins.
Picking one creates a new Grid Element of that layout, already placed
in the column or cell the wizard was opened from.

How structural context narrows the choices
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

The wizard's list is not the same everywhere. Which content element
types, plugins and Grid Element layouts appear depends on the
restrictions configured for the column or cell the wizard was opened
from. An area limited to a small set of layouts, or one that
disallows Grid Elements entirely, simply does not offer the choices it
disallows. This is the same restriction configuration described in
:ref:`Restrictions`, applied here as it is applied everywhere else an
editor creates or places content.

Relationship to the Drag-In Wizard
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

The New Content Element Wizard and the :ref:`EditorGuideDragInWizard`
are two different ways of reaching the same choices, not competing
workflows. The New Content Element Wizard remains a valid, ordinary
way to create content, opened from wherever it is needed, one element
at a time. An editor who uses the Drag-In Wizard consistently while
building out a page's structure may simply never need to open this
dialog for that part of the work, since the Drag-In Wizard covers
element creation directly from the same panel. Both remain available
side by side; neither is deprecated in favor of the other.
