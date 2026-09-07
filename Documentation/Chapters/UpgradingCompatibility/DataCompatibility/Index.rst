.. include:: /Includes.rst.txt

.. _UpgradingCompatibilityDataCompatibility:

===========================================
Data Compatibility Across TYPO3 Versions
===========================================

The one piece of Grid Elements' data model that is genuinely at risk
during a TYPO3 version upgrade, rather than only during ordinary
editing, is ``colPos``.

The ``colPos`` issue
^^^^^^^^^^^^^^^^^^^^^

This is exclusively a database schema matter, not a TCA one.
``tt_content.colPos`` has two competing SQL column definitions,
supplied by two different extensions' ``ext_tables.sql``:

.. list-table::
   :header-rows: 1
   :widths: 30 70

   * - Definition
     - SQL
   * - TYPO3 Core (``typo3/cms-frontend``)
     - ``colPos int(11) unsigned DEFAULT '0' NOT NULL``
   * - Grid Elements
     - ``colPos int(11) DEFAULT '0' NOT NULL``

Grid Elements' own ``ext_tables.sql`` deliberately declares the same
column without ``unsigned``, because it depends on two negative
sentinel values, ``-1`` for a grid child and ``-2`` for an unused
element, to place content outside the page's own Backend Layout
columns, see :ref:`DataModel` for exactly what each value means and
why. TCA plays no role in this at all; TCA describes how a field is
edited and validated in the backend, not the SQL type of the database
column that stores it. The signed/unsigned distinction is decided
entirely by which ``ext_tables.sql`` definition for this column ends
up applied to the database.

Which definition wins
^^^^^^^^^^^^^^^^^^^^^^

TYPO3's schema migration (Admin Tools > Maintenance, ``typo3
database:updateschema``, or equivalent deployment tooling) collects
the ``ext_tables.sql`` of every currently active extension and
combines them into the set of column definitions it compares the live
database against. As long as Grid Elements is active during that step,
its unsigned-free ``colPos`` definition is part of that combined set,
alongside Core's ``unsigned`` one, and Grid Elements' definition is
what actually applies to the column, keeping it signed.

The failure mode only exists if Grid Elements is *not* active during
that step: with its ``ext_tables.sql`` absent from the combined set,
only Core's ``unsigned`` definition remains, the schema migration then
sees the live column as merely differing from that definition, and
applying it converts the column back to unsigned. **This conversion
must never be allowed to happen.** If it does, every negative sentinel
value is lost: ``colPos`` on affected grid children can no longer
record that they are placed in a Grid Element column (``-1``) or
unused (``-2``), so TYPO3 Core's own placement handling and backend
presentation, both of which read ``colPos`` directly, interpret those
records incorrectly, typically as if they were ordinary page-level
elements. The parent-child relation itself, stored in
``tx_gridelements_container``, is not affected by this and continues
to identify each record as a child of its Grid Element; only what
``colPos`` displays about it in the Page and List modules is wrong.
This is exactly the scenario the Col Pos Fixer's own docblock
describes, see :ref:`UpgradingCompatibilityUpgradeWizards`.

There is no need to ever deactivate Grid Elements for a TYPO3 Core
major-version upgrade
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Deactivating, removing, or otherwise taking Grid Elements out of the
picture "for the duration of the upgrade" is not a required or
sensible step, and doing it is what actually creates the risk
described above; it is not something a Core upgrade forces on a
project. Each currently maintained Grid Elements version is built to
support both the TYPO3 version it targets and that version's immediate
predecessor at the same time. This branch's ``composer.json`` states
that directly: ``"typo3/cms-core": "^12.4 || ^13.4.7"``. The same
installed Grid Elements version is already compatible before, during
and after a project runs ``composer update`` to move its TYPO3 Core
requirement from ``^12.4`` to ``^13.4.7``. There is consequently no
point in that upgrade where Grid Elements needs to be absent, and no
reason for its ``ext_tables.sql`` to ever drop out of the combined
schema definition set. Where this has gone wrong in practice, it has
been a hosting provider's own automated upgrade process deactivating
extensions it did not recognize as compatible without checking their
actual supported-version range, not a limitation of Grid Elements
itself or a real requirement of the TYPO3 upgrade process.

Keep Grid Elements active throughout the upgrade, and this entire
failure mode does not arise. **Back up ``tt_content`` before a TYPO3
Core major-version upgrade regardless**, as ordinary precaution. If
the column does get converted back to unsigned, for example because a
provider's tooling deactivated the extension without being asked to,
restore the ``colPos`` sentinel with the Col Pos Fixer described at
:ref:`DataConsistencyTools`, which can repair the sentinel for any row
that still has its ``tx_gridelements_container`` intact. It cannot
recover the relation itself if that was also lost; a backup is the
only recovery path for that case.

Applying schema migration suggestions safely
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Keeping Grid Elements active removes the cause of this specific
problem, but the schema migration step itself deserves a general
safety rule regardless, for ``colPos`` and every other field alike.
TYPO3's schema migrator always groups its suggestions into distinct
kinds, both in Admin Tools > Maintenance > Analyze Database Structure
and in ``typo3 database:updateschema``: adding new tables, fields and
indexes; changing existing ones; and removing ones no active extension
declares anymore. Adding is always safe to apply without reviewing
each suggestion individually, since it only creates something that did
not exist before and cannot destroy existing data.

**Changing or removing a field or table should be the very last thing
applied during an upgrade, and only after reviewing exactly what each
suggestion does; never apply a batch of "change" or "remove"
suggestions unread.** This is precisely the mechanism that would
revert ``colPos`` to unsigned: once Core's is the only active
definition for that column, it does not appear as a new field to add,
it appears as a suggested *change* to an existing one. A project that
reviews change suggestions individually, rather than accepting all of
them at once, would see and reject exactly that change to ``colPos``,
independent of whether Grid Elements stayed active throughout the
upgrade.

What is, and is not, version-specific here
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

This branch supports TYPO3 ``^12.4 || ^13.4.7`` simultaneously, not
one or the other. Grid Elements' signed ``colPos`` schema definition
and the ``DataHandler`` logic that depends on it are the same code for
both supported versions; there is no version-specific ``colPos``
behavior within the range this branch targets, and no version-specific
reason to deactivate it either. The risk described above is entirely a
consequence of Grid Elements being made inactive during a schema
migration step; it is unrelated to, and not required by, moving
between any two TYPO3 Core versions this branch already supports.
