.. include:: /Includes.rst.txt

.. _Rendering:

=========
Rendering
=========

Everything a template needs to render a Grid Element's children, the
parent-child relation, the cell each child occupies, the grid's row
and column layout, already exists once an editor has built the
structure in the backend, see :ref:`DataModel` and :ref:`Nesting`.
Rendering does not create or define that structure. It reads it.

This section covers how a Grid Element's stored structure reaches the
frontend output:

.. toctree::
   :maxdepth: 1
   :titlesonly:

   Architecture/Index
   DataProcessing/Index
   Typoscript/Index
