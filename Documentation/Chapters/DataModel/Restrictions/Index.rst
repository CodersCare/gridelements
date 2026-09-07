.. include:: /Includes.rst.txt

.. _Restrictions:

========================================
Restrictions, Validation & Permissions
========================================

What an editor is allowed to place into a given cell of a Grid Element,
or into a given column of a page, is decided by four separate layers.
They are easy to conflate because they operate on the same underlying
configuration, but they run at different times, for different reasons,
and a bypass at one layer does not imply a bypass at another.

Structural restrictions
^^^^^^^^^^^^^^^^^^^^^^^^

Each cell of a grid layout (page-level Backend Layout or Grid Element
layout) can carry ``allowed``, ``disallowed`` and ``maxitems``
configuration, defined in the layout's TSconfig (see the Grid TS
Syntax reference). ``allowed``/``disallowed`` apply by default to
``CType``, ``list_type`` and ``tx_gridelements_backend_layout``.
Server-side validation also automatically includes any further field
mentioned by a column's own ``allowed`` or ``disallowed``
configuration, so project-specific fields can be protected without
changing the restriction format. ``maxitems`` caps how many elements a
cell accepts.

The column restrictions are used both to filter the normal authoring
UI and to validate persisted changes server side. ``top_level_layout``
is a separate layout-level nesting restriction whose enforcement path
differs, as described below.

This configuration is the shared input to both layers below: what gets
*offered* to an editor, and what actually gets *accepted*.

Authoring-time filtering (UI layer)
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Several components filter what an editor sees, based on the
restrictions above:

* Grid Element layout options offered in the New Content Element
  Wizard are filtered through ``LayoutSetup::getLayoutWizardItems()``.
* ``ModifyNewContentElementWizardItemsListener``, a PSR-14 listener on
  Core's ``ModifyNewContentElementWizardItemsEvent``, filters the
  wizard's full item list of content element types and plugins,
  resolving the target column's restrictions either from the page's
  own backend layout or from the target grid container's layout,
  depending on where the wizard was opened.
* The corresponding FormEngine select fields (``colPos``, ``CType``,
  ``list_type``) use ``itemsProcFunc`` implementations that apply the
  same filtering to their item lists.

This layer determines what is convenient to pick, not what is
permitted. The listener above reads its restriction data
(``allowed``/``disallowed``) directly from request query parameters,
which means any authenticated backend user can construct a request
that offers different values than the wizard would. UI filtering alone
would not be a security boundary.

Server-side enforcement
^^^^^^^^^^^^^^^^^^^^^^^^

The same restrictions are enforced again, server side, independent of
what the UI offered. ``Classes/Hooks/DataHandler.php`` implements
``processDatamap_beforeStart()`` and ``processCmdmap_beforeStart()``,
backed by ``Classes/Helper/RestrictionGuard.php``:

* ``processDatamap_beforeStart()`` checks normal ``tt_content``
  datamap saves and resolves the effective target column, either the
  container's own layout and ``tx_gridelements_columns``, or the
  target page's Backend Layout and ``colPos``. It rejects the save if
  any restricted field's value is disallowed, or if the column's
  ``maxitems`` would be exceeded.
* ``processCmdmap_beforeStart()`` runs the equivalent check for
  copy and move commands (drag-and-drop, cut/paste, paste), resolving
  the same restrictions for the destination the command targets.
* ``top_level_layout`` is handled separately for copy and move
  operations that would place an existing Grid Element inside another
  container. It is not part of the generic
  ``allowed``/``disallowed``/``maxitems`` field-validation loop.

``maxitems`` enforcement counts existing siblings in the exact target
scope (container and column, or page and column, per language) and
also accounts for other records being saved in the same request, so
several new elements created together cannot collectively exceed the
limit even though none of them exists in the database yet.

A rejected save or command is not partially applied: the offending
entry is removed from the datamap or cmdmap before TYPO3 processes it,
and a flash message explains why. This makes ``allowed``/``disallowed``/``maxitems``
a real constraint on what ends up stored, not
merely a convenience for what the wizard suggests. This document makes
no claim about enforcement behavior in other maintained branches.

Permissions and auth mode
^^^^^^^^^^^^^^^^^^^^^^^^^^

The layers above decide what is allowed in a particular cell. A
separate, TYPO3 Core mechanism decides whether a given backend user may
use a particular ``CType`` at all: explicit-allow/deny auth mode,
checked via ``BackendUserAuthentication::checkAuthMode()`` and
configured per backend user or group, the same mechanism Core uses for
any other restricted field value.

Grid Elements gates two of its own features behind this check rather
than inventing a separate permission system:

* ``ModifyNewContentElementWizardItemsListener`` only runs at all if
  the current user's permissions allow the ``gridelements_pi1``
  ``CType``. A user without that permission does not see the Grid
  Elements branch of the wizard.
* The "Paste as Reference" context menu action only renders if the
  user's permissions allow the ``shortcut`` ``CType``
  (``ItemProvider::canRender()``), since creating a reference means
  creating a ``shortcut`` record.

Summary
^^^^^^^^

.. list-table::
   :header-rows: 1
   :widths: 22 38 40

   * - Layer
     - Decides
     - Enforced by
   * - Structural restrictions
     - What a layout's ``top_level_layout`` setting and a column's
       ``allowed``/``disallowed``/``maxitems`` configuration say
     - Layout TSconfig, typically authored through the Grid Wizard, the
       shared input to both layers below
   * - Authoring-time filtering
     - What is offered to an editor
     - Wizard item listener, ``itemsProcFunc`` implementations
   * - Server-side enforcement
     - What is actually accepted and stored
     - ``DataHandler`` ``beforeStart`` hooks, ``RestrictionGuard``
   * - Permissions / auth mode
     - Whether a user may use a given ``CType`` at all
     - TYPO3 Core ``checkAuthMode()``
