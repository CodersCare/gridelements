const store = new Map();

export default {
    isset: (key) => store.has(key),
    get: (key) => store.get(key),
    set: (key, value) => {
        store.set(key, value);
        return Promise.resolve();
    },
    /** test helper, not part of the real TYPO3 API */
    _reset: () => store.clear(),
};
