.. include:: /Includes.rst.txt

.. _EditorGuideDragInWizard:

===============
Drag-In Wizard
===============

The Drag-In Wizard is Grid Elements' own direct-authoring tool. It
puts the same element types that the New Content Element Wizard offers
directly inside the Page Module, as a panel an editor can drag items
out of, and turns creating an element into a single motion: pick an
item, drag it into the structural area it belongs in, and it exists
there, already placed, the moment it is dropped.

This is the practical core of :ref:`Structure-first Authoring
<CoreConcepts>`. An editor builds a page's structure and fills it with
content in the same place, in one continuous act, rather than
switching between a separate creation dialog and the layout it is
building.

Opening the wizard
^^^^^^^^^^^^^^^^^^^

A toggle button next to the Page Module's own *Create new content
element* button opens and closes the wizard panel. Once open, it stays
available while an editor keeps working, so it can be left open for a
whole session of building out a page rather than reopened for every
element.

What the wizard presents
^^^^^^^^^^^^^^^^^^^^^^^^^

The panel lists the same categories and element types the New Content
Element Wizard would offer for the current page, grouped the same way,
with the same icons and descriptions. Nothing needs to be configured
twice: the Drag-In Wizard is a different way of reaching the same set
of choices, not a separate catalogue to maintain.

What the panel actually shows is pre-filtered before any drag begins.
It examines every column currently rendered on the page, page-level
Backend Layout columns and any Grid Element's own cells alike, and
offers an element type only if at least one of those columns would
accept it under its restriction configuration, see :ref:`Restrictions`.
An element type that no column on the current page allows does not
appear in the panel at all; an editor is never offered something that
has nowhere to go on the page being edited. This pre-filtering of what
is offered is a separate mechanism from what happens once a drag is
already in progress, described below.

Creating an element by dragging it in
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Dragging an item out of the panel highlights the structural areas on
the page that would actually accept it, exactly as ordinary
:ref:`EditorGuideDragAndDrop` highlights valid targets for an existing
element. Dropping it there creates a new element of that type,
already placed in that column, in that Grid Element's cell, or nested
inside another Grid Element, wherever it was dropped. Selection,
creation and placement happen as one interaction, so there is no
intermediate step where the new element exists but has not been
placed yet.

How restrictions shape what is offered
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Restrictions act at two separate points, not just one. Before a drag
starts, they decide which element types appear in the panel at all,
see above. Once a drag is in progress, the same restriction
configuration decides which columns and cells light up as valid drop
targets for the specific item being dragged: only the columns and
cells that would actually accept that item's type light up. A cell
restricted to a particular set of content types, or one that has
already reached its maximum number of elements, simply does not
become a drop target for anything it would reject. This is the same
restriction configuration described in :ref:`Restrictions`, applied
here as it is
applied to any other way of placing content.

Why one interaction instead of two
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Without the Drag-In Wizard, adding content to a specific cell means
opening the New Content Element Wizard from that cell's own *create
new content element* control, choosing a type, and waiting for the
dialog to return to the page. The Drag-In Wizard removes that
detour: the wizard is already open, alongside the structure it will
fill, and placing an element is the same motion as choosing it. Used
consistently, it covers ordinary element creation directly, so an
editor building out a page's structure does not need to open the New
Content Element Wizard, described next, for that part of the work.
