.. include:: /Includes.rst.txt

.. _UpgradingCompatibility:

=========================
Upgrading & Compatibility
=========================

This section covers what changes about Grid Elements' own data across
a TYPO3 version upgrade, the tools available to repair that data if
something goes wrong, and what does (and does not) differ in
restriction enforcement between the TYPO3 versions this branch
supports. It is not a changelog; historical release notes belong in
the extension's own changelog, not here.

.. list-table::
   :header-rows: 0
   :widths: 30 70

   * - :ref:`UpgradingCompatibilityUpgradeWizards`
     - The data-repair commands available for Grid Elements' own
       fields, and when a project actually needs them.
   * - :ref:`UpgradingCompatibilityDataCompatibility`
     - The ``colPos`` signed/unsigned issue and why it matters
       specifically during a TYPO3 Core version upgrade.
   * - :ref:`UpgradingCompatibilityRestrictionEnforcement`
     - Whether ``allowed``/``disallowed``/``maxitems`` enforcement
       differs between the TYPO3 versions this branch supports.

.. toctree::
   :maxdepth: 1
   :titlesonly:
   :hidden:

   UpgradeWizards/Index
   DataCompatibility/Index
   RestrictionEnforcement/Index
