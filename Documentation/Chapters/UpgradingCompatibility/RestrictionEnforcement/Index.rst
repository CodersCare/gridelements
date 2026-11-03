.. include:: /Includes.rst.txt

.. _UpgradingCompatibilityRestrictionEnforcement:

===========================================
Restriction Enforcement Across Versions
===========================================

:ref:`Restrictions` documents the four layers that decide what an
editor is allowed to place into a grid cell or page column, in full.
This page only adds the version dimension: does any of that differ
between the TYPO3 versions this branch supports?

Server-side enforcement: TYPO3 13 and 14
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

On TYPO3 13, the server-side enforcement layer,
``Classes/Hooks/DataHandler.php`` and
``Classes/Helper/RestrictionGuard.php``, checks the
``allowed``/``disallowed``/``maxitems`` restrictions in
``processDatamap_beforeStart()`` and ``processCmdmap_beforeStart()``
for both page-level columns and Grid Element cells.

On TYPO3 14, the ``CType`` restriction of page-level Backend Layout
columns is delegated to TYPO3 Core's own
``DataHandlerContentElementRestrictionHook``. Grid Elements merges its
page-column ``CType`` rules into Core's column configuration through
``ManipulateBackendLayoutColPosConfigurationListener``. The remaining
field checks, ``maxitems`` and restrictions inside Grid Element cells
remain with Grid Elements.

Related, but not a restriction-enforcement, difference
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Two other backend touchpoints are easy
to conflate with restriction enforcement because they sit nearby, but
neither one decides what is allowed or accepted:

* The record-usage marking mechanism,
  ``IsContentUsedOnPageLayoutListener``, only affects
  whether a grid child is reported as "used" for Core's own unused-content
  indicators.
* The List module (Web > List on TYPO3 13, Content > Records on
  TYPO3 14) XCLASS, ``Xclass\DatabaseRecordList`` versus
  ``Xclass\DatabaseRecordList13``, only affects how nested rows are
  rendered when ``nestingInListModule`` is enabled.

See :ref:`DeveloperReferenceExtensionPoints` for both.

Scope of this statement
^^^^^^^^^^^^^^^^^^^^^^^^

This confirms behavior for the current codebase on this branch only,
supporting TYPO3 ``^13.4.7`` and ``^14.3``. It makes no claim about
restriction-enforcement behavior on other maintained branches of the
extension; earlier branches maintain their own, independent file set
and are out of scope for this documentation.
