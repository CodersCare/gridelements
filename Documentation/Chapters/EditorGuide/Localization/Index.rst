.. include:: /Includes.rst.txt

.. _EditorGuideLocalization:

============
Localization
============

Translating a page's content works the same way for a Grid Element as
for any other content element, because the mechanism itself is TYPO3
Core's, not something Grid Elements replaces or adds to. What is worth
understanding is how that mechanism, connected or free localization,
interacts with a Grid Element's own structure and its children.

Translate versus Copy
^^^^^^^^^^^^^^^^^^^^^^

When translating a content element, TYPO3 offers two modes unless a
project has explicitly opted into mixing them on the same page
(``mod.web_layout.allowInconsistentLanguageHandling`` in Page
TSconfig):

* **Translate** creates *connected* records: each translation stays
  linked to its default-language source. Once a language column holds
  connected records, TYPO3 locks that column down: no new element can
  be created there, nothing can be pasted into it, and nothing can be
  dragged into or within it.
* **Copy** creates *free* records: independent translations with no
  link back to a source. A column of free records behaves exactly like
  a default-language column, with full drag and drop, paste, and
  new-element creation.

This choice is made once, per page and language, through TYPO3's own
translation tooling; it is not a Grid Elements setting, and Grid
Elements does not override or replace it. The lock on a connected
column is TYPO3 Core's own, applied to the column as a whole, and it
governs a Grid Element's own cells exactly as it governs a page
column: an editor who needs to rearrange a Grid Element's children in
a translated language needs that language in free (Copy) mode; a
connected (Translate) language keeps the structure locked to whatever
the default language already has.

Translating a Grid Element and its children
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

A Grid Element's children are ordinary content records related to it
through a dedicated parent-child relation, not through their position
on the page, see :ref:`DataModel`. Nothing about that relation is
specific to one language: a Grid Element and each of its children have
their own translation state, exactly as any other content element
would.

Two places in the backend deal with translating a Grid Element's
children:

* The container's own edit form lists its children and offers TYPO3's
  usual localization controls there, letting an editor localize or
  synchronize individual children directly from the container.
* When a whole page's content is translated at once through TYPO3's
  page-wide translation tool, a Grid Element and its children are
  offered as a single unit rather than as separate top-level entries;
  a child already accounted for through its container is not listed
  again on its own. An editor does not need to individually select a
  container's children in that tool for them to be included.

Because none of this depends on any Grid Elements-specific TCA
override, and both mechanisms build directly on TYPO3 Core's own
inline-relation and page-translation handling, this behavior can be
relied on across TYPO3 versions that support Grid Elements' underlying
requirements. It is not something a future TYPO3 upgrade is expected
to change without a corresponding Core change to connected/free
localization itself.

For the architectural difference between Grid Elements and
EXT:container localization, including why Grid Elements' relation
model is one of the few places "Core-native" is a technically
meaningful description, see :ref:`GridElementsAndContainer`.
