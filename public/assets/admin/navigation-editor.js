(() => {
  "use strict";

  const form = document.querySelector("[data-navigation-form]");
  const editor = document.querySelector("[data-navigation-editor]");
  const payload = document.querySelector("[data-navigation-payload]");
  const dialog = document.querySelector("[data-navigation-dialog]");
  const dialogForm = document.querySelector("[data-navigation-dialog-form]");
  const dialogFields = document.querySelector(
    "[data-navigation-dialog-fields]",
  );
  const dialogTitle = document.querySelector("[data-navigation-dialog-title]");
  if (
    !(form instanceof HTMLFormElement) ||
    !(editor instanceof HTMLElement) ||
    !(payload instanceof HTMLInputElement) ||
    !(dialog instanceof HTMLDialogElement) ||
    !(dialogForm instanceof HTMLFormElement) ||
    !(dialogFields instanceof HTMLElement) ||
    !(dialogTitle instanceof HTMLElement)
  ) {
    return;
  }

  const readData = (id) => {
    const node = document.getElementById(id);
    if (!(node instanceof HTMLScriptElement)) {
      throw new Error(`Missing editor data: ${id}`);
    }
    return JSON.parse(node.textContent ?? "{}");
  };

  const rawNavigation = readData("navigation-data");
  const languageData = readData("navigation-languages");
  const destinations = readData("navigation-destinations");
  const localeEntries = Object.entries(languageData.items ?? {});
  const defaultLocale = languageData.default ?? localeEntries[0]?.[0] ?? "pl";
  const menuCount = document.querySelector("[data-navigation-menu-count]");
  const itemCount = document.querySelector("[data-navigation-item-count]");
  const revision = document.querySelector("[data-navigation-revision]");
  const saveState = document.querySelector("[data-navigation-save-state]");
  let dragged = null;
  let editedItem = null;
  let editedDraft = null;
  let discardEditedItem = null;
  let saveTimer = null;
  let saving = false;
  let saveAgain = false;

  const normalizeItem = (raw = {}) => {
    const link = raw.link && typeof raw.link === "object" ? raw.link : null;
    let type = "url";
    let destination = typeof raw.url === "string" ? raw.url : "/";
    if (link) {
      type = ["page", "collection", "url"].includes(link.type)
        ? link.type
        : "url";
      destination = link[type] ?? "/";
    }

    return {
      label: raw.label && typeof raw.label === "object" ? { ...raw.label } : {},
      type,
      destination: typeof destination === "string" ? destination : "/",
      target: raw.target === "_blank" ? "_blank" : "_self",
      children: Array.isArray(raw.children)
        ? raw.children.map(normalizeItem)
        : [],
    };
  };

  const menus = Object.entries(rawNavigation).map(([name, items]) => ({
    name,
    items: Array.isArray(items) ? items.map(normalizeItem) : [],
  }));

  const element = (name, className = "", text = "") => {
    const node = document.createElement(name);
    if (className) node.className = className;
    if (text) node.textContent = text;
    return node;
  };

  const countItems = (items) =>
    items.reduce((total, item) => total + 1 + countItems(item.children), 0);

  const kindIcon = (type) => {
    const node = element("span", `navigation-kind-icon is-${type}`);
    node.setAttribute("aria-hidden", "true");
    const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    svg.setAttribute("viewBox", "0 0 24 24");
    svg.setAttribute("fill", "none");
    svg.setAttribute("stroke", "currentColor");
    svg.setAttribute("stroke-width", "1.8");
    svg.innerHTML =
      type === "page"
        ? '<path d="M6 2h8l5 5v15H6z"/><path d="M14 2v6h5M9 13h6M9 17h4"/>'
        : type === "collection"
          ? '<rect x="3" y="5" width="14" height="14" rx="2"/><path d="M7 2h12a2 2 0 0 1 2 2v12"/>'
          : '<path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1.1 1"/><path d="M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1.1-1"/>';
    node.append(svg);
    return node;
  };

  const button = (label, action, className = "button compact secondary") => {
    const node = element("button", className, label);
    node.type = "button";
    node.addEventListener("click", action);
    return node;
  };

  const icons = {
    up: '<path d="m18 15-6-6-6 6"/>',
    down: '<path d="m6 9 6 6 6-6"/>',
    outdent: '<path d="M9 18h10M9 12h10M9 6h10M5 8l-4 4 4 4"/>',
    edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4z"/>',
    addChild: '<path d="M5 4v6a4 4 0 0 0 4 4h10"/><path d="m16 11 3 3-3 3"/><path d="M12 18v4m-2-2h4"/>',
    remove: '<path d="M3 6h18M8 6V4h8v2m-9 0 1 15h8l1-15M10 11v6m4-6v6"/>',
  };

  const iconButton = (label, icon, action, modifier = "") => {
    const node = button(
      label,
      action,
      `icon-button navigation-action ${modifier}`.trim(),
    );
    node.textContent = "";
    node.setAttribute("aria-label", label);
    node.title = label;
    const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    svg.setAttribute("viewBox", "0 0 24 24");
    svg.setAttribute("width", "18");
    svg.setAttribute("height", "18");
    svg.setAttribute("fill", "none");
    svg.setAttribute("stroke", "currentColor");
    svg.setAttribute("stroke-width", "2");
    svg.setAttribute("stroke-linecap", "round");
    svg.setAttribute("stroke-linejoin", "round");
    svg.setAttribute("aria-hidden", "true");
    svg.innerHTML = icons[icon];
    node.append(svg);
    return node;
  };

  const actionsMenu = (actions, label = "Działania") => {
    const menu = element("details", "navigation-actions-menu");
    const trigger = element("summary", "", "•••");
    trigger.setAttribute("aria-label", label);
    trigger.title = label;
    actions.querySelectorAll(".navigation-action").forEach((action) => {
      const actionLabel = action.getAttribute("aria-label");
      if (actionLabel) action.append(element("span", "navigation-action-label", actionLabel));
    });
    actions.classList.add("navigation-actions-popover");
    menu.append(trigger, actions);
    return menu;
  };

  const input = (label, value, onInput, options = {}) => {
    const wrapper = element("label");
    wrapper.append(label);
    const control = element("input");
    control.value = value;
    control.required = options.required ?? false;
    if (options.pattern) control.pattern = options.pattern;
    control.addEventListener("input", () => onInput(control.value));
    wrapper.append(control);
    return wrapper;
  };

  const select = (label, value, options, onChange) => {
    const wrapper = element("label");
    wrapper.append(label);
    const control = element("select");
    options.forEach(([optionValue, optionLabel]) => {
      const option = element("option", "", optionLabel);
      option.value = optionValue;
      option.selected = optionValue === value;
      control.append(option);
    });
    control.addEventListener("change", () => onChange(control.value));
    wrapper.append(control);
    return wrapper;
  };

  const destinationOptions = (type) => {
    if (type === "page") {
      return (destinations.pages ?? []).map((item) => [
        item.id,
        `${item.label} — ${item.id}`,
      ]);
    }
    if (type === "collection") {
      return (destinations.collections ?? []).map((item) => [
        item.id,
        `${item.label} — ${item.id}`,
      ]);
    }
    return [];
  };

  const destinationLabel = (item) => {
    if (item.type === "url") return item.destination || "Brak adresu";
    const source =
      item.type === "page" ? destinations.pages : destinations.collections;
    const destination = (source ?? []).find(
      (candidate) => candidate.id === item.destination,
    );
    const type = item.type === "page" ? "Strona" : "Kolekcja";
    return destination
      ? `${type}: ${destination.label} — ${destination.id}`
      : `${type}: ${item.destination}`;
  };

  const renderDialogFields = () => {
    if (!editedDraft) return;
    dialogFields.replaceChildren();
    const labels = element("div", "navigation-dialog-labels");
    localeEntries.forEach(([locale, name]) => {
      labels.append(
        input(
          `Etykieta — ${name}`,
          editedDraft.label[locale] ?? "",
          (value) => {
            editedDraft.label[locale] = value;
          },
          { required: locale === defaultLocale },
        ),
      );
    });
    dialogFields.append(labels);
    dialogFields.append(
      select(
        "Typ linku",
        editedDraft.type,
        [
          ["page", "Strona"],
          ["collection", "Kolekcja"],
          ["url", "Adres URL / telefon / e-mail"],
        ],
        (value) => {
          editedDraft.type = value;
          const options = destinationOptions(value);
          editedDraft.destination =
            options[0]?.[0] ?? (value === "url" ? "/" : "");
          renderDialogFields();
        },
      ),
    );
    const options = destinationOptions(editedDraft.type);
    if (editedDraft.type === "url") {
      dialogFields.append(
        input(
          "Adres",
          editedDraft.destination,
          (value) => {
            editedDraft.destination = value;
          },
          { required: true },
        ),
      );
    } else {
      dialogFields.append(
        select(
          editedDraft.type === "page" ? "Strona" : "Kolekcja",
          editedDraft.destination,
          options.length > 0 ? options : [["", "Brak dostępnych pozycji"]],
          (value) => {
            editedDraft.destination = value;
          },
        ),
      );
    }
    dialogFields.append(
      select(
        "Otwieranie",
        editedDraft.target,
        [
          ["_self", "To samo okno"],
          ["_blank", "Nowe okno"],
        ],
        (value) => {
          editedDraft.target = value;
        },
      ),
    );
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
      label: { ...item.label },
      type: item.type,
      destination: item.destination,
      target: item.target,
    };
    dialogTitle.textContent =
      item.label[defaultLocale]?.trim() || "Nowa pozycja";
    renderDialogFields();
    dialog.showModal();
    dialogFields.querySelector("input")?.focus();
  };

  const removeFrom = (items, index) => items.splice(index, 1)[0];
  const containsItem = (root, candidate) =>
    root === candidate ||
    root.children.some((child) => containsItem(child, candidate));

  const renderItem = (item, items, index, depth, parentContext) => {
    const card = element("article", "navigation-item");
    card.draggable = true;
    card.style.setProperty("--navigation-depth", String(depth));
    const row = element("div", "navigation-item-row");
    const handle = element("span", "drag-handle", "⋮⋮");
    handle.tabIndex = 0;
    handle.setAttribute("role", "button");
    handle.setAttribute("aria-label", "Przeciągnij, aby zmienić kolejność");
    handle.title = "Przeciągnij, aby zmienić kolejność";
    card.addEventListener("dragstart", (event) => {
      if (
        event.target instanceof Element &&
        event.target.closest(".navigation-item-actions, .navigation-actions-menu")
      ) {
        event.preventDefault();
        return;
      }
      dragged = { items, item, card };
      card.classList.add("dragging");
      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = "move";
        event.dataTransfer.setData("text/plain", "navigation-item");
      }
    });
    card.addEventListener("dragend", () => {
      dragged = null;
      card.classList.remove("dragging");
      editor
        .querySelectorAll(".drag-over")
        .forEach((node) => node.classList.remove("drag-over"));
      render(true);
    });
    card.addEventListener("dragover", (event) => {
      event.preventDefault();
      event.stopPropagation();
      if (!dragged || containsItem(dragged.item, item)) return;
      editor
        .querySelectorAll(".drag-over")
        .forEach((node) => node.classList.toggle("drag-over", node === card));
      card.classList.add("drag-over");
      if (dragged.items !== items || dragged.item === item) return;

      const currentIndex = items.indexOf(dragged.item);
      const targetIndex = items.indexOf(item);
      if (currentIndex < 0 || targetIndex < 0) return;
      const rectangle = card.getBoundingClientRect();
      const after = event.clientY > rectangle.top + rectangle.height / 2;
      let destinationIndex = targetIndex + (after ? 1 : 0);
      if (currentIndex < destinationIndex) destinationIndex -= 1;
      if (destinationIndex === currentIndex) return;

      items.splice(currentIndex, 1);
      items.splice(destinationIndex, 0, dragged.item);
      card.parentElement?.insertBefore(
        dragged.card,
        after ? card.nextSibling : card,
      );
      sync();
    });
    card.addEventListener("dragleave", (event) => {
      if (
        !(event.relatedTarget instanceof Node) ||
        !card.contains(event.relatedTarget)
      ) {
        card.classList.remove("drag-over");
      }
    });
    card.addEventListener("drop", (event) => {
      event.preventDefault();
      event.stopPropagation();
      card.classList.remove("drag-over");
      if (!dragged || dragged.items === items) return;
      if (containsItem(dragged.item, item)) return;
      const sourceIndex = dragged.items.indexOf(dragged.item);
      if (sourceIndex < 0) return;
      const moved = removeFrom(dragged.items, sourceIndex);
      const destinationIndex = items.indexOf(item);
      items.splice(destinationIndex, 0, moved);
      render(true);
    });

    const summary = element("div", "navigation-item-summary");
    summary.append(
      element("strong", "", item.label[defaultLocale] || "Nowa pozycja"),
      element("small", "", destinationLabel(item)),
    );
    const actions = element("div", "navigation-item-actions");
    if (index > 0)
      actions.append(
        iconButton(
          "Przenieś wyżej",
          "up",
          () => {
            [items[index - 1], items[index]] = [items[index], items[index - 1]];
            render(true);
          },
          "navigation-action-move",
        ),
      );
    if (index < items.length - 1)
      actions.append(
        iconButton(
          "Przenieś niżej",
          "down",
          () => {
            [items[index], items[index + 1]] = [items[index + 1], items[index]];
            render(true);
          },
          "navigation-action-move",
        ),
      );
    if (parentContext)
      actions.append(
        iconButton("Wysuń o jeden poziom", "outdent", () => {
          const moved = removeFrom(items, index);
          parentContext.items.splice(parentContext.index + 1, 0, moved);
          render(true);
        }, "navigation-action-structure"),
      );
    actions.append(iconButton("Edytuj pozycję", "edit", () => openDialog(item), "navigation-action-edit"));
    if (depth < 8)
      actions.append(
        iconButton("Dodaj pozycję podrzędną", "addChild", () => {
          const child = normalizeItem({ label: { [defaultLocale]: "" } });
          item.children.push(child);
          render();
          openDialog(child, () => {
            const childIndex = item.children.indexOf(child);
            if (childIndex >= 0) item.children.splice(childIndex, 1);
            render(true);
          });
        }, "navigation-action-add"),
      );
    actions.append(
      iconButton(
        "Usuń pozycję",
        "remove",
        () => {
          if (window.confirm("Usunąć tę pozycję wraz z jej dziećmi?")) {
            removeFrom(items, index);
            render(true);
          }
        },
        "navigation-action-remove",
      ),
    );
    row.append(handle, kindIcon(item.type), summary, actionsMenu(actions));
    card.append(row);

    if (item.children.length > 0) {
      const children = element("div", "navigation-children");
      item.children.forEach((child, childIndex) => {
        children.append(
          renderItem(child, item.children, childIndex, depth + 1, {
            items,
            index,
          }),
        );
      });
      card.append(children);
    }

    return card;
  };

  const serializeItem = (item) => ({
    label: Object.fromEntries(
      Object.entries(item.label)
        .map(([locale, value]) => [locale, String(value).trim()])
        .filter(([locale, value]) => locale === defaultLocale || value !== ""),
    ),
    link:
      item.type === "page"
        ? { type: "page", page: item.destination }
        : item.type === "collection"
          ? { type: "collection", collection: item.destination }
          : { type: "url", url: item.destination.trim() },
    target: item.target,
    children: item.children.map(serializeItem),
  });

  const sync = () => {
    payload.value = JSON.stringify(
      Object.fromEntries(
        menus.map((menu) => [menu.name.trim(), menu.items.map(serializeItem)]),
      ),
    );
  };

  const setSaveState = (state, label) => {
    if (!(saveState instanceof HTMLElement)) return;
    saveState.dataset.state = state;
    const text = saveState.querySelector("span");
    if (text) text.textContent = label;
  };

  const save = async () => {
    if (!(revision instanceof HTMLInputElement)) return;
    if (saving) {
      saveAgain = true;
      return;
    }
    saving = true;
    setSaveState("saving", "Zapisywanie…");
    sync();
    try {
      const response = await fetch(form.action, {
        method: "POST",
        headers: { Accept: "application/json" },
        body: new FormData(form),
      });
      const data = await response.json();
      if (!response.ok || typeof data.revision !== "string") {
        throw new Error(data.error?.message ?? "Nie udało się zapisać nawigacji.");
      }
      revision.value = data.revision;
      setSaveState("saved", "Wszystkie zmiany zapisane");
    } catch (error) {
      setSaveState("error", error instanceof Error ? error.message : "Błąd zapisu");
    } finally {
      saving = false;
      if (saveAgain) {
        saveAgain = false;
        void save();
      }
    }
  };

  const queueSave = () => {
    window.clearTimeout(saveTimer);
    setSaveState("pending", "Zmiany oczekują na zapis");
    saveTimer = window.setTimeout(() => void save(), 450);
  };

  const render = (shouldSave = false) => {
    editor.replaceChildren();
    if (menuCount instanceof HTMLElement) menuCount.textContent = String(menus.length);
    if (itemCount instanceof HTMLElement)
      itemCount.textContent = String(menus.reduce((total, menu) => total + countItems(menu.items), 0));
    menus.forEach((menu, menuIndex) => {
      const section = element("section", "form-section navigation-menu");
      const heading = element("div", "section-heading navigation-menu-heading");
      const title = element("div");
      title.append(
        element("p", "eyebrow", "Menu"),
        element("h2", "", menu.name),
      );
      title.append(element("span", "navigation-menu-count", `${countItems(menu.items)} pozycji`));
      const menuActions = element("div", "actions");
      menuActions.append(
        button("Dodaj pozycję", () => {
          const item = normalizeItem({ label: { [defaultLocale]: "" } });
          menu.items.push(item);
          render(false);
          openDialog(item, () => {
            const itemIndex = menu.items.indexOf(item);
            if (itemIndex >= 0) menu.items.splice(itemIndex, 1);
            render(true);
          });
        }),
      );
      if (menus.length > 1)
        menuActions.append(
          button(
            "Usuń menu",
            () => {
              if (window.confirm("Usunąć całe menu?")) {
                menus.splice(menuIndex, 1);
                render(true);
              }
            },
            "button compact danger-text",
          ),
        );
      heading.append(title, actionsMenu(menuActions, "Działania menu"));
      section.append(
        heading,
        input(
          "Nazwa techniczna menu",
          menu.name,
          (value) => {
            menu.name = value;
            sync();
            queueSave();
          },
          { required: true, pattern: "[a-z][a-z0-9_-]*" },
        ),
      );
      const list = element("div", "navigation-list");
      menu.items.forEach((item, index) =>
        list.append(renderItem(item, menu.items, index, 1, null)),
      );
      if (menu.items.length === 0)
        list.append(element("div", "empty-state", "Menu jest puste."));
      section.append(list);
      editor.append(section);
    });
    sync();
    if (shouldSave) queueSave();
  };

  document
    .querySelector("[data-navigation-add-menu]")
    ?.addEventListener("click", () => {
      let suffix = menus.length + 1;
      let name = `menu-${suffix}`;
      while (menus.some((menu) => menu.name === name))
        name = `menu-${++suffix}`;
      menus.push({ name, items: [] });
      render(true);
    });
  dialogForm.addEventListener("submit", (event) => {
    event.preventDefault();
    if (!editedItem || !editedDraft) return;
    const defaultLabel = String(editedDraft.label[defaultLocale] ?? "").trim();
    if (defaultLabel === "" || editedDraft.destination.trim() === "") {
      window.alert("Nazwa w języku domyślnym i cel linku są wymagane.");
      return;
    }
    editedItem.label = { ...editedDraft.label };
    editedItem.type = editedDraft.type;
    editedItem.destination = editedDraft.destination;
    editedItem.target = editedDraft.target;
    editedItem = null;
    editedDraft = null;
    discardEditedItem = null;
    dialog.close();
    render(true);
  });
  document
    .querySelectorAll("[data-navigation-dialog-close]")
    .forEach((control) => {
      control.addEventListener("click", () => closeDialog(true));
    });
  dialog.addEventListener("click", (event) => {
    if (event.target === dialog) closeDialog(true);
  });
  dialog.addEventListener("cancel", (event) => {
    event.preventDefault();
    closeDialog(true);
  });
  form.addEventListener("submit", (event) => {
    const names = menus.map((menu) => menu.name.trim());
    if (
      names.some((name) => !/^[a-z][a-z0-9_-]*$/.test(name)) ||
      new Set(names).size !== names.length
    ) {
      event.preventDefault();
      window.alert(
        "Nazwy techniczne menu muszą być unikalne i zgodne ze wzorem a-z, 0-9, _ oraz -.",
      );
      return;
    }
    sync();
  });
  render();
})();
