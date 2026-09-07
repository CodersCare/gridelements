.. include:: /Includes.rst.txt

.. _GridDefinitions:

================
Grid Definitions
================

A Grid Element's rows, columns and cells do not exist until an
integrator defines them. This section covers how that definition
happens: the syntax that describes a grid's structure, the visual tool
that authors it, the additional properties a grid layout can carry
through FlexForm, and the TSconfig options that tie all of it together.

This is definition-time configuration. It determines what structural
areas exist and what may be placed in them. It does not cover how
editors fill those areas with content, which is the Editor Guide, or
how the resulting structure reaches the frontend, see :ref:`Rendering`.

How the pieces fit together
^^^^^^^^^^^^^^^^^^^^^^^^^^^^

.. code-block:: text

   Grid structure (rows, columns, cells, restrictions)
       |
       +-- written as Grid TS Syntax ............ see Grid TS Syntax
       |
       +-- authored visually with the Grid Wizard  see Grid Wizard
       |       (the wizard reads and writes the same TSconfig)
       |
       +-- delivered to TYPO3 as TSconfig ........ see TSconfig
       |
       +-- optionally extended with configurable
           properties through FlexForm ........... see FlexForm

The Grid Wizard and Grid TS Syntax describe the same structural
concept from two directions, one visual, one textual, not two
unrelated configuration systems. A layout built with the wizard is
TSconfig; TSconfig typed by hand opens in the wizard as the same
structure. FlexForm configuration is a separate, optional layer: it
adds configurable properties to a grid layout, it does not itself
define rows, columns or the parent-child relation, see :ref:`DataModel`
for how that relation is actually stored.

Because a grid definition is TSconfig, it is plain text: it can live
in a project's version control alongside the rest of its
configuration, be diffed and reviewed like code, and be reused across
backend layouts or projects without re-entering it by hand.

.. toctree::
   :maxdepth: 1
   :titlesonly:

   GridTsSyntax/Index
   GridWizard/Index
   Flexform/Index
   Tsconfig/Index
