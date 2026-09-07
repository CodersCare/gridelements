.. include:: /Includes.rst.txt

.. _DataProcessingReference:

Reference
---------

See :ref:`RenderingArchitecture` for how ``GridChildrenProcessor`` fits
into rendering overall, and :ref:`DataProcessing` for how to configure
it. This page lists its TypoScript options and their defaults.

.. ### BEGIN~OF~TABLE ###


.. _typoscript-dataprocessing-reference:

TypoScript
^^^^^^^^^^


.. _typoscript-grid-children-processor:

setup
"""""

.. container:: table-row

   Property
         dataProcessing

   Data type
         array of class references by full namespace

   Description
         Add one or multiple processors to manipulate the $data variable of the currently rendered content object, like tt_content or page. The sub-property options can be used to pass parameters to the processor class.

   Default
         10 = GridElementsTeam\\Gridelements\\DataProcessing\\GridChildrenProcessor


.. _typoscript-dataprocessing-default:

dataProcessing.123.default
""""""""""""""""""""""""""

.. container:: table-row

   Property
         default

   Data type
         Internal

   Description
         The default setup used by any Grid Element layout that has not got its
         own setup available. Layouts are assigned by their identifier.
         Just provide individual blocks **myIdentifier{...}** for each layout.

   Default
         default


.. _typoscript-dataprocessing-default-as:

dataProcessing.123.default.as
"""""""""""""""""""""""""""""

.. container:: table-row

   Property
         as

   Data type
         Internal / stdWrap

   Description
         This will be the name of the variable filled with the output generated
         by the GridChildrenProcessor. You can access it via **{children}** from
         within your Fluid template.

   Default
         children


.. _typoscript-dataprocessing-default-options:

dataProcessing.123.default.options
""""""""""""""""""""""""""""""""""

.. container:: table-row

   Property
         options

   Data type
         Internal

   Description
         Can contain any of the following options to determine the behaviour
         of the GridChildrenProcessor.

   Default
         N/A


.. _typoscript-dataprocessing-default-options-sortingDirection:

dataProcessing.123.default.options.sortingDirection
"""""""""""""""""""""""""""""""""""""""""""""""""""

.. container:: table-row

   Property
         sortingDirection

   Data type
         String / stdWrap

   Description
         Determines the sorting direction of the database query for children.
         Only **desc** is treated specially; any other value, including
         **asc**, results in ascending sorting.

   Default
         asc


.. _typoscript-dataprocessing-default-options-sortingField:

dataProcessing.123.default.options.sortingField
""""""""""""""""""""""""""""""""""""""""""""""

.. container:: table-row

   Property
         sortingField

   Data type
         String / stdWrap

   Description
         Determines the sorting field of the database query for children.
         Can be any field name but you have to make sure that the field exists
         yourself, otherwise the query might fail.

   Default
         sorting


.. _typoscript-dataprocessing-default-options-recursive:

dataProcessing.123.default.options.recursive
""""""""""""""""""""""""""""""""""""""""""""

.. container:: table-row

   Property
         recursive

   Data type
         Integer / stdWrap

   Description
         Determines how many additional levels of a nested container's own
         children are pre-fetched within this single processing pass. This is
         a data-eagerness setting, not a structural depth limit; nesting
         itself is unconditional regardless of this value, see :ref:`Nesting`.
         The whole pre-fetched tree of children will be handed over to the
         variable as a data array.
         **Be aware of the fact that this ignores different setups of child elements**

   Default
         0


.. _typoscript-dataprocessing-default-options-resolveFlexFormData:

dataProcessing.123.default.options.resolveFlexFormData
""""""""""""""""""""""""""""""""""""""""""""""""""""""

.. container:: table-row

   Property
         resolveFlexFormData

   Data type
         Boolean / stdWrap

   Description
         Determines if FlexForms of containers should be resolved and assigned
         to virtual fields named **flexform\_my\_fieldname**.
         Setting this option to 0 will disable resolving of child FlexForms too, unless you explicitly allow them using resolveChildFlexFormData

   Default
         1


.. _typoscript-dataprocessing-default-options-resolveChildFlexFormData:

dataProcessing.123.default.options.resolveChildFlexFormData
"""""""""""""""""""""""""""""""""""""""""""""""""""""""""""

.. container:: table-row

   Property
         resolveChildFlexFormData

   Data type
         Boolean / stdWrap

   Description
         Determines if FlexForms of children should be resolved and assigned
         to virtual fields named **flexform\_my\_fieldname**.
         The default will be overridden by setting resolveFlexFormData to 0

   Default
         1


.. _typoscript-dataprocessing-default-options-resolveBackendLayout:

dataProcessing.123.default.options.resolveBackendLayout
"""""""""""""""""""""""""""""""""""""""""""""""""""""""

.. container:: table-row

   Property
         resolveBackendLayout

   Data type
         Boolean / stdWrap

   Description
         Determines if CE-BackendLayouts should be resolved. This is applied
         to the current Grid Element itself, not only to its children: its
         resolved layout information is required for processing the Grid
         structure, including its rows and columns. Child Grid Elements may
         then also have their Backend Layout information resolved, assigned
         to virtual fields named **tx\_gridelements\_backend\_layout\_resolved**.
         Use this information e.g. to generate CSS classes based on layout data.

   Default
         1


.. _typoscript-dataprocessing-default-options-respectColumns:

dataProcessing.123.default.options.respectColumns
"""""""""""""""""""""""""""""""""""""""""""""""""

.. container:: table-row

   Property
         respectColumns

   Data type
         Boolean / stdWrap

   Description
         Determines if layout columns should be recognized and assigned while
         rendering children. When enabled, **children** itself becomes a
         matrix indexed by the Grid Elements structural column identifiers,
         based on **tx\_gridelements\_columns**, instead of a flat list.

   Default
         1


.. _typoscript-dataprocessing-default-options-respectRows:

dataProcessing.123.default.options.respectRows
""""""""""""""""""""""""""""""""""""""""""""""

.. container:: table-row

   Property
         respectRows

   Data type
         Boolean / stdWrap

   Description
         Determines if layout rows should be recognized and assigned while
         rendering columns. When enabled, **children** itself is indexed by
         row instead of by column, and each row directly contains the
         column-indexed child arrays described under **respectColumns**.
         Sets respectColumns internally if not set.

   Default
         1


.. ###### END~OF~TABLE ######
