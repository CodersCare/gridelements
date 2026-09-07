.. include:: /Includes.rst.txt

.. _EditorGuideDragAndDrop:

=============
Drag and Drop
=============

Drag and drop moves an element that already exists. It is TYPO3's own
Page Module mechanism: grab a content element by its header and drop
it where it should go. Grid Elements extends this mechanism so it also
works across a grid's own cells and into and out of nested structures,
honoring the same restrictions that govern any other way of placing
content there.

This is a different action from the :ref:`EditorGuideDragInWizard`.
Drag and drop always moves or copies a content element that is already
on the page. Creating a brand new element by dragging its type
directly into a structural area is the Drag-In Wizard's job, not
ordinary drag and drop, even though both interactions look similar at
a glance.

Moving within a Grid Element
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Dragging a child from one cell of a Grid Element to another cell of
the same Grid Element relocates it there. Only cells that would
actually accept the dragged element highlight as valid targets while
dragging; a cell whose restrictions rule out that element's type
simply never lights up, so an invalid drop is not offered in the first
place rather than rejected afterwards.

Moving between structural areas
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

The same drag works between a page column and a Grid Element's cell in
either direction, and between the cells of two different Grid
Elements on the same page, including cells of a Grid Element nested
inside another one. Dropping an element into a plain page column
detaches it from any Grid Element it used to belong to; it becomes an
ordinary page-level element again, placed in that column like any
other. See :ref:`DataModel` if the underlying field changes that make
this possible are of interest.

Copy instead of move
^^^^^^^^^^^^^^^^^^^^^

Holding down Ctrl while dropping (Option on macOS) copies the element
to the target cell instead of moving it, leaving the original in
place. This is the same modifier TYPO3 uses for drag and drop
elsewhere in the Page Module.

Restrictions decide what is offered
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

A cell's ``allowed``, ``disallowed`` and ``maxitems`` configuration is
checked while dragging, not only after dropping. Cells that would
reject the dragged element's type, or that have already reached their
maximum number of elements, do not become active drop targets at all.
See :ref:`Restrictions` for how these rules are configured and how
they are enforced again, independently, once a drop is submitted.
