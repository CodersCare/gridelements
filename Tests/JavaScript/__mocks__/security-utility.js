// Faithful reimplementation of TYPO3 core's SecurityUtility.stripHtml() (the only method
// grid-editor.js uses) - reaching into the sibling monorepo's vendor/ directory isn't an option
// since this extension is published as its own standalone repository.
export default class SecurityUtility {
    constructor(documentRef = document) {
        this.documentRef = documentRef;
    }

    stripHtml(value) {
        return new DOMParser().parseFromString(value, 'text/html').body.textContent || '';
    }
}
