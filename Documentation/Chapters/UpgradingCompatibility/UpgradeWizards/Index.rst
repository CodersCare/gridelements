.. include:: /Includes.rst.txt

.. _UpgradingCompatibilityUpgradeWizards:

================
Upgrade Wizards
================

Grid Elements does not register a TYPO3 Install Tool upgrade wizard
(``UpgradeWizardInterface``) for its own data; there is no entry for
it in Admin Tools > Upgrade. The mechanisms that exist for repairing
Grid Elements' own fields after something has gone wrong are the two
data consistency tools documented in full at
:ref:`DataConsistencyTools`: the Col Pos Fixer and the Number Of
Children Fixer, each available as both a TYPO3 CLI command and a
classic Scheduler task.

No structural migration between Grid Elements versions
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

The absence of a registered upgrade wizard is not only about repair
tooling; it also reflects that upgrading Grid Elements itself has not
required one. Grid Elements' persisted structure,
``tx_gridelements_container`` as the parent relation,
``tx_gridelements_columns`` as the structural cell, and
``tx_gridelements_children`` as the maintained IRRE counter, see
:ref:`DataModel`, has remained backward-compatible across Grid
Elements versions. An existing Grid Elements structure continues to be
understood by a newer Grid Elements version without being converted
into a replacement structural storage model.

This is distinct from the two other mechanisms on this page and in
this section:

* **Structural migration** would convert existing data from one
  structural model to another. Grid Elements has not required this
  between its own versions.
* **Repair and consistency tools**, the Col Pos Fixer and Number Of
  Children Fixer described below, restore specific field values after
  an external interference. They repair values within the existing
  structural model; they do not convert it into a different one.
* **Compatibility-related Upgrade Wizards**, see
  :ref:`UpgradingCompatibilityDataCompatibility`, address how Grid
  Elements' data interacts with a given TYPO3 Core version, such as
  the ``colPos`` schema definition. This is not a statement that no
  TYPO3 Core-level upgrade wizard is ever relevant to a project using
  Grid Elements, only that none is required to migrate the Grid
  Elements structure itself.

When they are actually relevant
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Both tools exist for exceptional states, not as a routine step in
every upgrade. The Col Pos Fixer's own docblock names the scenario
that makes it relevant specifically to upgrading: a TYPO3 Core
major-version upgrade running while Grid Elements is temporarily
uninstalled or inactive, so its ``ext_tables.sql`` schema definition
is not part of the active set a schema migration compares against, and
the migration applies Core's unsigned ``colPos`` definition instead of
Grid Elements' own. This is purely a database schema mechanism, see
:ref:`UpgradingCompatibilityDataCompatibility`; it is separate from
this tool, which only repairs the resulting record values, not the
column's SQL definition. That scenario is avoidable, not an inherent
part of upgrading: Grid Elements does not need to be deactivated for a
Core major-version upgrade, since each maintained version already
supports both the TYPO3 version it targets and its predecessor at the
same time, see :ref:`UpgradingCompatibilityDataCompatibility` for why.
This tool exists for the case where that avoidable step happened
anyway, most often through a hosting provider's own automated upgrade
tooling. The Number Of Children Fixer is not upgrade-specific; it
repairs a maintained counter that can drift from unrelated
cut/copy/paste or drag-and-drop edge cases at any time, upgrade or
not.

Neither tool needs to run automatically as part of an upgrade
pipeline. Run them, via CLI or a one-off Scheduler execution, only
after a known interference with ``tt_content`` such as the upgrade
scenario above, or when a specific report of a wrong ``colPos``
sentinel or a wrong ``tx_gridelements_children`` count cannot be
explained by normal backend editing. Both are idempotent: rerunning
either one against already-correct data changes nothing.

What they modify
^^^^^^^^^^^^^^^^^

.. list-table::
   :header-rows: 1
   :widths: 30 70

   * - Tool
     - Modifies
   * - Col Pos Fixer
     - ``tt_content.colPos``, unconditionally set back to ``-1`` for
       every row that currently has ``tx_gridelements_container > 0``.
       It does not touch the relation itself; if
       ``tx_gridelements_container`` was also lost, this tool cannot
       recover it.
   * - Number Of Children Fixer
     - ``tt_content.tx_gridelements_children`` on every
       ``gridelements_pi1`` container, recomputed from an actual count
       of rows whose ``tx_gridelements_container`` points at it.

See :ref:`DataConsistencyTools` for the exact commands, task classes
and SQL each tool runs.
