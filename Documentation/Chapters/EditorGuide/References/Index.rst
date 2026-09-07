.. include:: /Includes.rst.txt

.. _EditorGuideReferences:

==========
References
==========

A reference lets an existing content record appear in another
structure without being duplicated as an independent record. The
content shown is the same content, wherever it appears; there is only
ever one record to edit.

Creating a reference
^^^^^^^^^^^^^^^^^^^^^

Referencing starts the same way copying does: an element is put on the
clipboard from its context menu, on this page or on another one. From
there, an editor has two ways to bring it in as a reference rather
than a copy:

* From an existing element's own context menu, once something is on
  the clipboard in copy mode, *Paste reference after* appears right
  next to the ordinary *Paste after* action.
* From an empty cell or column's *create new content element* control,
  a *Paste* dialog appears when something is on the clipboard,
  offering *Paste* and *Paste reference* side by side.

Either way, the target cell or column, including a Grid Element's own
cell, is honored, so a reference lands exactly where an ordinary paste
would.

What is actually referenced
^^^^^^^^^^^^^^^^^^^^^^^^^^^^

A reference is its own, small content record. It does not hold a copy
of the referenced content; it holds a pointer to it. What an editor
sees in the Page Module is the referenced content's own preview,
rendered in place and marked with a border so it reads clearly as a
reference rather than as the content element itself. Editing the
referenced content is done by opening the original record, wherever it
lives; the reference itself has nothing of its own to edit beyond
where it points.

Because a reference is a pointer, not a duplicate, editing the
original updates every place that references it. This is the
meaningful difference from a normal copy: a copy is independent from
the moment it is created, while a reference stays connected to a
single source of truth.

Creating a reference requires the same backend-user permission TYPO3
uses for the *shortcut* content element type, since a reference is
implemented as one. See the permissions section of :ref:`Restrictions`
for how that is enforced; an editor without that permission simply
does not see the *Paste reference* choice.

Nesting and restrictions
^^^^^^^^^^^^^^^^^^^^^^^^^

A reference can be placed inside a Grid Element's cell exactly like
any other content element, including a cell of a nested Grid Element.
Because a reference is technically a *shortcut* content element, the
column or cell restrictions that would allow or disallow a shortcut
element apply to whether a reference can be pasted there at all, the
same way any other restriction narrows down what a cell accepts, see
:ref:`Restrictions`.
