document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) {
        return;
    }

    const localeTab = target.closest('[data-locale-target]');
    if (localeTab instanceof HTMLButtonElement) {
        const translated = localeTab.closest('.translated');
        const locale = localeTab.dataset.localeTarget;
        if (translated && locale) {
            translated.querySelectorAll(':scope > .locale-tabs > .locale-tab').forEach((tab) => {
                tab.classList.toggle('active', tab === localeTab);
            });
            translated.querySelectorAll(':scope > .locale-panel').forEach((panel) => {
                panel.classList.toggle('active', panel.getAttribute('data-locale-panel') === locale);
            });
        }
        return;
    }

    const addButton = target.closest('[data-repeater-add]');
    if (addButton instanceof HTMLButtonElement) {
        const repeater = addButton.closest('[data-repeater]');
        const template = repeater?.querySelector(':scope > template[data-repeater-template]');
        const items = repeater?.querySelector(':scope > [data-repeater-items]');
        if (repeater instanceof HTMLElement && template instanceof HTMLTemplateElement && items) {
            const index = Number.parseInt(repeater.dataset.nextIndex ?? '0', 10);
            const token = template.dataset.indexToken;
            if (token) {
                const html = template.innerHTML.replaceAll(token, String(index));
                items.insertAdjacentHTML('beforeend', html);
                repeater.dataset.nextIndex = String(index + 1);
            }
        }
        return;
    }

    const removeButton = target.closest('[data-repeater-remove]');
    if (removeButton instanceof HTMLButtonElement) {
        removeButton.closest('.repeater-item')?.remove();
    }
});

const builderList = document.querySelector('[data-builder-list]');
const orderForm = document.querySelector('[data-order-form]');
const orderFields = document.querySelector('[data-order-fields]');
const orderSubmit = document.querySelector('[data-order-submit]');
let dragged = null;

function synchronizeOrder() {
    if (!builderList || !orderFields) {
        return;
    }
    orderFields.replaceChildren();
    builderList.querySelectorAll('[data-block-id]').forEach((item, index) => {
        const id = item.getAttribute('data-block-id');
        const position = item.querySelector('.position');
        if (position) {
            position.textContent = String(index + 1);
        }
        if (id) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'order[]';
            input.value = id;
            orderFields.append(input);
        }
    });
    if (orderSubmit instanceof HTMLButtonElement) {
        orderSubmit.disabled = false;
    }
}

builderList?.addEventListener('dragstart', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }
    dragged = event.target.closest('[data-block-id]');
    dragged?.classList.add('dragging');
});

builderList?.addEventListener('dragend', () => {
    dragged?.classList.remove('dragging');
    dragged = null;
});

builderList?.addEventListener('dragover', (event) => {
    event.preventDefault();
    if (!(dragged instanceof Element) || !(event.target instanceof Element)) {
        return;
    }
    const target = event.target.closest('[data-block-id]');
    if (!(target instanceof Element) || target === dragged || !builderList) {
        return;
    }
    const rectangle = target.getBoundingClientRect();
    const after = event.clientY > rectangle.top + rectangle.height / 2;
    builderList.insertBefore(dragged, after ? target.nextSibling : target);
    synchronizeOrder();
});

orderForm?.addEventListener('submit', synchronizeOrder);

document.addEventListener('submit', (event) => {
    if (!(event.target instanceof HTMLFormElement)) {
        return;
    }
    const message = event.target.dataset.confirm;
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});
