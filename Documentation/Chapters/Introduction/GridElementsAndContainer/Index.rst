.. include:: /Includes.rst.txt

.. _GridElementsAndContainer:

================================
Grid Elements and EXT:container
================================

Grid Elements is often described, informally, as one of several
interchangeable "grid" or "container" extensions for TYPO3, with
``b13/container`` (EXT:container) as the most frequently mentioned
alternative. The two extensions' actual code and documentation
describe a different scope for each. This page compares them directly,
by architecture, data model and integration mechanism, not by age or
popularity, so a reader evaluating either one can judge the actual
difference in scope.

Everything said here about EXT:container is based on its own
``README.md`` and current source (``B13\Container\...``, inspected at
the time of writing); everything said about Grid Elements is based on
this codebase, cross-referenced to the detailed pages linked
throughout.

A narrower scope, by EXT:container's own description
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

EXT:container's own ``README.md`` states its scope explicitly:

    "We wanted an extension that only does one thing: EXT:container
    ONLY adds tools to create and render container elements. There are
    no FlexForms, no permission handling, or custom rendering."

    "We wanted an extension in which every grid has its own Content
    Type (CType), making it as close as possible to TYPO3 Core
    functionality."

That is a deliberate design: every container definition is its own
``CType``, registered in PHP per project, with a fixed
column/``colPos`` layout, and EXT:container's scope covers creating
and rendering that structure.

Grid Elements has a broader scope. A Grid Element is not, in its own
model, a container implementation with some extra features attached;
it is a Structure-first Authoring system, see :ref:`CoreConcepts`, in
which container behavior is one part of a larger set of capabilities
that share the same underlying structure:

.. list-table::
   :header-rows: 0
   :widths: 34 66

   * - Reusable structural definitions
     - One grid layout, written once as TSconfig, reusable across any
       number of Grid Elements, see :ref:`GridDefinitions`. EXT:container
       fixes a layout's columns in PHP per ``CType``.
   * - Grid Wizard
     - Visual structure definition tool, reading and writing that same
       TSconfig, see :ref:`GridWizard`.
   * - Structural parent-child relation
     - A maintained relation independent of ``colPos``, see
       :ref:`DataModel`.
   * - Row and column semantics
     - An explicit two-dimensional structure, not only a flat set of
       columns, see :ref:`DataModel`.
   * - Nesting
     - Unlimited structural nesting, with no separate setting to
       enable it, see :ref:`Nesting`.
   * - Restrictions and validation
     - ``allowed``/``disallowed``/``maxitems`` enforced server side by
       Grid Elements itself, with no further extension required, see
       :ref:`Restrictions`.
   * - References
     - "Paste as Reference" lets an existing record participate in
       more than one structure without duplication, see
       :ref:`EditorGuideReferences`.
   * - Unused-element handling
     - Content that loses its column on a layout change is parked, not
       lost, see :ref:`DataModel`.
   * - Drag-In Wizard
     - Creates and places a new element in a single drag, without a
       detour through the New Content Element Wizard, see
       :ref:`EditorGuideDragInWizard`.
   * - FlexForm integration
     - Per-instance configurable behavior on top of the structure, see
       :ref:`Flexform`.
   * - List Module integration
     - Optional nested rendering of a container's children in TYPO3's
       Web > List module, see :ref:`DeveloperReferenceListModuleIntegration`.
   * - Structural context at rendering time
     - The relation, cell and layout information built during
       authoring is what rendering reads, see :ref:`RenderingArchitecture`.
   * - Configurable DataProcessor
     - One processor, several structural views of the same data
       (flat, column-grouped, row/column matrix, recursively
       pre-fetched), see :ref:`DataProcessing`.

Two of these are worth naming concretely, because they are easy to
assume are included in a "container extension" and are not, in
EXT:container's own case:

* **Restrictions.** EXT:container's own README states plainly:
  "Supports colPos-restrictions if EXT:content_defender is installed."
  ``allowed``/``disallowed``/``maxitems`` enforcement is not part of
  EXT:container itself; it depends on a separate third-party
  extension, ``EXT:content_defender``
  (``ichhabrecht/content-defender``, ``IchHabRecht\ContentDefender\...``,
  a different vendor from both b13 and Grid Elements Team) being
  installed alongside it. Grid Elements enforces the same kind of
  restriction natively, as part of its own ``DataHandler`` logic, with
  no additional extension, see :ref:`Restrictions`.
* **References.** Neither EXT:container's ``README.md`` nor its
  current source contains a "paste as reference" or equivalent
  content-reference mechanism. Achieving that on an EXT:container-based
  project depends on a further, separate third-party extension,
  ``EXT:paste_reference`` (Composer package
  ``ehaerer/paste-reference``). That extension's own documentation
  describes its origin directly: it "brings the extracted functions
  from gridelements to copy and paste content elements also as
  reference and not only as copy", extracted from Grid Elements itself
  into a standalone extension so projects that do not need the rest of
  Grid Elements can still have that one capability. Grid Elements
  still provides "Paste as Reference" as one of its own built-in
  capabilities, unextracted, see :ref:`EditorGuideReferences`.

Even combined, EXT:container, content_defender and paste_reference do
not reach feature parity with Grid Elements: content_defender
validates restrictions against an existing structure, and
paste_reference creates reference records, but neither one materializes
or authors structure. None of the three provides a Drag-In Wizard,
Grid Elements' own structural row/column model, or recursive frontend
DataProcessing, since all three are specific to how Grid Elements
structures and materializes its own data, see
:ref:`EditorGuideDragInWizard` and the DataProcessing comparison below.
EXT:container implements a deliberately narrower container model,
described as such in its own README. It is not a feature-equivalent
replacement to Grid Elements' broader Structure-first Authoring
architecture.

Is EXT:container "Core native"?
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

EXT:container is sometimes described as more "Core native" than Grid
Elements. Technically, that label does not apply to either extension:
EXT:container is a third-party Composer/TER package
(``b13/container``), not a TYPO3 Core feature, and it integrates with
TYPO3 through the same kinds of extension mechanisms Grid Elements
and any other TYPO3 extension use.

.. list-table::
   :header-rows: 1
   :widths: 34 33 33

   * - Mechanism
     - Grid Elements
     - EXT:container
   * - DataHandler hooks
     - Three (``processDatamapClass``, ``processCmdmapClass``, an
       unused ``moveRecordClass`` registration), see
       :ref:`DeveloperReferenceExtensionPoints`.
     - Six ``$GLOBALS`` hook registrations in ``ext_localconf.php``
       (four ``processCmdmapClass``: ``CommandMapPostProcessingHook``,
       ``CommandMapBeforeStartHook``, ``DeleteHook``,
       ``CommandMapAfterFinishHook``; two ``processDatamapClass``:
       ``DatamapBeforeStartHook``, ``DatamapPreProcessFieldArrayHook``).
   * - PSR-14 event listeners
     - Eight, registered in ``Configuration/Services.yaml``, see
       :ref:`DeveloperReferenceExtensionPoints`.
     - Nine, via ``#[AsEventListener]`` attributes in
       ``Classes/Listener/*``: ``BootCompleted``, ``ContentUsedOnPage``
       (``IsContentUsedOnPageLayoutEvent``, the same event Grid
       Elements' ``IsContentUsedOnPageLayoutListener`` uses),
       ``IsReferenceConsideredForDependency`` (Workspaces),
       ``PageContentPreviewRendering`` and
       ``LegacyPageContentPreviewRendering``,
       ``ManipulateBackendLayoutColPosConfigurationForPage``,
       ``ModifyNewContentElementWizardItems`` (the same Core event
       Grid Elements' own listener of the same purpose uses),
       ``PageTsConfig``, ``RecordSummaryForLocalization`` (see
       Localization below).
   * - Own events for third-party listeners
     - One (``ModifyRecordListElementDataEvent``), see
       :ref:`DeveloperReferenceExtensionPoints`.
     - Two (``BeforeContainerConfigurationIsAppliedEvent``,
       ``BeforeContainerPreviewIsRendered``).
   * - XCLASS / class-replacement overrides
     - One, conditional on the ``nestingInListModule`` opt-in:
       XCLASSing Core's ``DatabaseRecordList`` for List module
       nesting, see :ref:`DeveloperReferenceListModuleIntegration`.
     - Two, conditional on EXT:content_defender being active:
       ``$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']`` replaces
       ``IchHabRecht\ContentDefender``'s own DataHandler hook classes
       with EXT:container's versions, the same override mechanism as
       an XCLASS.

Both extensions integrate with TYPO3's backend through hooks, events
and, where necessary, class overrides; neither uses a different,
more "native" channel that the other lacks access to. The full
breakdown of Grid Elements' own mechanisms is at
:ref:`DeveloperReferenceExtensionPoints`.

What is true, and is a legitimate design choice rather than a claim
about Core integration, is that EXT:container's data model stays
close to Core's own ``colPos`` field: a container child's cell and its
page-level placement are the same value, using project-chosen
``colPos`` numbers (for example ``200``, ``201`` in its own README
example) rather than a value TYPO3 Core itself assigns meaning to.
Grid Elements makes a different, equally deliberate choice, keeping
those concerns separate, see below. A data model choice is a design
choice. It does not turn either extension into TYPO3 Core
functionality.

The translated-child relation model, see Localization below, is the
one place on this page where a "Core-native" comparison is technically
meaningful, and it favors Grid Elements rather than EXT:container.
Elsewhere on this page, "Core native" does not describe a real
architectural distinction between the two extensions.

Data model
^^^^^^^^^^

.. list-table::
   :header-rows: 1
   :widths: 34 33 33

   * -
     - Grid Elements
     - EXT:container
   * - Parent relation
     - ``tx_gridelements_container``, on the child, pointing at the
       container's ``uid``, see :ref:`DataModel`.
     - ``tx_container_parent``, on the child, pointing at the
       container's ``uid``.
   * - Cell identifier
     - ``tx_gridelements_columns``, a structural cell identifier kept
       separate from ``colPos``, see :ref:`DataModel`.
     - ``colPos``, a project-chosen value with no fixed meaning beyond
       the container's own PHP definition, and simultaneously the
       value TYPO3 Core's own placement model sees.
   * - Core placement compatibility
     - ``colPos`` remains available for Core's own placement and
       compatibility behavior (``-1``/``-2`` sentinels), independent
       of the structural cell, see :ref:`DataModel` and
       :ref:`UpgradingCompatibilityDataCompatibility`.
     - Not distinct from the cell identifier; the same field serves
       both roles.
   * - Child counter
     - ``tx_gridelements_children``, a maintained counter, see
       :ref:`DataModel`.
     - None; EXT:container's own migration notes state a container
       "doesn't store the number of its children".

Keeping ``tx_gridelements_container``, ``tx_gridelements_columns`` and
``colPos`` as three separate concerns, rather than collapsing the
cell identifier into ``colPos``, is what lets Grid Elements give
``colPos`` its own, Core-facing sentinel values (``-1`` for a grid
child, ``-2`` for an unused element) without that choice ever
constraining what a structural cell is allowed to be numbered. This is
a different structural data model, not merely a different field
naming scheme for the same idea, see :ref:`DataModel` for the full
picture.

One further difference is which side of the relation each extension
persists. EXT:container stores ``tx_container_parent`` only on the
child; the Container record itself maintains no reciprocal, parent-side
child relation. The relation is one-way, Child → Parent, and retrieving
a container's children always means querying ``tt_content`` for the
records whose ``tx_container_parent`` points back at it. This is a
characteristic of the relation, not a claim that EXT:container cannot
retrieve its own children; Grid Elements' own ``tx_gridelements_container``
requires the same kind of query, see :ref:`DataModel`.

Grid Elements additionally models the parent side of that same
underlying TYPO3 inline relation, through ``tx_gridelements_children``,
a TCA ``inline`` field (``foreign_table = tt_content``,
``foreign_field = tx_gridelements_container``) that also maintains a
stored child counter, see :ref:`DataModel`. ``tx_gridelements_children``
is not the source of truth for identifying a container's children;
that remains ``tx_gridelements_container``, queried the same way
regardless. The architectural difference is that Grid Elements models
both the child-to-parent and parent-to-child sides of the relation
through TYPO3's own inline-relation machinery, where EXT:container
persists only the child-to-parent side.

DataProcessing
^^^^^^^^^^^^^^

Both extensions render through a TYPO3 DataProcessor, described for
Grid Elements' own side in full at :ref:`DataProcessing`, but
``B13\Container\DataProcessing\ContainerProcessor`` implements one
particular container-processing model, not "the" container processing
model as such: it exposes four options, ``contentId``, ``colPos``,
``as`` and ``skipRenderingChildContent``. Its behavior splits in two:

* **With ``colPos`` explicitly set**, it scopes its query to that one
  area and returns that area's direct children as a flat list under
  the configured ``as`` variable, rendering each one unless told not
  to.
* **With ``colPos`` left unset**, it determines the container's
  available ``colPos`` areas itself and processes all of them within
  that same single processor execution, exposing one flat array per
  area as ``children_<colPos>`` (``children_200``, ``children_201``,
  and so on). This is EXT:container's own documented default
  behavior, not something a project has to configure through several
  processor calls; the explicit-``colPos`` form above exists for
  choosing a different variable name or targeting one area on its own,
  not because covering several areas otherwise requires one
  ``ContainerProcessor`` entry per area.

What ContainerProcessor never produces, in either form, is one shared
structural object spanning several areas at once: it returns as many
separate flat arrays as there are areas involved, not a matrix any of
them are cells of.

``GridElementsTeam\Gridelements\DataProcessing\GridChildrenProcessor``
approximates that flat shape as one configuration among several of a
substantially broader structural processing system. For one explicitly
selected Container area, the approximate configuration is:

.. code-block:: typoscript

   sortingDirection = ASC
   sortingField = sorting
   recursive = 0
   resolveFlexFormData = 0
   resolveChildFlexFormData = 0
   resolveBackendLayout = 0
   respectColumns = 0
   respectRows = 0

``respectColumns = 0`` is the important setting here, not
``respectColumns = 1``. Grid Elements does not use ``colPos`` to
represent its structural grid column at all, see :ref:`DataModel`.
This is a *shape* approximation, not a *scope* one: with all
structural shaping disabled, ``GridChildrenProcessor`` still retrieves
the complete direct child set through ``tx_gridelements_container``
(unfiltered by cell); ``respectColumns``/``respectRows`` only change
how that already-fetched, complete set is shaped afterward, not what
the query scopes to. It does not query-scope that set down to one
``tx_gridelements_columns`` value the way ContainerProcessor's
``colPos`` parameter scopes its own query to one area. The two produce
the same flat, ungrouped *shape*; the Grid Elements result still
contains every one of the container's children, not only those of a
single selected cell. Narrowing a flat ``GridChildrenProcessor`` result
down to one specific structural cell is not something any option does
directly; that would have to happen in the template instead.

Everything past that approximation is capability ContainerProcessor
has no equivalent option for:

* **Recursion.** ``recursive`` lets ``GridChildrenProcessor``
  materialize additional levels of a nested Grid Element tree into one
  resulting data structure, within one DataProcessing result. It does
  not do this in a single database query for the whole tree: for each
  child that is itself a Grid Element, the processor invokes a further
  ``GridChildrenProcessor`` run against that child, with ``recursive``
  decremented by one, which issues its own separate query for that
  level. The materialized result nests as one tree in the template;
  reaching it still costs one additional processor call and query per
  nested level, not one query overall. ContainerProcessor processes
  only the current container's direct children; a nested container
  among those children is, to it, just another content element,
  processed and rendered separately when its own turn comes, not a
  claim that EXT:container's containers cannot structurally nest.
* **Rows.** Grid Elements' structural model has an explicit row
  dimension, not only columns, see :ref:`DataModel`. ``respectRows``
  can preserve that dimension in the output, indexing ``children`` by
  row first, with the column-indexed arrays nested inside.
  ContainerProcessor has no equivalent, since its underlying data
  model does not carry a row concept separate from ``colPos``.
* **Columns.** ``respectColumns``, enabled, retrieves the full direct
  child set through ``tx_gridelements_container`` and reshapes it into
  one matrix indexed by ``tx_gridelements_columns``, across *all* of a
  container's cells, in a single processing operation and a single
  output variable. This is the capability behind the data model
  distinction above turning into a different processing capability,
  not merely a different field name.
* **Backend Layout and FlexForm resolution.** ``resolveBackendLayout``
  resolves the current Grid Element's (and, for nested children, their
  own) Backend Layout information; ``resolveFlexFormData`` and
  ``resolveChildFlexFormData`` resolve FlexForm data for the current
  Grid Element and its children respectively, see :ref:`Flexform`.
  EXT:container's own scope explicitly excludes FlexForm handling
  ("There are no FlexForms..."), consistent with, not a gap relative
  to, its stated design.
* **Configurable sorting**, ``sortingField`` and ``sortingDirection``,
  rather than only the fixed common case.

See :ref:`DataProcessing` for the full option reference and how to
configure ``GridChildrenProcessor`` in a project.

Localization
^^^^^^^^^^^^

Both extensions build on TYPO3 Core's connected/free localization
modes, described for Grid Elements' own side in full at
:ref:`EditorGuideLocalization`, not on a localization mechanism of
their own. Where they differ architecturally is what each keeps its
structural relation pointing at once a child is translated, and this
is the one place on this page where a "Core-native" comparison is
technically meaningful, not because either extension is Core, but
because one relation model follows the pattern TYPO3 Core's own
translated inline relations are expected to have, and the other
deliberately does not.

Grid Elements' own ``DataHandler`` logic
(``checkAndUpdateTranslatedElements()``, see :ref:`ParentChildIrre`)
resolves a record's translated counterpart the same way TYPO3 Core
itself would, through ``l18n_parent``, and keeps a translated child's
``tx_gridelements_container`` pointing at the *corresponding
translated container* found that way, not at the original-language
one. This is exactly the relation shape an ordinary TYPO3 inline
relation between a translated child and a translated parent is
expected to have; the structural parent-child relation stays valid, in
the ordinary Core sense, inside the translated language tree.

EXT:container's own documentation describes a different model for its
connected mode: translating a container also translates its children,
a translated child's ``l18n_parent`` points, as usual, to its
translation origin, but its ``tx_container_parent`` points to the
*original-language* container, not the translated one, a relation
Core's own model would not itself produce. Because the relation field
does not follow the translation, EXT:container's current code carries
additional localization-specific logic to reinterpret those records
correctly wherever that matters, including its ``ContentUsedOnPage``
and ``RecordSummaryForLocalization`` event listeners, see
:ref:`DeveloperReferenceExtensionPoints`.

The distinction is not merely that the two extensions "handle
localization differently": Grid Elements follows TYPO3 Core's normal
translated inline-relation model, while EXT:container introduces its
own cross-language parent relation and then has to compensate for it
through additional overlay and localization-specific logic. Public
issues document this causing real integration friction:
``l10nmgr``, which imports translated content through TYPO3's normal
record-translation workflow, ran into this relation model
(`b13/container#193 <https://github.com/b13/container/issues/193>`__),
and the same relation model is documented as causing a separate
problem within EXT:container's own Connected Mode translation
(`b13/container#609 <https://github.com/b13/container/issues/609>`__).
DeepL-based translation integrations have also required compatibility
fixes for EXT:container, as documented in the ``deepltranslate_core``
changelog (`localization wizard with EXT:container
<https://github.com/web-vision/deepltranslate-core/blob/main/CHANGELOG.md>`__
and an earlier fix for translation of inline elements in containers).
The changelog documents that such fixes were required; it does not
itself attribute them to the cross-language ``tx_container_parent``
relation specifically.

When each fits
^^^^^^^^^^^^^^

EXT:container is a coherent choice when a project's need is exactly
what it describes itself as solving: a small, fixed set of per-project
container layouts, each its own ``CType``, rendered through
ContainerProcessor's flat, area-based processing model, with no
requirement for reusable structural definitions, restrictions without
an additional extension, references, unused-element handling, or
one-motion drag-in authoring.
Grid Elements fits when some or all of the broader capability list
above is what a project actually needs; the two are not competing
implementations of the same scope, and the choice between them should
follow from that difference in scope, not from which one is perceived
as newer or more widely discussed.

Further reading
^^^^^^^^^^^^^^^^

`Grid Elements 14 Available – Version 13 in the TER
<https://coders.care/blog/article/gridelements-14-available-version-13-in-the-ter>`__,
a Coders.Care article making the same distinction from the maintainer
side: "Gridelements is far more than a solution for simple container
structures. The extension follows an independent and comprehensive
approach that combines flexible layouts, editorial control and
comfortable visual handling."

`Grid Elements: The Idea
<https://coders.care/blog/article/grid-elements-the-idea>`__, the
Coders.Care article on Grid Elements' own conceptual background, also
cited in :ref:`Introduction`, for the broader argument that Grid
Elements "was never really about grids" in the narrow sense a
container-only extension covers.

.. list-table::
   :header-rows: 0
   :widths: 30 70

   * - :ref:`CoreConcepts`
     - The Structure-first Authoring model this comparison assumes.
   * - :ref:`DataModel`
     - The full ``tx_gridelements_container`` /
       ``tx_gridelements_columns`` / ``colPos`` picture.
   * - :ref:`Nesting`
     - How structural nesting works, independent of any single
       DataProcessor option.
   * - :ref:`DataProcessing`
     - The detailed ``GridChildrenProcessor`` vs. ``ContainerProcessor``
       comparison.
   * - :ref:`EditorGuideLocalization`
     - The detailed localization relation comparison.
   * - :ref:`DeveloperReferenceExtensionPoints`
     - The full mechanism-by-mechanism integration comparison.
