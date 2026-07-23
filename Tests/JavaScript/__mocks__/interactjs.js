function makeChainable() {
    const handler = {
        get(target, prop) {
            if (prop === 'then' || typeof prop === 'symbol') {
                return undefined;
            }
            return (..._args) => proxy;
        },
    };
    const proxy = new Proxy({}, handler);
    return proxy;
}

export default function interact() {
    return makeChainable();
}
