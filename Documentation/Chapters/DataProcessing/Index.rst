.. include:: /Includes.rst.txt

.. _typoscript-dataprocessing:


DataProcessing
--------------

This is the default TypoScript setting provided while including the
special **Gridelements w/DataProcessing** setup in your TS template editor:

::

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
                # Read more about it in the TypoScript section of the manual
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



As you can see, it is based on a usual FLUIDTEMPLATE with template, layout and partial paths.
It makes use of the GridChildrenProcessor to fetch children from the database.
Additionally this processor provides some internal keys to define the processing setup.
Anything else you want to use will be based on the official TypoScript syntax, since like the built in
processors of the core, the GridChildrtenProcessor might contain other processors too, so you won't have
to hassle with any other extension specific parameters.

As described in the commented part, there are some default settings for
those parameters, that will be used if you don't set any values yourself.
Just use the the debug viewhelper in the dummy templates to get an overview of the different behaviours.

::
  <f:debug>{_all}</f:debug>

Any of the internal keys and the default settings will of course be
passed to the stdWrap method, so you can assign almost anything to any
part of your setup.

The two setups for the shortcut cObject are the same as for the well known default setup.


.. toctree::
   :maxdepth: 5
   :titlesonly:
   :glob:

   Reference/Index

