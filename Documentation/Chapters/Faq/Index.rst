.. include:: /Includes.rst.txt

.. _Faq:

======================
FAQ & Troubleshooting
======================

FAQ
^^^

**Question: What is the recommended way to create grid definitions?**

**Answer:** Even though a grid definition can be created and edited
entirely as a record through the Grid Wizard, the recommended approach
for a real project is a file-based definition with a unique,
hand-chosen identifier rather than an autoincremented record ID. A
file-based definition works identically across every environment of a
project, can be put into version control and deployed like any other
project configuration, and does not depend on a database record having
already been created with the same ID somewhere else. See
:ref:`TSconfig` and :ref:`GridWizard`, which reads and writes that
same TSconfig regardless of where it is stored.

**Question: Is Grid Elements just another container extension?**

**Answer:** Container behavior, a parent element that holds and
structures children, is part of what Grid Elements does, but it is
not the whole of it. Grid Elements is a Structure-first Authoring
system that includes container behavior as one part of a substantially
broader architecture; a dedicated container-only extension such as
EXT:container implements one particular, useful container-processing
model within that broader space, not a feature-equivalent replacement.
See :ref:`GridElementsAndContainer` for the detailed technical
comparison, including scope, data model, DataProcessing, localization
and integration mechanisms.

**Question: How do I connect my templates to the structure Grid
Elements builds?**

**Answer:** The recommended, modern approach is the
``GridChildrenProcessor`` DataProcessor, see :ref:`DataProcessing`. Wired
up in TypoScript like any other data processor, it hands a structured
Grid Elements data model to Fluid, which project-specific templates and
partials then render, see :ref:`RenderingArchitecture` for how that
fits together as a whole.

The original TypoScript rendering path is deprecated but remains
available as a supported legacy path for existing projects, see
:ref:`LegacyTypoScriptRendering`. It can be connected the same way a
page template is, through TypoScript's ``TEMPLATE`` or
``FLUIDTEMPLATE`` objects, and it exposes its legacy virtual fields to
Fluid-based templates the same way it exposes them to TypoScript-based
ones, so this path does not require choosing TypoScript over Fluid
either.

**Question: Why is FlexForm not recommended for building new content
elements?**

**Answer:** FlexForm data is stored as a single XML blob rather than
as normal, individually queryable database fields. That makes it hard
to collect or aggregate that content, for example while building a
teaser listing or another kind of cross-page collection, since there
is no normalized data structure to query against.

**Question: How should I build a new content element with individual,
structured input fields instead?**

**Answer:** Register a new ``CType`` on ``tt_content`` through the
TYPO3 TCA API, since that table already provides most of the fields a
project is likely to need. Add the TCA structure and the new content
type via an extension, provide TypoScript or Fluid for its frontend
output, and it behaves like any other content element, including
inside a Grid Element.

**Question: Can I place a Grid Element inside another Grid Element?**

**Answer:** Yes. A Grid Element container is itself a regular
``tt_content`` record, using the ``gridelements_pi1`` ``CType``, so it
is placed exactly the way any other content element is placed, and it
can hold and nest further Grid Elements without a depth limit, see
:ref:`Nesting`.

**Question: Why does a drag-and-drop action reload the page, when
TYPO3 Core's own don't?**

**Answer:** When several editors work on the same page's content at
the same time, TYPO3 Core only shows a change once someone opens that
particular element's own edit form; nothing signals a change made
elsewhere on the same page. Reloading after a drag-and-drop action
avoids the confusion that would otherwise build up in a page with
active structural editing by several editors at once. As long as
TYPO3 has no general content-locking mechanism of its own, Grid
Elements keeps this behavior.

Troubleshooting
^^^^^^^^^^^^^^^^

**A grid cell that should be available doesn't show up when placing
content.**

Check the cell's ``allowed``/``disallowed`` and ``maxitems``
restrictions first; a full cell or a disallowed ``CType`` is filtered
out of both the Drag-In Wizard and the New Content Element Wizard
before an editor ever sees it, see :ref:`Restrictions`. If the whole
grid is missing rather than one cell, check that the Grid Element's
own Backend Layout selection actually resolves, either a
database-stored CE Backend Layout or file-based Page TSconfig, see
:ref:`GridWizard` and :ref:`TSconfig`.

**Content elements have disappeared after a grid layout was changed.**

This is usually the "unused elements" state, not data loss: when a
layout change removes the column an element used to occupy, Grid
Elements moves it to ``colPos = -2`` and saves its previous value in
``backupColPos`` rather than deleting it, see :ref:`DataModel`. Add
back a matching column, or a page-level column configured with
``colPos = -2``, to see and recover it. If elements have moved to
column 0 instead, see the ``colPos`` sentinel note below.

**A grid child's ``colPos`` looks wrong in the Page or List module,
even though it is still shown as part of its Grid Element.**

This is the signed/unsigned ``colPos`` schema issue, most often seen
after a TYPO3 Core version upgrade, see
:ref:`UpgradingCompatibilityDataCompatibility`. It affects only
``colPos``, the field TYPO3 Core's own placement handling and backend
presentation read; it does not change the actual parent relation,
``tx_gridelements_container``, which is why the child still shows up
correctly wherever rendering or editing reads that relation directly.
If ``tx_gridelements_container`` is still correct on the affected
rows, the Col Pos Fixer described at :ref:`DataConsistencyTools`
restores the ``-1`` sentinel.

**Nesting a Grid Element inside another doesn't seem to work, or seems
artificially limited.**

Structural nesting itself has no depth limit and needs no separate
setting to enable, see :ref:`Nesting`. If children of a nested
container are missing from rendered output specifically, check the
DataProcessor's ``recursive`` option: it controls how many additional
levels are pre-fetched in a single processing pass and is a
data-eagerness setting, not a nesting toggle, see
:ref:`typoscript-dataprocessing-default-options-recursive`. Fluid
template recursion, the partial chain re-entering itself for a nested
Grid Element, is a third, independent thing again, also documented on
:ref:`Nesting`.

**The ``children`` variable in a Fluid template is empty, or not
grouped the way expected.**

Confirm ``GridChildrenProcessor`` is actually wired into the relevant
TypoScript ``dataProcessing`` stack, see :ref:`DataProcessing`. If it
runs but the shape of ``children`` is unexpected, check
``respectColumns`` and ``respectRows``: with both off, the result is a
genuinely flat list; ``respectColumns`` alone makes ``children`` a
column-indexed matrix; ``respectRows`` additionally indexes it by row,
with the column-indexed arrays nested inside, see
:ref:`DataProcessingReference`.

**A grid definition set in one place doesn't seem to apply, or gets
overridden.**

Grid definitions are page TSconfig, and page TSconfig cascades down
the page tree the same way as any other page TSconfig: a definition
set closer to the affected page wins over one set further up the
rootline. Check for a more specific override before assuming the
definition itself is wrong, see :ref:`TSconfigGridElementsScope`.

**Where is a Grid Element's layout actually stored? I can't find it as
a file.**

By default, the Grid Wizard stores a layout in a CE Backend Layout
database record. That is not a limitation of the wizard, the same
TSconfig construct can also be copied into file-based Page TSconfig
under ``tx_gridelements.setup`` for version control, see
:ref:`GridWizard` and :ref:`TSconfig`.

**An editor was still able to place something a restriction should
have blocked.**

Distinguish what filtered the UI from what was actually persisted:
UI-level filtering (the New Content Element Wizard, the Drag-In
Wizard, and the ``itemsProcFunc`` implementations behind their form
fields) determines only what looks convenient to pick and is not
itself a security boundary, since its restriction data can be read
from request parameters. What is actually accepted is decided
separately, server side, by the ``DataHandler`` hooks and
``RestrictionGuard``. See :ref:`Restrictions` for the full layer
breakdown before assuming a restriction was bypassed.

**A translated page's Grid Element children don't match the default
language, or got detached after translating.**

This depends on whether the page uses TYPO3's connected (Translate)
or free (Copy) localization mode, which interact differently with a
Grid Element's own structure and its children, see
:ref:`EditorGuideLocalization`.
