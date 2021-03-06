.. include:: /Includes.rst.txt

.. _GridWizard:


Grid Wizard
-----------

For those, who are not familiar with TypoScript or just prefer the
usability of a point and click interface, there is a comfortable Grid
Wizard that will help to create the TypoScript code.


Creating the basic grid structure
"""""""""""""""""""""""""""""""""

When you want to use this wizard just go to the  **Configuration** tab
of the layout record and you will find it within the editing form.
Since TYPO3 version 8 there is no popup window for that wizard anymore.
When this is a newly created record, the wizard will look like this:

.. figure:: ../../Images/GridWizard/CreateBasicGridStructureStep1.png
   :alt: Create basic grid structure step 1
   :width: 600
.. :align: center
.. :name: Create basic grid structure step 1


Otherwise it will show a visible representation of the structure
provided in the PageTS-Config area below.

Now you can click on the  **small arrows** at the right and at the
bottom to create the basic grid structure. down and right will increase the number
of columns and/or rows, up and left will decrease it. To get the example we have
been using for the :ref:`Grid TS Syntax <GridTsSyntax>`,
the basic grid would be looking like this:

.. figure:: ../../Images/GridWizard/CreateBasicGridStructureStep2.png
   :alt: Create basic grid structure step 2
   :width: 600
.. :align: center
.. :name: Create basic grid structure step 2


Spanning, naming and assigning cells
""""""""""""""""""""""""""""""""""""

Now you can deal with the cells that should be  **spanning multiple
columns and/or rows** . Therefor you just have to click on the
**triangle symbols beside the cells** you want to enlarge. You can
span  **right and down only** , since this resembles the way cells are
spanned in the HTML table used within the page module. Only when you
spanned a cell over at least one column and/or row, there will be
**additional triangles pointing to the left and up** , so that you can
**remove** the spanning by clicking on them.

To create the structure of the Grid TS example, you should click on
the right triangle of the upper left cell first until it spans the
whole row. Then you should click on the bottom triangle of the first
cell of the second row to have it span two rows. Finally you should
click on the right triangle of the second cell of the last row until
it spans the remaining three columns of the last row. Now the result
should be looking like this:

.. figure:: ../../Images/GridWizard/CreateBasicGridStructureStep3.png
   :alt: Create basic grid structure step 3
   :width: 600
.. :align: center
.. :name: Create basic grid structure step 3

Finally you should give the cells a  **name** and a number to
be used as the value for the internal colPos within a grid element
using this layout. And you should decide about the available content, list
and grid element types and maybe the maximum number of items for each cell.
If you don't set the **column number**, the cell will be a placeholder that can
not contain any element later on.
To edit the values for each cell, just click on the  **pencil within
the square** in the middle of each cell, fill in the values and save
them by clicking on the  **disk symbol** .

.. figure:: ../../Images/GridWizard/CreateBasicGridStructureStep4.png
   :alt: Create basic grid structure step 4
   :width: 600
.. :align: center
.. :name: Create basic grid structure step 4


Saving the layout to the CE backend layout record
"""""""""""""""""""""""""""""""""""""""""""""""""

Now that you have named and assigned each cell, the layout should be
looking like this:

.. figure:: ../../Images/GridWizard/CreateBasicGridStructureStep5.png
   :alt: Create basic grid structure step 5
   :width: 600
.. :align: center
.. :name: Create basic grid structure step 5


It will be saved and transformed into the PageTS-Config syntax below when you save the record.
Depending on the names and column values you have been using, the result should be close to the example we have
used in the :ref:`Grid TS Syntax <GridTsSyntax>` section. When
you open the wizard the next time, it will come up in the same state.

Of course you don't have to save the configuration as a record in the database, but you can copy it and paste it into a file to
include that just as any other usual PageTS-Config file.
