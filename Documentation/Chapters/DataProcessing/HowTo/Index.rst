.. include:: /Includes.rst.txt

How To
------

This guide outlines the configuration of the `GridChildrenProcessor`, its options, and examples of corresponding templates and partials for rendering grid-based layouts in TYPO3.

Modern Approach vs Traditional Method
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

The `GridChildrenProcessor` represents a modern, Fluid-based approach to handling grid layouts in TYPO3, offering significant advantages over the traditional method using the Gridelements plugin:

**Traditional Method:**
The original Gridelements implementation relied on the `Gridelements->main` userFunc in TypoScript, which processed grid children internally and rendered them using pre-defined TypoScript objects (COA, TEMPLATE). This method required extensive TypoScript configuration for customization and made it difficult to separate content structure from presentation.

**Modern Approach with GridChildrenProcessor:**
The DataProcessor approach leverages TYPO3's ContentObjectRenderer data processing capabilities to:

* Separate data gathering from rendering logic
* Allow full Fluid template control over the grid structure
* Provide specific rendering options per grid layout type
* Enable recursive processing of nested grids with clean template code
* Offer greater flexibility through configuration options like `respectColumns` and `respectRows`
* Integrate seamlessly with other TYPO3 data processors

This modern approach significantly improves maintainability, template reusability, and follows TYPO3's current best practices for content rendering.

Example Configuration
^^^^^^^^^^^^^^^^^^^^^

Below is an example `GridChildrenProcessor` configuration:

.. code-block:: typoscript

   lib.gridelements {
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
                   options {
                       sortingDirection = asc
                       sortingField = sorting
                       recursive = 0
                       resolveFlexFormData = 1
                       resolveChildFlexFormData = 1
                       resolveBackendLayout = 1
                       respectColumns = 1
                       respectRows = 1
                   }
               }
           }
       }
   }

---

Templates and Partials
^^^^^^^^^^^^^^^^^^^^^^

The templates and partials used with `GridChildrenProcessor` extend TYPO3's grid-based rendering functionality. Samples are provided below for each template and partial used in the configuration.
Those samples are provided by Gridelements out of the box, but you can of course still create your own templates with less conditions, if your layouts are strictly bound to a certain configuration.
Just add those settings to the TypoScript template based on your Gridelements backend layouts and configure them to your needs.

.. code-block:: typoscript

    10 = GridElementsTeam\Gridelements\DataProcessing\GridChildrenProcessor
    10 {
        default {
            [...]
        }
        myLayout1 {  # Custom key for this layout
            as = children
            options {
                sortingField = title  # Sort child elements alphabetically by their title
                sortingDirection = desc  # Change sorting direction to descending
                recursive = 0  # Disable recursive processing of child elements
                resolveFlexFormData = 0  # Keep original FlexForm XML without conversion
                resolveBackendLayout = 0  # Disable resolution of backend layouts
                respectColumns = 0  # Skip grouping entirely
            }
        }
        myLayout2 {  # Custom key for this layout
            as = somethingElse  # use a different key to deal with your child elements
            options {
                recursive = 1  # Disable recursive processing of child elements
                respectColumns = 1  # Group children by their column value
                respectRows = 0  # Skip grouping by rows i. e. with a single row configuration
            }
        }
    }


**Template: GridElement.html**
""""""""""""""""""""""""""""""

This is the main entry template for rendering the grid layout.

.. code-block:: html

   <html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers" data-namespace-typo3-fluid="true">
       <f:layout name="Default"/>
       <f:section name="Main">
           <f:render partial="Container" arguments="{_all}"/>
       </f:section>
   </html>


**Partial: Container.html**
"""""""""""""""""""""""""""

The `Container` partial is the central rendering controller. It uses the switches `respectColumns` and `respectRows` to dynamically determine whether to render content hierarchically by rows, columns, or as a flat list of child elements.

If **`respectColumns`** is enabled:
- Child elements are grouped into columns according to their `tx_gridelements_columns` values.
- Each column group is rendered by the `Columns` partial.

If **`respectRows`** is also enabled:
- Child elements are first grouped into rows based on the layout configuration.
- Within each row, child elements are further grouped into their respective columns, using the `Rows` and `Columns` partials.

If neither switch is enabled:
- All child elements are rendered as a flat list using the `Child` partial.


.. code-block:: html

   <html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers" data-namespace-typo3-fluid="true">
       <div class="grid-container grid-container-{data.tx_gridelements_backend_layout}">
           <f:if condition="{options.respectColumns}">
               <f:then>
                   <f:if condition="{options.respectRows}">
                       <f:then>
                           <f:render partial="Rows" arguments="{data: data, rows: children, options: options, settings: settings}"/>
                       </f:then>
                       <f:else>
                           <f:render partial="Columns" arguments="{data: data, columns: children, options: options, settings: settings}"/>
                       </f:else>
                   </f:if>
               </f:then>
               <f:else>
                   <f:if condition="{children}">
                       <f:for each="{children}" as="child">
                           <f:render partial="Child" arguments="{data: child.data, children: child.children, options: options, settings: settings}"/>
                       </f:for>
                   </f:if>
               </f:else>
           </f:if>
       </div>
   </html>


**Partial: Rows.html**
""""""""""""""""""""""

This partial organizes child elements into rows and delegates further rendering to the `Columns` partial if `respectColumns` is enabled.

.. code-block:: html

   <html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers" data-namespace-typo3-fluid="true">
       <f:if condition="{rows}">
           <f:for each="{rows}" as="columns" key="rowNumber">
               <div id="c{data.uid}-{rowNumber}" class="grid-row grid-row-{rowNumber}">
                   <f:render partial="Columns" arguments="{data: data, columns: columns, options: options, settings: settings}"/>
               </div>
           </f:for>
       </f:if>
   </html>


**Partial: Columns.html**
"""""""""""""""""""""""""

Handles rendering of columns by grouping child elements into respective `colPos` values. Child elements within each column are delegated to the `Child` partial.

.. code-block:: html

   <html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers" data-namespace-typo3-fluid="true">
       <f:if condition="{columns}">
           <f:for each="{columns}" as="column" key="columnNumber">
               <div id="c{data.uid}-{columnNumber}" class="grid-column grid-column-{columnNumber}">
                   <f:for each="{column}" as="child">
                       <f:render partial="Child" arguments="{data: child.data, children: child.children, options: options, settings: settings}"/>
                   </f:for>
               </div>
           </f:for>
       </f:if>
   </html>


**Partial: Child.html**
"""""""""""""""""""""""

Renders individual child elements. If the child element itself is a container, this partial recursively calls the `Container` partial to render its children.

.. code-block:: html

   <html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers" data-namespace-typo3-fluid="true">
       <f:if condition="{children}">
           <f:then>
               <a id="c{data.uid}"></a>
               <f:if condition="{data._LOCALIZED_UID}">
                   <a id="c{data._LOCALIZED_UID}"></a>
               </f:if>
               <f:render partial="Container" arguments="{_all}" optional="true"/>
           </f:then>
           <f:else>
               <f:if condition="{data.CType}">
                   <f:cObject typoscriptObjectPath="tt_content.{data.CType}" data="{data}" table="tt_content" />
               </f:if>
           </f:else>
       </f:if>
   </html>

---

Advantages
^^^^^^^^^^

- **Flexible Layout Management:** Powerful control over rendering child elements as rows, columns, or lists. Adjust processing dynamically with `respectRows` and `respectColumns`.
- **Dynamic Nesting:** Support for nested grid structures enables hierarchical layouts. The `recursive` option makes it easy to process children of children endlessly.
- **Adaptable Templates:** Templates and partials reflect layout variations, allowing developers to customize rendering for unique use cases.
- **Streamlined Processing:** Integrates with TYPO3's `dataProcessing` to combine FlexForm data, sorting, and grouping in a single pipeline.