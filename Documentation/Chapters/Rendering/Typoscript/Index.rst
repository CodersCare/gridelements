.. include:: /Includes.rst.txt

.. _LegacyTypoScriptRendering:

====================================
Legacy TypoScript Rendering
====================================

.. note::

   This rendering path is deprecated, not recommended for new
   projects. It is retained for existing projects that already render
   through it and remains fully functional. New implementations should
   use :ref:`DataProcessing` and Fluid instead, see
   :ref:`RenderingArchitecture` for how the two paths compare.

This is the default TypoScript setting provided while including the
legacy **Gridelements** setup in your TS template editor:

.. code-block:: typoscript

   lib.gridelements.defaultGridSetup {
       // stdWrap functions being applied to each element
       columns {
           default {
               renderObj = COA
               renderObj {
                   # You can use registers to e.g. provide different image settings for each column
                   # 10 = LOAD_REGISTER
                   20 =< tt_content

                   # And you can reset the register later on
                   # 30 = RESTORE_REGISTER
               }
           }

           #2 < .default
           #2 {
           #}
       }

       # if you want to provide your own templating, just insert a cObject here
       # this will prevent the collected content from being rendered directly
       # e.g. cObject = TEMPLATE or cObject = FLUIDTEMPLATE will be available from the core
       # the content will be available via field names like
       # tx_gridelements_view_columns (an array containing each column)
       # or tx_gridelements_view_children (an array containing each child)
       # tx_gridelements_view_column_123 (123 is the number of the column)
       # or tx_gridelements_view_child_123 (123 is the UID of the child)
   }

   tt_content.gridelements_pi1 = COA
   tt_content.gridelements_pi1 {
       #10 =< lib.stdheader
       20 = COA
       20 {
           10 = USER
           10 {
               userFunc = GridElementsTeam\Gridelements\Plugin\Gridelements->main
               setup {
                   default < lib.gridelements.defaultGridSetup
               }
           }
       }
   }

   tt_content.gridelements_view < tt_content.gridelements_pi1

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

As you can see, it is just based on the usual TypoScript and uses some
internal keys, like columns, default and renderObj to define the setup
for the columns. Anything else you want to use will be based on the
official TypoScript syntax, so no extension-specific parameters are
involved.

As described in the commented part, you will find some additional
virtual fields in your data, containing data gathered during the
rendering process. These come in handy, when you want
to use a TEMPLATE or FLUIDTEMPLATE element to produce your output.
Just use the debug view helper in your template to get an overview of
the available fields.

.. code-block:: html

   <f:debug>{_all}</f:debug>

Any of the internal keys and the default settings will of course be
passed to the stdWrap method, so you can assign almost anything to any
part of your setup.

The two setups for the shortcut cObject are used to render the
references properly, so changing them should be done only with a
clear understanding of their effect.

See :ref:`LegacyTypoScriptReference` for the full list of internal
keys and virtual fields this rendering path provides.

.. toctree::
   :maxdepth: 5
   :titlesonly:
   :glob:

   Reference/Index
