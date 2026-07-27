function makeChainable(calls) {
    const handler = {
        get(target, prop) {
            if (prop === 'then' || typeof prop === 'symbol') {
                return undefined;
            }
            return (...args) => {
                calls.push({ method: prop, args });
                return proxy;
            };
        },
    };
    const proxy = new Proxy({}, handler);
    return proxy;
}

function interact() {
    return makeChainable(interact.calls);
}
interact.calls = [];

export default interact;
