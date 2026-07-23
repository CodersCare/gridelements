class FakeModal {
    constructor() {
        this._listeners = {};
    }

    addEventListener(type, callback) {
        (this._listeners[type] ??= []).push(callback);
    }

    dispatchEvent(type, target) {
        (this._listeners[type] ?? []).forEach(cb => cb({ target }));
    }

    /** Simulates a user clicking one of the buttons passed to Modal.confirm/show, by button "name". */
    clickButton(name) {
        const buttonTarget = { getAttribute: (attr) => (attr === 'name' ? name : null) };
        this.dispatchEvent('button.clicked', buttonTarget);
    }

    hideModal() {
        this.hidden = true;
    }
}

export default {
    currentModal: null,
    show(title, content, severity, buttons) {
        const modal = new FakeModal();
        modal.title = title;
        modal.content = content;
        modal.severity = severity;
        modal.buttons = buttons;
        this.currentModal = modal;
        return modal;
    },
    confirm(title, content, severity, buttons) {
        return this.show(title, content, severity, buttons);
    },
    dismiss() {
        this.currentModal = null;
    },
    loadUrl(title, severity, buttons, url) {
        const modal = new FakeModal();
        modal.title = title;
        modal.url = url;
        this.currentModal = modal;
        return modal;
    },
};
