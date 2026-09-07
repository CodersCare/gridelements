.. include:: /Includes.rst.txt

.. _RenderingArchitecture:

======================
Rendering Architecture
======================

A Grid Element's frontend output ultimately needs three things that
already exist by the time rendering starts: which children belong to
the container (:ref:`DataModel`), which cell of the container's grid
each child occupies, and the container's own row/column layout. None
of that is created by rendering. It is read by rendering, from
structure that backend authoring already established, see
:ref:`DataModel` and :ref:`Nesting` for how that structure is stored
and why it nests without limit. This page is about how that stored
structure reaches Fluid or TypoScript output, not about the structure
itself.

Two rendering paths currently coexist in Grid Elements. Both start from
that same stored structure and both produce working output, but they
get there through unrelated code. The recommended path looks like
this:

.. code-block:: text

   stored Grid Elements structure
           |
           v
   relation and layout information (LayoutSetup)
           |
           v
   DataProcessing (GridChildrenProcessor)
           |
           v
   structured data for Fluid
           |
           v
   frontend output

The deprecated legacy path starts from the same stored structure and
the same ``LayoutSetup`` layout information, but does not use
``GridChildrenProcessor`` to hand the structured Grid Elements data
model to Fluid. It renders directly, through nested TypoScript
``COA``/``TEMPLATE`` objects driven by a ``USER`` cObject, and it can
use ``FLUIDTEMPLATE`` for that rendering and expose its legacy virtual
fields to Fluid-based templates the same way it exposes them to
TypoScript-based ones. Both paths are described in detail below.

The recommended path: DataProcessing and Fluid
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

``GridElementsTeam\Gridelements\DataProcessing\GridChildrenProcessor``
is a standard TYPO3 ``DataProcessorInterface`` implementation, wired up
in TypoScript like any other data processor. It queries
``tt_content`` for the records whose ``tx_gridelements_container``
matches the current container, the same relation described in
:ref:`DataModel`, resolves each child's FlexForm and backend layout
data as configured, and groups the result into the variable a Fluid
template receives, either a flat list or a rows/columns matrix built
from the container's own grid layout. It hands that structured data to
Fluid; it does not decide how it is displayed. See :ref:`DataProcessing`
for how to configure it and :ref:`DataProcessingReference` for its
options.

Fluid then renders that data through a small set of partials
(``Container``, ``Rows``, ``Columns``, ``Child``) that walk the
structure the processor built. A child that is itself a Grid Element
re-enters the same partial chain, which is the Fluid template
recursion described in :ref:`Nesting`. This page does not repeat that
explanation; the distinction between structural nesting, the
processor's ``recursive`` option, and this Fluid recursion is
canonical there.

The legacy path: TypoScript and userFunc
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

``Classes/Plugin/Gridelements.php`` is the original rendering
implementation, predating ``DataProcessing`` as a TYPO3 Core feature.
It is a ``USER`` cObject: its ``main()`` method queries the same
``tx_gridelements_container`` relation directly, resolves the
container's grid layout through :ref:`LayoutSetup <RenderingLayoutSetup>`,
and renders the result itself through nested ``COA``/``TEMPLATE``
TypoScript objects rather than handing structured data to Fluid.

The class and its public methods are marked ``@deprecated`` in source.
Grid Elements treats this path as **deprecated, not recommended for
new projects**. It remains available and functional for existing
projects that already render through it, and this documentation keeps
its :ref:`configuration <LegacyTypoScriptRendering>` and
:ref:`reference material <LegacyTypoScriptReference>` available for
that reason. New projects should use DataProcessing and Fluid instead.

.. _RenderingLayoutSetup:

The role of LayoutSetup
^^^^^^^^^^^^^^^^^^^^^^^^

``Classes/Backend/LayoutSetup.php`` resolves a Grid Element layout's
own row/column configuration, its ``allowed``/``disallowed``/
``maxitems`` per cell, into a normalized array through
``checkAvailableColumns()``. Both rendering paths depend on this: the
legacy plugin calls ``LayoutSetup::init()`` and
``getLayoutColumns()`` directly in ``main()``, and
``GridChildrenProcessor`` calls the same ``init()``/``getLayoutSetup()``
methods to resolve the container's layout for its ``respectColumns``/
``respectRows`` grouping. ``LayoutSetup`` is the shared parsing layer
underneath both rendering paths, even though the paths do nothing
else in common.

This is a distinct code path from
``GridElementsHelper::getSelectedBackendLayout()``, which parses the
same kind of ``allowed``/``disallowed``/``maxitems`` configuration but
from a page's own Backend Layout rather than a Grid Element's layout,
and feeds authoring-time restriction enforcement rather than
rendering, see :ref:`Restrictions`. The two are independently
implemented, not one calling the other, though both normalize through
the same shared helper, ``GridElementsHelper::mergeAllowedDisallowedSettings()``.
Rendering only ever depends on ``LayoutSetup``; a rendering template
has no reason to call ``getSelectedBackendLayout()``.

What rendering is not responsible for
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Neither rendering path creates the parent-child relation, decides
nesting depth, or enforces ``allowed``/``disallowed``/``maxitems``.
Those are backend authoring and DataHandler concerns, settled before a
page is ever rendered, see :ref:`ParentChildIrre`, :ref:`Nesting` and
:ref:`Restrictions`. A rendering configuration change, including
switching between the two paths described here, changes how a
structure is displayed. It never changes what that structure is.
