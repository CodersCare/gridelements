[![Latest Stable Version](https://poser.pugx.org/gridelementsteam/gridelements/v/stable)](https://extensions.typo3.org/extension/gridelements/)
[![TYPO3 12](https://img.shields.io/badge/TYPO3-12-orange.svg?style=flat-square)](https://get.typo3.org/version/12)
[![TYPO3 13](https://img.shields.io/badge/TYPO3-13-orange.svg?style=flat-square)](https://get.typo3.org/version/13)
[![TYPO3 14 Priority Access](https://img.shields.io/badge/TYPO3-14%20Priority%20Access-blue.svg?style=flat-square)](https://coders.care/for/crowdfunding/gridelements)
[![Total Downloads](https://poser.pugx.org/gridelementsteam/gridelements/d/total)](https://packagist.org/packages/gridelementsteam/gridelements)
[![Monthly Downloads](https://poser.pugx.org/gridelementsteam/gridelements/d/monthly)](https://packagist.org/packages/gridelementsteam/gridelements)

# TYPO3 extension `gridelements`

**Gridelements sets the benchmark for grid-based content in TYPO3.** It was
built by one of the inventors and developers of the Core backend layouts that
have shipped with TYPO3 since 4.5, as the logical next step from them. It brings
that same proven grid concept from the page level down to the content itself,
turning any content element into a nestable grid container that editors build,
rearrange and reference directly in the page module.

### For power users

Advanced drag & drop, including the Drag-In Wizard, assembles even complex
layouts in seconds. Paste as Reference reuses existing content instead of
duplicating it, keeping the whole structure slim. Collapse and Expand tidies
away columns in the page module and container elements in the list module, and
each user's open and closed state is remembered, so nothing is ever in the way.

### For integrators

Grids are defined in TypoScript or backend layout records and ship their own
frontend rendering, from accordions and tabs to galleries, without writing a
single line of PHP to create elements.

### For enterprise

Governance is built in. Allowed and disallowed content types per grid element
and per column, together with minimum and maximum item counts, let editorial
teams work inside clear guard rails while agencies roll out one consistent
layout system across dozens of sites. Grid definitions carry over between
versions unchanged, and Gridelements has been maintained and developed for
15 years.

Get it from the [TER](https://extensions.typo3.org/extension/gridelements) or
with `composer require gridelementsteam/gridelements`. Gridelements 14 for
TYPO3 13 and 14 is available through [Priority Access](https://coders.care/for/crowdfunding/gridelements).

## Version matrix

| Gridelements | TYPO3               | PHP                   | Status | Availability    |
|--------------|---------------------|-----------------------|--------|-----------------|
| 14           | 13.4 LTS / 14.3 LTS | 8.3 / 8.4 / 8.5       | Stable | Priority Access |
| 13           | 12.4 LTS / 13.4 LTS | 8.2 / 8.3 / 8.4       | Stable | TER             |
| 12           | 11.5 LTS / 12.4 LTS | 8.1 / 8.2 / 8.3 / 8.4 | Stable | TER             |

|                  | URL                                                                |
|------------------|--------------------------------------------------------------------|
| **Repository:**  | https://github.com/CodersCare/gridelements                         |
| **Read online:** | https://docs.typo3.org/p/gridelementsteam/gridelements/main/en-us/ |
| **TER:**         | https://extensions.typo3.org/extension/gridelements                |

<!-- Markdown link & img dfn's -->
[coders.care-url]: https://coders.care/for/crowdfunding/gridelements
[patreon-url]: https://www.patreon.com/cybercraft
[paypal-url]: https://www.paypal.me/cybercraftsponsoring/150
[amazon-url]: https://www.amazon.de/gp/registry/wishlist/2I80GX9ZSMYXX
[blog-url]: https://coders.care/blog/article/service-level-agreements-for-typo3-extensions

## How Gridelements is funded

Gridelements is licensed under the GPL, and every version becomes publicly available in the TER. Development, maintenance, testing and support for new TYPO3 releases are paid work.

That work is funded by the people who use Gridelements commercially. If it is part of how you deliver projects, funding it is what keeps it current.

### Priority Access and the two-stage release

Every Gridelements version is released in two stages. It first goes to Priority Access, where sponsors fund the development and work with the current version on the current TYPO3 release straight away. After the next TYPO3 LTS version has been shipped, that same version becomes publicly available for free in the TER.

Priority Access is therefore not a paywall. It funds the version that the whole community receives later, and it keeps Gridelements aligned with every new TYPO3 LTS release.

### Ways to contribute

|   |                                                                                                                                                                                                              |
|:--|:-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| [![CodersCareLogo](Documentation/Images/Sponsoring/CodersCareLogo.png)][coders.care-url] | **Priority Access.** Fund development directly and work with the current version for the current TYPO3 release. |
| [![PatreonLogo](Documentation/Images/Sponsoring/PatreonLogo.png)][patreon-url] | **Patreon.** Monthly support. Depending on the tier, this includes a mention in the release notes and the option to put a feature request on the roadmap.                                                    |
| [![PaypalLogo](Documentation/Images/Sponsoring/PaypalLogo.png)][paypal-url] | **PayPal.** One-off contributions of any amount.                                                                                                                                                             |

Beyond that, Joey and Petra appreciate a good single malt. [Wishlist][amazon-url]. Slàinte mhath!

## The Agreement

Excerpt from the coders.care blog post [Service Level Agreements for TYPO3 Extensions][blog-url]

![Big Orange rope pulling several colorful small ropes](Documentation/Images/Sponsoring/Why.jpg)
### Enabling companies, developers and the community to join forces and thrive
There is one particular thing, that should be different to most of the variants of service level agreements provided by other open-source projects though. Having to buy a so called "enterprise" or "professional" edition of the extensions or TYPO3 itself just to become entitled for an SLA is a No-Go, since it will create two classes in the community and contradict the principles of free software implied by the GPL.

The benefit for the people agreeing to a certain service level should be defined by reliability and responsiveness, and by earlier access rather than exclusive access. Every improved version still becomes public for the whole community in the end. Sponsors simply receive it sooner, depending on the level and the priority they paid for.

For developers there is the need for another agreement: They have to accept and publish fixes and changes to their extensions up to a certain degree, so the whole pool of developers can take care of the extensions covered by the SLAs. This will avoid forks.

There are several nice side effects of these agreements. For example it would reduce the number of extensions which are maintained by a single person and therefore the risk of loss when using these extensions. Due to the four-eyes principle this would increase the quality of each extension in the approved pool and at the same time reduce the amount of "me too" extensions in the TER.

There would be a powerful team of developers backing the service levels, so it would be easy to keep the approved extensions on a level with upcoming versions of the TYPO3 core. And since this would be done in close collaboration with the TYPO3 core team and the security team, core bugs and security holes affecting extension behaviour could be fixed and published much more easily as well.
