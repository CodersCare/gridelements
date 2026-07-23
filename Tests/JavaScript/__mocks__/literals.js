// Real TYPO3 core implementation escapes each interpolation via CSS.escape(), which jsdom does
// not implement. Test fixtures only ever interpolate plain identifiers, so a bare passthrough
// is sufficient here without pulling in a CSS.escape polyfill.
export const selector = (strings, ...values) => String.raw({ raw: strings }, ...values);
