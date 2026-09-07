.. include:: /Includes.rst.txt

.. _Nesting:

=======
Nesting
=======

Nesting is part of Grid Elements' structural model, not a syntax trick
layered on top of it. A child of a Grid Element is an ordinary
``tt_content`` record (see :ref:`DataModel`), and nothing in the data
model restricts what ``CType`` that record may have, including
``gridelements_pi1`` itself. A container's child can therefore be a
container of its own, with its own children, to whatever depth an
editor builds by placing a Grid Element inside another Grid Element's
cell through the New Content Element wizard or drag-and-drop. No
setting turns nesting "on", and no setting caps how deep it goes: the
only structural limit is :ref:`cycle protection <ParentChildIrre>`,
which prevents a container from becoming its own ancestor. It does not
limit legitimate depth.

A layout-level ``top_level_layout`` flag can exclude that specific
layout from being nested inside another container at all. Separately,
per-column ``allowed``/``disallowed``/``maxitems`` restrictions can
limit which layouts or how many elements a specific cell accepts.
These are authoring restrictions described in full on
:ref:`Restrictions`, not general depth limits on nesting as a
mechanism.

It is easy to conflate three distinct things when reading rendering
code or templates, so this page keeps them separate.

Structural nesting
^^^^^^^^^^^^^^^^^^^

This is the persisted state described above: a chain of
``tx_gridelements_container`` relations, unconditional and generic. It
exists in the database the moment an editor nests one Grid Element
inside another, independent of how the page is ever rendered.

The DataProcessor's ``recursive`` option
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

``GridChildrenProcessor`` is the recommended way to fetch a container's
children for Fluid rendering (see the DataProcessing chapter). Its
``recursive`` option controls how many additional levels of children it
pre-fetches within a single processing pass:

.. code-block:: php

   // GridChildrenProcessor::processChildRecord(), simplified
   if ($options['recursive'] && $record['CType'] === 'gridelements_pi1') {
       // re-invoke the same processor on this child, recursive - 1,
       // gathering its children into the same data structure
   }

With ``recursive = 0`` (the default), a nested container's own
children are simply not gathered as part of this processing pass. That
is a data-eagerness setting: it changes how many levels of the tree
arrive pre-assembled in one processing chain and when deeper containers
are processed. It is not a structural limit, and it does not mean
nested containers below that point cannot be rendered, see the next
section.

Fluid template recursion
^^^^^^^^^^^^^^^^^^^^^^^^^

Rendering a container's children in Fluid works through recursion
between two partials. ``Child.html`` checks whether pre-fetched
``children`` data is present for the current record:

.. code-block:: html

   <f:if condition="{children}">
       <f:then>
           <f:render partial="Container" arguments="{_all}" optional="true"/>
       </f:then>
       <f:else>
           <f:cObject typoscriptObjectPath="tt_content.{data.CType}" data="{data}" table="tt_content" />
       </f:else>
   </f:if>

If ``children`` was pre-fetched (because ``recursive`` was greater than
0, or because this is the container currently being rendered from
scratch), the partial renders straight back into ``Container.html``,
which walks down through ``Rows``/``Columns`` into ``Child`` again.

If it was not pre-fetched, the child is instead rendered through
TYPO3's standard ``tt_content.{CType}`` dispatch. For a nested
``gridelements_pi1`` record, that re-enters the container's own
TypoScript rendering setup from scratch, including its own
``GridChildrenProcessor`` call for its own children. The structure
still renders correctly and completely; only the fetching strategy
differs, one combined pre-fetched pass versus a fresh per-element
render. This is what keeps ``recursive = 0`` from ever capping how deep
a page actually nests: it changes *how* deeper levels are fetched, not
*whether* they exist or render.

The legacy TypoScript rendering path (``Classes/Plugin/Gridelements.php``,
deprecated and not recommended for new projects) has its own separate
recursive mechanism, based on ``COA``/``TEMPLATE`` TypoScript objects
rather than Fluid partials. It is not covered further here; the
Rendering section documents the current, recommended DataProcessing and
Fluid approach described above.

Because nesting is unconditional at the data-model level, structures
like an accordion containing tabs, each containing a multi-column
layout, each column containing further Grid Elements, require no
special configuration beyond normal backend authoring.
