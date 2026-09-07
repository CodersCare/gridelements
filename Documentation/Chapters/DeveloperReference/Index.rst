.. include:: /Includes.rst.txt

.. _DeveloperReference:

=====================
Developer Reference
=====================

The preceding sections describe Grid Elements from an editor's or an
integrator's point of view: how a grid structure is defined
(:ref:`GridDefinitions`), how its data is stored (:ref:`DataModel`),
how editors work with it (:ref:`EditorGuide`), and how it reaches
frontend output (:ref:`Rendering`). This section is for a different
audience: developers extending Grid Elements itself, writing another
extension that needs to cooperate with it, or maintaining a project
that XCLASSes or overrides part of TYPO3's backend and needs to know
where Grid Elements already touches that same code.

Grid Elements is implemented almost entirely through TYPO3 Core's
PSR-14 events and a small number of legacy ``$GLOBALS`` hooks that
Core has not replaced with events for the relevant operations yet.
XCLASSing is exceptional and limited to a single, explicitly opt-in
case. :ref:`DeveloperReferenceExtensionPoints` catalogs all of these
by their actual current class and event names, and states plainly
where an older hook has been superseded rather than presenting it as
current.

Where to go next
^^^^^^^^^^^^^^^^^

.. list-table::
   :header-rows: 0
   :widths: 30 70

   * - :ref:`DeveloperReferenceExtensionPoints`
     - PSR-14 events Grid Elements listens to and dispatches, the
       remaining legacy hooks, and its one XCLASS.
   * - :ref:`DeveloperReferenceListModuleIntegration`
     - How Grid Elements optionally integrates with TYPO3's Web > List
       module, and how that integration relates to (and stays
       distinct from) the Grid Elements parent-child relation and
       ``colPos``.

.. toctree::
   :maxdepth: 1
   :titlesonly:
   :hidden:

   ExtensionPoints/Index
   ListModuleIntegration/Index
