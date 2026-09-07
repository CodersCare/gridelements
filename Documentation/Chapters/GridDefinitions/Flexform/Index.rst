.. include:: /Includes.rst.txt

.. _Flexform:


FlexForm
--------

A grid definition, written as :ref:`Grid TS Syntax <GridTsSyntax>` or
built with the :ref:`Grid Wizard <GridWizard>`, describes a layout's
rows, columns and restrictions. It does not describe any configurable
behaviour a Grid Element using that layout should offer beyond its
structure, options an editor sets once per Grid Element instance, such
as a display variant or a note for other editors. That is what the
**FlexForm Configuration** field on a CE backend layout record is for.

FlexForm configuration is additive, not structural. It never defines
rows, columns or restrictions, and it plays no part in the
parent-child relation described in :ref:`DataModel`. A Grid Element
with no FlexForm configuration at all still fully supports children,
nesting and restrictions; FlexForm only adds fields to that element's
own editing form and, from there, to its own data.

What to put in a FlexForm
^^^^^^^^^^^^^^^^^^^^^^^^^^

Anything unrelated to the relation between a container and its
children can go in a Grid Element's FlexForm configuration, using the
same syntax as any other FlexForm field:

- Checkboxes or radio buttons to toggle behaviour.
- Selectors to pick a rendering variant of the Grid Element in the
  frontend.
- Input fields for additional configuration values.
- Text areas for internal notes to other editors.

Everything defined in the data structure shows up as fields in the
Grid Element's own edit form, under **Content Element Configuration**.

FlexForm data structures use the same XML format as elsewhere in
TYPO3, including the format TemplaVoila-based content elements use,
which can make copying an existing data structure a useful starting
point when migrating such elements to Grid Elements.

.. note::
   FlexForm **sections**, repeatable groups of fields, are not
   resolved by either rendering path's FlexForm handling. Fields
   outside a section are read and made available as described below;
   fields inside a section are not.

How a layout's data structure is resolved
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

A Grid Element's data structure is resolved per grid layout, not
globally. On a ``tt_content`` record with ``CType = gridelements_pi1``,
the extension's
``BeforeFlexFormDataStructureIdentifierInitializedListener`` and
``BeforeFlexFormDataStructureParsedListener`` PSR-14 listeners look up
the record's selected layout through :ref:`LayoutSetup
<RenderingLayoutSetup>`, the same shared parsing layer rendering
depends on, and resolve its data structure from one of two CE backend
layout record fields:

* **pi_flexform_ds_file**, a data structure referenced as a file, either
  a TYPO3 file reference or a plain file path.
* **pi_flexform_ds**, a data structure entered directly as XML.

``pi_flexform_ds_file`` takes precedence when both are set. A layout
defined purely through TSconfig, without any CE backend layout record,
can set the same value using the ``flexformDS`` key under
``tx_gridelements.setup.<id>``, see :ref:`TSconfig`; ``LayoutSetup``
maps it onto ``pi_flexform_ds`` internally, so both paths resolve the
same way.

Reading FlexForm values in the frontend
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

When resolving FlexForm data is enabled, both rendering paths, see
:ref:`RenderingArchitecture`, convert a Grid Element's ``pi_flexform``
value into individual fields, each prefixed with **flexform_** so it
cannot collide with any of the record's own field names, and make them
available on the record like any other field. In the recommended
DataProcessing path this happens through
``GridChildrenProcessor``'s ``resolveFlexFormData`` and
``resolveChildFlexFormData`` options, documented in
:ref:`DataProcessingReference`.
