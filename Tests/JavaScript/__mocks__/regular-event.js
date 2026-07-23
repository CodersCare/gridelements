export default class RegularEvent {
    constructor(eventName, handler) {
        this.eventName = eventName;
        this.handler = handler;
    }

    bindTo(element) {
        element.addEventListener(this.eventName, this.handler);
        return this;
    }

    delegateTo(element, selector) {
        element.addEventListener(this.eventName, (event) => {
            const match = event.target.closest ? event.target.closest(selector) : null;
            if (match) {
                this.handler(event, match);
            }
        });
        return this;
    }
}
