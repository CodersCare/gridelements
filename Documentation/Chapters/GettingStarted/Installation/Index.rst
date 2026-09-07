.. include:: /Includes.rst.txt

.. _Installation:

Installation
------------

Getting Grid Elements ready for use has three parts: get the extension
into the project, update the database schema for its tables and
fields, and include the TypoScript that renders its output. All three
are one-time, project-level setup.

Install with Composer
^^^^^^^^^^^^^^^^^^^^^^

Composer is the primary and recommended way to install Grid Elements.
From the project root:

.. code-block:: bash

   composer require gridelementsteam/gridelements

In a Composer-managed TYPO3 installation, this alone makes the
extension active. There is no separate activation step in the
Extension Manager; the Extension Manager, in Composer mode, simply
reflects what is required in ``composer.json``.

Update the database schema
^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Grid Elements adds its own database table for CE backend layout
records and a handful of fields to ``tt_content``. Bring the schema up
to date with either:

.. code-block:: bash

   vendor/bin/typo3 database:updateschema

or the Install Tool's *Analyze Database Structure* function under
*Admin Tools > Maintenance*. Review the proposed changes and apply
them.

.. note::
   One of those changes turns ``tt_content.colPos`` into a signed
   column, since Grid Elements stores negative sentinel values there
   for its own children. Applying that change is required. Never
   revert it with a later schema fix or manual migration: doing so
   prevents those negative sentinel values from being stored, which
   makes TYPO3 Core's own placement handling and backend presentation
   misinterpret affected records, even though the underlying
   ``tx_gridelements_container`` parent relation itself remains intact.
   See :ref:`DataModel` for what those sentinel values mean and how to
   recover if this happens.

Dependencies
^^^^^^^^^^^^

The current Composer package requires PHP 8.2, 8.3 or 8.4, and
TYPO3 12.4 LTS or 13.4 LTS (patch level 13.4.7 or later). It has no
dependency on any other third-party extension. Consult
``composer.json`` in the package for the exact constraint, and the
version matrix in the project's `README
<https://github.com/CodersCare/gridelements>`__ for how this maps to
other Grid Elements versions.

Basic configuration
^^^^^^^^^^^^^^^^^^^^

With the extension active and the schema updated, one piece of
frontend configuration remains before a Grid Element can render:
including its TypoScript. This is the only step that still needs
doing manually; everything about *defining* a grid's structure is
TSconfig, covered in :ref:`GridDefinitions`, and does not require
touching TypoScript at all.

Include the recommended static template
"""""""""""""""""""""""""""""""""""""""

Grid Elements ships two static templates. Open the TypoScript module,
edit the site's root template record, and on the *Includes* tab, under
*Include static (from extensions)*, add:

**Gridelements w/DataProcessing (recommended)**

This is the current, supported rendering path, built on
``GridChildrenProcessor`` and Fluid, see :ref:`Rendering` for how it
works. The other entry, **Gridelements (deprecated)**, wires up the
older ``Plugin\Gridelements`` TypoScript path; it is still functional
for existing projects but not recommended for new ones, see
:ref:`LegacyTypoScriptRendering`. Include one of the two, not both.

The recommended static template builds on ``lib.contentElement``,
which TYPO3's own Fluid-based content rendering provides. Make sure a
static template that defines it, ordinarily *Fluid Content Elements*
(``fluid_styled_content``) or a sitepackage that supplies the
equivalent, is included before Grid Elements' own static template.

Grid definitions versus rendering configuration
"""""""""""""""""""""""""""""""""""""""""""""""

Keep the two configuration languages apart. A grid's rows, columns,
cells and restrictions are :ref:`TSconfig <TSconfig>`, not TypoScript;
:ref:`Grid TS Syntax <GridTsSyntax>` documents that notation, and the
:ref:`Grid Wizard <GridWizard>` builds it visually. TypoScript, the
static template just included, is only ever about frontend rendering
output. Neither one substitutes for the other.

Installing without Composer
^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Grid Elements is also published in the `TER
<https://extensions.typo3.org/extension/gridelements>`__ for
installations that do not manage extensions with Composer. Download or
import it there, then activate it in the Extension Manager as with any
other classic-mode extension. The database schema update and
TypoScript inclusion steps above are the same either way.

Historical note. Grid Elements' extension metadata still declares a
conflict with TemplaVoila. This declaration dates from an earlier
period when the two extensions shared some of the same TYPO3 Core
hooks and could not be active on the same installation at once; it
remains in the metadata as a leftover from that situation, not as a
warning specific to any project built today.

----

With the extension installed, activated and its static template
included, continue to :ref:`QuickStart` to define and render a first
Grid Element.
