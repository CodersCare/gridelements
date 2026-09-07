.. include:: /Includes.rst.txt

.. _DataConsistencyTools:

======================
Data Consistency Tools
======================

Under normal operation, the ``DataHandler`` hooks described on
:ref:`ParentChildIrre` keep ``colPos`` and the
``tx_gridelements_children`` counter (see :ref:`DataModel`) consistent
automatically as editors work. The two tools on this page exist for
exceptional cases where those values nevertheless become inconsistent,
for example because an external process writes to ``tt_content``
outside a normal Grid Elements-aware save or because an edge case
bypasses the expected maintenance behavior. They are corruption-repair
tools for exceptional states, not a routine or scheduled maintenance
step.

Grid Elements Col Pos Fixer
^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Fixes grid children whose ``colPos`` is no longer ``-1``. Per its own
docblock, this is aimed at the specific case of a TYPO3 Core
major-version upgrade: if Grid Elements' own signed SQL schema
definition for ``colPos`` is not part of the active set a schema
migration compares against during that upgrade, TYPO3 Core's unsigned
definition can be applied to the column instead, which can affect the
negative sentinel values stored there, see
:ref:`UpgradingCompatibilityDataCompatibility` for the schema
mechanism itself. This tool restores the ``-1`` sentinel for any row
whose ``tx_gridelements_container`` relation is still intact.

The fixer does not try to detect which rows are broken. It
unconditionally reasserts the sentinel for every row that currently has
a container:

.. code-block:: sql

   UPDATE tt_content SET colPos = -1 WHERE tx_gridelements_container > 0

Grid Elements Number Of Children Fixer
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Fixes the maintained ``tx_gridelements_children`` counter described on
:ref:`DataModel`. Per its own docblock, this addresses drift caused by
buggy cut/copy/paste or drag-and-drop behavior in edge cases. For every
``gridelements_pi1`` container, it counts the container's actual
children directly (``tx_gridelements_container = <container uid>``) and
writes that count back, replacing whatever value the counter currently
holds.

Running the tools
^^^^^^^^^^^^^^^^^^

Both are available as TYPO3 CLI commands and as classic TYPO3
Scheduler task types, so either a CLI or a backend-scheduled workflow
can trigger the repair.

.. list-table::
   :header-rows: 1
   :widths: 34 33 33

   * - Tool
     - Console command
     - Scheduler task class
   * - Col Pos Fixer
     - ``vendor/bin/typo3 gridelements-col-pos-fixer``
     - ``GridElementsTeam\Gridelements\Task\GridelementsColPosFixer``
   * - Number Of Children Fixer
     - ``vendor/bin/typo3 gridelements-number-of-children-fixer``
     - ``GridElementsTeam\Gridelements\Task\GridelementsNumberOfChildrenFixer``

Neither tool needs to run on a schedule for Grid Elements to function
correctly. Reach for them after a known external interference with
``tt_content``, such as the upgrade scenario described above, or when
the ``tx_gridelements_children`` count or a grid child's ``colPos``
sentinel is known to be inconsistent and normal backend editing does
not correct it.
