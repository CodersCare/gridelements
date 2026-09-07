.. include:: /Includes.rst.txt

.. _DataProcessing:
.. _typoscript-dataprocessing:

==============
DataProcessing
==============

DataProcessing is the recommended way to render a Grid Element's
children. See :ref:`RenderingArchitecture` for how it fits alongside
the deprecated legacy TypoScript path; this page is about using it.

``GridElementsTeam\Gridelements\DataProcessing\GridChildrenProcessor``
is a standard TYPO3 ``DataProcessorInterface`` implementation. Add it
to a content object's ``dataProcessing`` array like any other
processor, and it fetches the current container's children, the
``tt_content`` records related to it through ``tx_gridelements_container``
(see :ref:`DataModel`), resolves their FlexForm and backend layout
data as configured, and hands the result to Fluid as a plain variable.
What that variable looks like, a flat list or a rows/columns matrix
grouped by the container's own grid layout, is controlled by its
options, see :ref:`DataProcessingReference`. How to configure and use
those options, and the Fluid partials that consume the result, is
covered in :ref:`DataProcessingHowTo`.

Grid Elements ships a ready-to-use static TypoScript template,
**Gridelements w/DataProcessing**, that wires up
``GridChildrenProcessor`` on a ``FLUIDTEMPLATE`` for
``tt_content.gridelements_pi1``:

.. code-block:: typoscript

   tt_content.gridelements_pi1 =< lib.contentElement
   tt_content.gridelements_pi1 {
       templateName = GridElement
       templateName.override.field = tx_gridelements_backend_layout
       templateRootPaths {
           1 = EXT:gridelements/Resources/Private/Templates/
       }

       partialRootPaths {
           1 = EXT:gridelements/Resources/Private/Partials/
       }

       dataProcessing {
           10 = GridElementsTeam\Gridelements\DataProcessing\GridChildrenProcessor
           10 {
               default {
                   as = children
                   # Default options of the grid children processor
                   # Change them according to the needs of your layout
                   # See the Reference page for the full option list
                   # options {
                   #    sortingDirection = ASC
                   #    sortingField = sorting
                   #    recursive = 0
                   #    resolveFlexFormData = 1
                   #    resolveChildFlexFormData = 1
                   #    resolveBackendLayout = 1
                   #    respectColumns = 1
                   #    respectRows = 1
                   #}
               }
           }
       }
   }

   lib.tt_content.shortcut.pages = COA
   lib.tt_content.shortcut.pages {
       5 = LOAD_REGISTER
       5 {
           tt_content_shortcut_recursive.field = recursive
       }

       10 = USER
       10 {
           userFunc = GridElementsTeam\Gridelements\Plugin\Gridelements->user_getTreeList
       }

       20 = CONTENT
       20 {
           table = tt_content
           select {
               pidInList.data = register:pidInList
               selectFields.dataWrap = *,FIND_IN_SET(pid,{register:pidInList}) AS gridelements_shortcut_page_order_by
               where = colPos >= 0
               languageField = sys_language_uid
               orderBy = gridelements_shortcut_page_order_by,colPos,sorting
           }
       }

       30 = RESTORE_REGISTER
   }

   tt_content.shortcut.variables.shortcuts {
       tables := addToList(pages)
       conf.pages < lib.tt_content.shortcut.pages
   }

This is a normal ``FLUIDTEMPLATE`` setup with template, layout and
partial root paths; ``GridChildrenProcessor`` fetches the children, and
anything else added to this setup follows regular TypoScript syntax,
including other data processors run alongside it. The commented block
shows the available options with their default values, see
:ref:`DataProcessingReference` for what each one does. Use the
``f:debug`` view helper in a template to inspect what a given
configuration actually produces:

.. code-block:: html

   <f:debug>{_all}</f:debug>

Every option and internal key is also passed through ``stdWrap``, so
values can be assigned dynamically rather than only as TypoScript
constants.

For how this processor's option model compares to EXT:container's own
``ContainerProcessor``, including which option configuration
approximates its behavior, see :ref:`GridElementsAndContainer`.

.. toctree::
   :maxdepth: 5
   :titlesonly:
   :glob:

   HowTo/Index
   Reference/Index
