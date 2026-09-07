.. include:: /Includes.rst.txt

.. _UpgradingCompatibilityRestrictionEnforcement:

===========================================
Restriction Enforcement Across Versions
===========================================

:ref:`Restrictions` documents the four layers that decide what an
editor is allowed to place into a grid cell or page column, in full.
This page only adds the version dimension: does any of that differ
between the TYPO3 versions this branch supports?

Server-side enforcement: confirmed identical
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

The server-side enforcement layer, ``Classes/Hooks/DataHandler.php``
and ``Classes/Helper/RestrictionGuard.php``, contains no
TYPO3-version-conditional code. The same ``allowed``/``disallowed``/
``maxitems`` checks, in ``processDatamap_beforeStart()`` and
``processCmdmap_beforeStart()``, run identically regardless of which
supported TYPO3 version (``^12.4`` or ``^13.4.7``) a project runs.
There is no known gap between them for this enforcement layer.

Related, but not a restriction-enforcement, difference
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Two other backend touchpoints do differ by TYPO3 version, and are easy
to conflate with restriction enforcement because they sit nearby, but
neither one decides what is allowed or accepted:

* The record-usage marking mechanism, ``Hooks\PageLayoutView`` (dead
  in the versions this branch supports) versus
  ``IsContentUsedOnPageLayoutListener`` (current), only affects
  whether a grid child is reported as "used" for Core's own unused-content
  indicators.
* The List module XCLASS, ``Xclass\DatabaseRecordList`` versus
  ``Xclass\DatabaseRecordList12``, only affects how nested rows are
  rendered when ``nestingInListModule`` is enabled.

See :ref:`DeveloperReferenceExtensionPoints` for both.

Scope of this statement
^^^^^^^^^^^^^^^^^^^^^^^^

This confirms behavior for the current codebase on this branch only,
supporting TYPO3 ``^12.4`` and ``^13.4.7``. It makes no claim about
restriction-enforcement behavior on other maintained branches of the
extension; earlier branches maintain their own, independent file set
and are out of scope for this documentation.
