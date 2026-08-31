(() => {
    'use strict';

    const form = document.querySelector('[data-navigation-form]');
    const editor = document.querySelector('[data-navigation-editor]');
    const payload = document.querySelector('[data-navigation-payload]');
    const dialog = document.querySelector('[data-navigation-dialog]');
    const dialogForm = document.querySelector('[data-navigation-dialog-form]');
    const dialogFields = document.querySelector('[data-navigation-dialog-fields]');
    const dialogTitle = document.querySelector('[data-navigation-dialog-title]');
    if (
        !(form instanceof HTMLFormElement)
        || !(editor instanceof HTMLElement)
        || !(payload instanceof HTMLInputElement)
        || !(dialog instanceof HTMLDialogElement)
        || !(dialogForm instanceof HTMLFormElement)
        || !(dialogFields instanceof HTMLElement)
        || !(dialogTitle instanceof HTMLElement)
    ) {
        return;
    }

    const readData = (id) => {
        const node = document.getElementById(id);
        if (!(node instanceof HTMLScriptElement)) {
            throw new Error(`Missing editor data: ${id}`);
        }
        return JSON.parse(node.textContent ?? '{}');
    };

    const rawNavigation = readData('navigation-data');
    const languageData = readData('navigation-languages');
    const destinations = readData('navigation-destinations');
    const localeEntries = Object.entries(languageData.items ?? {});
    const defaultLocale = languageData.default ?? localeEntries[0]?.[0] ?? 'pl';
    let dragged = null;
    let editedItem = null;
    let editedDraft = null;
    let discardEditedItem = null;

    const normalizeItem = (raw = {}) => {
        const link = raw.link && typeof raw.link === 'object' ? raw.link : null;
        let type = 'url';
        let destination = typeof raw.url === 'string' ? raw.url : '/';
        if (link) {
            type = ['page', 'collection', 'url'].includes(link.type) ? link.type : 'url';
            destination = link[type] ?? '/';
        }

        return {
            label: raw.label && typeof raw.label === 'object' ? {...raw.label} : {},
            type,
            destination: typeof destination === 'string' ? destination : '/',
            target: raw.target === '_blank' ? '_blank' : '_self',
            children: Array.isArray(raw.children) ? raw.children.map(normalizeItem) : [],
        };
    };

    const menus = Object.entries(rawNavigation).map(([name, items]) => ({
        name,
        items: Array.isArray(items) ? items.map(normalizeItem) : [],
    }));

    const element = (name, className = '', text = '') => {
        const node = document.createElement(name);
        if (className) node.className = className;
        if (text) node.textContent = text;
        return node;
    };

    const button = (label, action, className = 'button compact secondary') => {
        const node = element('button', className, label);
        node.type = 'button';
        node.addEventListener('click', action);
        return node;
    };

    const input = (label, value, onInput, options = {}) => {
        const wrapper = element('label');
        wrapper.append(label);
        const control = element('input');
        control.value = value;
        control.required = options.required ?? false;
        if (options.pattern) control.pattern = options.pattern;
        control.addEventListener('input', () => onInput(control.value));
        wrapper.append(control);
        return wrapper;
    };

    const select = (label, value, options, onChange) => {
        const wrapper = element('label');
        wrapper.append(label);
        const control = element('select');
        options.forEach(([optionValue, optionLabel]) => {
            const option = element('option', '', optionLabel);
            option.value = optionValue;
            option.selected = optionValue === value;
            control.append(option);
        });
        control.addEventListener('change', () => onChange(control.value));
        wrapper.append(control);
        return wrapper;
    };

    const destinationOptions = (type) => {
        if (type === 'page') {
            return (destinations.pages ?? []).map((item) => [item.id, `${item.label} — ${item.id}`]);
        }
        if (type === 'collection') {
            return (destinations.collections ?? []).map((item) => [item.id, `${item.label} — ${item.id}`]);
        }
        return [];
    };

    const destinationLabel = (item) => {
        if (item.type === 'url') return item.destination || 'Brak adresu';
        const source = item.type === 'page' ? destinations.pages : destinations.collections;
        const destination = (source ?? []).find((candidate) => candidate.id === item.destination);
        const type = item.type === 'page' ? 'Strona' : 'Kolekcja';
        return destination ? `${type}: ${destination.label} — ${destination.id}` : `${type}: ${item.destination}`;
    };

    const renderDialogFields = () => {
        if (!editedDraft) return;
        dialogFields.replaceChildren();
        const labels = element('div', 'navigation-dialog-labels');
        localeEntries.forEach(([locale, name]) => {
            labels.append(input(`Etykieta — ${name}`, editedDraft.label[locale] ?? '', (value) => {
                editedDraft.label[locale] = value;
            }, {required: locale === defaultLocale}));
        });
        dialogFields.append(labels);
        dialogFields.append(select('Typ linku', editedDraft.type, [
            ['page', 'Strona'], ['collection', 'Kolekcja'], ['url', 'Adres URL / telefon / e-mail'],
        ], (value) => {
            editedDraft.type = value;
            const options = destinationOptions(value);
            editedDraft.destination = options[0]?.[0] ?? (value === 'url' ? '/' : '');
            renderDialogFields();
        }));
        const options = destinationOptions(editedDraft.type);
        if (editedDraft.type === 'url') {
            dialogFields.append(input('Adres', editedDraft.destination, (value) => {
                editedDraft.destination = value;
            }, {required: true}));
        } else {
            dialogFields.append(select(
                editedDraft.type === 'page' ? 'Strona' : 'Kolekcja',
                editedDraft.destination,
                options.length > 0 ? options : [['', 'Brak dostępnych pozycji']],
                (value) => {
                    editedDraft.destination = value;
                },
            ));
        }
        dialogFields.append(select(
            'Otwieranie',
            editedDraft.target,
            [['_self', 'To samo okno'], ['_blank', 'Nowe okno']],
            (value) => {
                editedDraft.target = value;
            },
        ));
    };

    const closeDialog = (discard = false) => {
        if (discard && discardEditedItem) discardEditedItem();
        editedItem = null;
        editedDraft = null;
        discardEditedItem = null;
        dialog.close();
    };

    const openDialog = (item, onDiscard = null) => {
        editedItem = item;
        discardEditedItem = onDiscard;
        editedDraft = {
            label: {...item.label},
            type: item.type,
            destination: item.destination,
            target: item.target,
        };
        dialogTitle.textContent = item.label[defaultLocale]?.trim() || 'Nowa pozycja';
        renderDialogFields();
        dialog.showModal();
        dialogFields.querySelector('input')?.focus();
    };

    const removeFrom = (items, index) => items.splice(index, 1)[0];
    const containsItem = (root, candidate) => root === candidate
        || root.children.some((child) => containsItem(child, candidate));

    const renderItem = (item, items, index, depth, parentContext) => {
        const card = element('article', 'navigation-item');
        card.style.setProperty('--navigation-depth', String(depth));
        const row = element('div', 'navigation-item-row');
        const handle = element('span', 'drag-handle', '⋮⋮');
        handle.draggable = true;
        handle.tabIndex = 0;
        handle.setAttribute('role', 'button');
        handle.setAttribute('aria-label', 'Przeciągnij, aby zmienić kolejność');
        handle.title = 'Przeciągnij, aby zmienić kolejność';
        handle.addEventListener('dragstart', () => {
            dragged = {items, index, item};
            card.classList.add('dragging');
        });
        handle.addEventListener('dragend', () => {
            dragged = null;
            card.classList.remove('dragging');
            editor.querySelectorAll('.drag-over').forEach((node) => node.classList.remove('drag-over'));
        });
        card.addEventListener('dragover', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (dragged && !containsItem(dragged.item, item)) card.classList.add('drag-over');
        });
        card.addEventListener('dragleave', (event) => {
            if (!(event.relatedTarget instanceof Node) || !card.contains(event.relatedTarget)) {
                card.classList.remove('drag-over');
            }
        });
        card.addEventListener('drop', (event) => {
            event.preventDefault();
            event.stopPropagation();
            card.classList.remove('drag-over');
            if (!dragged || (dragged.items === items && dragged.index === index)) return;
            if (containsItem(dragged.item, item)) return;
            const moved = removeFrom(dragged.items, dragged.index);
            let destinationIndex = index;
            if (dragged.items === items && dragged.index < index) destinationIndex -= 1;
            items.splice(destinationIndex, 0, moved);
            render();
        });

        const summary = element('div', 'navigation-item-summary');
        summary.append(
            element('strong', '', item.label[defaultLocale] || 'Nowa pozycja'),
            element('small', '', destinationLabel(item)),
        );
        const actions = element('div', 'navigation-item-actions');
        if (index > 0) actions.append(button('↑', () => {
            [items[index - 1], items[index]] = [items[index], items[index - 1]];
            render();
        }, 'icon-button'));
        if (index < items.length - 1) actions.append(button('↓', () => {
            [items[index], items[index + 1]] = [items[index + 1], items[index]];
            render();
        }, 'icon-button'));
        if (parentContext) actions.append(button('Wysuń', () => {
            const moved = removeFrom(items, index);
            parentContext.items.splice(parentContext.index + 1, 0, moved);
            render();
        }));
        actions.append(button('Edytuj', () => openDialog(item)));
        if (depth < 8) actions.append(button('Dodaj dziecko', () => {
            const child = normalizeItem({label: {[defaultLocale]: ''}});
            item.children.push(child);
            render();
            openDialog(child, () => {
                const childIndex = item.children.indexOf(child);
                if (childIndex >= 0) item.children.splice(childIndex, 1);
                render();
            });
        }));
        actions.append(button('Usuń', () => {
            if (window.confirm('Usunąć tę pozycję wraz z jej dziećmi?')) {
                removeFrom(items, index);
                render();
            }
        }, 'button compact danger-text'));
        row.append(handle, summary, actions);
        card.append(row);

        if (item.children.length > 0) {
            const children = element('div', 'navigation-children');
            item.children.forEach((child, childIndex) => {
                children.append(renderItem(child, item.children, childIndex, depth + 1, {items, index}));
            });
            card.append(children);
        }

        return card;
    };

    const serializeItem = (item) => ({
        label: Object.fromEntries(Object.entries(item.label)
            .map(([locale, value]) => [locale, String(value).trim()])
            .filter(([locale, value]) => locale === defaultLocale || value !== '')),
        link: item.type === 'page'
            ? {type: 'page', page: item.destination}
            : item.type === 'collection'
                ? {type: 'collection', collection: item.destination}
                : {type: 'url', url: item.destination.trim()},
        target: item.target,
        children: item.children.map(serializeItem),
    });

    const sync = () => {
        payload.value = JSON.stringify(Object.fromEntries(menus.map((menu) => [
            menu.name.trim(), menu.items.map(serializeItem),
        ])));
    };

    const render = () => {
        editor.replaceChildren();
        menus.forEach((menu, menuIndex) => {
            const section = element('section', 'form-section navigation-menu');
            const heading = element('div', 'section-heading navigation-menu-heading');
            const title = element('div');
            title.append(element('p', 'eyebrow', 'Menu'), element('h2', '', menu.name));
            const menuActions = element('div', 'actions');
            menuActions.append(button('Dodaj pozycję', () => {
                const item = normalizeItem({label: {[defaultLocale]: ''}});
                menu.items.push(item);
                render();
                openDialog(item, () => {
                    const itemIndex = menu.items.indexOf(item);
                    if (itemIndex >= 0) menu.items.splice(itemIndex, 1);
                    render();
                });
            }));
            if (menus.length > 1) menuActions.append(button('Usuń menu', () => {
                if (window.confirm('Usunąć całe menu?')) {
                    menus.splice(menuIndex, 1);
                    render();
                }
            }, 'button compact danger-text'));
            heading.append(title, menuActions);
            section.append(heading, input('Nazwa techniczna menu', menu.name, (value) => {
                menu.name = value;
                sync();
            }, {required: true, pattern: '[a-z][a-z0-9_-]*'}));
            const list = element('div', 'navigation-list');
            menu.items.forEach((item, index) => list.append(renderItem(item, menu.items, index, 1, null)));
            if (menu.items.length === 0) list.append(element('div', 'empty-state', 'Menu jest puste.'));
            section.append(list);
            editor.append(section);
        });
        sync();
    };

    document.querySelector('[data-navigation-add-menu]')?.addEventListener('click', () => {
        let suffix = menus.length + 1;
        let name = `menu-${suffix}`;
        while (menus.some((menu) => menu.name === name)) name = `menu-${++suffix}`;
        menus.push({name, items: []});
        render();
    });
    dialogForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!editedItem || !editedDraft) return;
        const defaultLabel = String(editedDraft.label[defaultLocale] ?? '').trim();
        if (defaultLabel === '' || editedDraft.destination.trim() === '') {
            window.alert('Nazwa w języku domyślnym i cel linku są wymagane.');
            return;
        }
        editedItem.label = {...editedDraft.label};
        editedItem.type = editedDraft.type;
        editedItem.destination = editedDraft.destination;
        editedItem.target = editedDraft.target;
        editedItem = null;
        editedDraft = null;
        discardEditedItem = null;
        dialog.close();
        render();
    });
    document.querySelectorAll('[data-navigation-dialog-close]').forEach((control) => {
        control.addEventListener('click', () => closeDialog(true));
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) closeDialog(true);
    });
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        closeDialog(true);
    });
    form.addEventListener('submit', (event) => {
        const names = menus.map((menu) => menu.name.trim());
        if (names.some((name) => !/^[a-z][a-z0-9_-]*$/.test(name)) || new Set(names).size !== names.length) {
            event.preventDefault();
            window.alert('Nazwy techniczne menu muszą być unikalne i zgodne ze wzorem a-z, 0-9, _ oraz -.');
            return;
        }
        sync();
    });
    render();
})();
