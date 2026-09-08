document.addEventListener("click", (event) => {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }

  const localeTab = target.closest("[data-locale-target]");
  if (localeTab instanceof HTMLButtonElement) {
    const translated = localeTab.closest(".translated");
    const locale = localeTab.dataset.localeTarget;
    if (translated && locale) {
      translated
        .querySelectorAll(":scope > .locale-tabs > .locale-tab")
        .forEach((tab) => {
          tab.classList.toggle("active", tab === localeTab);
        });
      translated.querySelectorAll(":scope > .locale-panel").forEach((panel) => {
        panel.classList.toggle(
          "active",
          panel.getAttribute("data-locale-panel") === locale,
        );
      });
      translated.dispatchEvent(
        new CustomEvent("cms:locale-changed", { bubbles: true }),
      );
    }
    return;
  }

  const addButton = target.closest("[data-repeater-add]");
  if (addButton instanceof HTMLButtonElement) {
    const repeater = addButton.closest("[data-repeater]");
    const template = repeater?.querySelector(
      ":scope > template[data-repeater-template]",
    );
    const items = repeater?.querySelector(":scope > [data-repeater-items]");
    if (
      repeater instanceof HTMLElement &&
      template instanceof HTMLTemplateElement &&
      items
    ) {
      const index = Number.parseInt(repeater.dataset.nextIndex ?? "0", 10);
      const token = template.dataset.indexToken;
      if (token) {
        const html = template.innerHTML.replaceAll(token, String(index));
        items.insertAdjacentHTML("beforeend", html);
        repeater.dataset.nextIndex = String(index + 1);
        items.dispatchEvent(
          new CustomEvent("cms:content-added", { bubbles: true }),
        );
      }
    }
    return;
  }

  const removeButton = target.closest("[data-repeater-remove]");
  if (removeButton instanceof HTMLButtonElement) {
    removeButton.closest(".repeater-item")?.remove();
    return;
  }

  const mediaButton = target.closest("[data-media-open]");
  if (mediaButton instanceof HTMLButtonElement) {
    const field = mediaButton.closest("[data-media-field]");
    const input = field?.querySelector("[data-media-source]");
    const form = mediaButton.closest("form[data-page-identity]");
    const identity = form?.getAttribute("data-page-identity");
    if (input instanceof HTMLInputElement && identity) {
      openMediaPicker(identity, mediaButton.dataset.mediaKind ?? "file", input);
    }
  }
});

let mediaSelection = null;

function mediaDialog() {
  let dialog = document.querySelector("[data-media-dialog]");
  if (dialog instanceof HTMLDialogElement) {
    return dialog;
  }
  dialog = document.createElement("dialog");
  dialog.className = "media-dialog";
  dialog.dataset.mediaDialog = "";
  dialog.innerHTML =
    '<div class="media-dialog-heading"><div><p class="eyebrow">Biblioteka strony</p>' +
    '<h2>Wybierz plik</h2></div><button type="button" class="dialog-close" data-media-close aria-label="Zamknij">×</button></div>' +
    '<div class="media-picker-grid" data-media-picker-grid></div>' +
    '<div class="actions media-dialog-footer"><a class="button secondary" data-media-manage>Otwórz bibliotekę</a>' +
    '<button type="button" class="button secondary" data-media-close>Zamknij</button></div>';
  document.body.append(dialog);
  dialog.addEventListener("click", (event) => {
    if (!(event.target instanceof Element)) {
      return;
    }
    if (event.target.closest("[data-media-close]")) {
      dialog.close();
      return;
    }
    const select = event.target.closest("[data-media-select]");
    if (
      select instanceof HTMLButtonElement &&
      typeof mediaSelection === "function"
    ) {
      mediaSelection({
        name: select.dataset.mediaSelect ?? "",
        url: select.dataset.mediaUrl ?? "",
        mime: select.dataset.mediaMime ?? "",
      });
      dialog.close();
    }
  });

  return dialog;
}

async function openMediaPicker(identity, kind, selection) {
  const dialog = mediaDialog();
  const grid = dialog.querySelector("[data-media-picker-grid]");
  const manage = dialog.querySelector("[data-media-manage]");
  if (
    !(grid instanceof HTMLElement) ||
    !(manage instanceof HTMLAnchorElement)
  ) {
    return;
  }
  mediaSelection =
    selection instanceof HTMLInputElement
      ? (item) => {
          selection.value = item.name;
          selection.dispatchEvent(new Event("change", { bubbles: true }));
        }
      : selection;
  grid.replaceChildren();
  const loading = document.createElement("p");
  loading.className = "muted";
  loading.textContent = "Ładowanie multimediów…";
  grid.append(loading);
  if (typeof dialog.showModal === "function") {
    dialog.showModal();
  } else {
    dialog.setAttribute("open", "");
  }

  try {
    const response = await fetch(
      `/admin/media/picker?path=${encodeURIComponent(identity)}&kind=${encodeURIComponent(kind)}`,
      {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      },
    );
    if (!response.ok) {
      throw new Error("Media request failed.");
    }
    const payload = await response.json();
    grid.replaceChildren();
    manage.href = payload.manageUrl;
    if (!Array.isArray(payload.items) || payload.items.length === 0) {
      const empty = document.createElement("p");
      empty.className = "empty-state";
      empty.textContent = "Brak plików pasujących do tego pola.";
      grid.append(empty);
      return;
    }
    payload.items.forEach((item) => {
      if (
        !item ||
        typeof item.name !== "string" ||
        typeof item.mime !== "string"
      ) {
        return;
      }
      const button = document.createElement("button");
      button.type = "button";
      button.className = "media-picker-card";
      button.dataset.mediaSelect = item.name;
      button.dataset.mediaUrl = typeof item.url === "string" ? item.url : "";
      button.dataset.mediaMime = item.mime;
      if (item.image === true && typeof item.thumbnail === "string") {
        const image = document.createElement("img");
        image.src = item.thumbnail;
        image.alt = "";
        image.loading = "lazy";
        button.append(image);
      } else {
        const icon = document.createElement("span");
        icon.className = "media-file-icon";
        icon.textContent = item.name.split(".").pop()?.toUpperCase() ?? "FILE";
        button.append(icon);
      }
      const description = document.createElement("span");
      const name = document.createElement("strong");
      name.textContent = item.name;
      const mime = document.createElement("small");
      mime.textContent = item.mime;
      description.append(name, mime);
      button.append(description);
      grid.append(button);
    });
  } catch {
    grid.replaceChildren();
    const error = document.createElement("p");
    error.className = "error";
    error.textContent = "Nie udało się wczytać biblioteki multimediów.";
    grid.append(error);
  }
}

window.CmsMediaPicker = { open: openMediaPicker };

const builderList = document.querySelector("[data-builder-list]");
const orderForm = document.querySelector("[data-order-form]");
const orderFields = document.querySelector("[data-order-fields]");
const orderSubmit = document.querySelector("[data-order-submit]");
const orderMessage = document.querySelector("[data-order-message]");
let dragged = null;
let dragGhost = null;

function synchronizeOrder() {
  if (!builderList || !orderFields) {
    return;
  }
  orderFields.replaceChildren();
  builderList.querySelectorAll("[data-block-id]").forEach((item, index) => {
    const id = item.getAttribute("data-block-id");
    const position = item.querySelector(".position");
    if (position) {
      position.textContent = String(index + 1);
    }
    if (id) {
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = "order[]";
      input.value = id;
      orderFields.append(input);
    }
  });
  if (orderSubmit instanceof HTMLButtonElement) {
    orderSubmit.disabled = false;
  }
  if (orderMessage instanceof HTMLElement) {
    orderMessage.textContent = "Kolejność została zmieniona";
  }
}

builderList?.addEventListener("dragstart", (event) => {
  if (!(event.target instanceof Element)) {
    return;
  }
  dragged = event.target.closest("[data-block-id]");
  dragged?.classList.add("dragging");
  builderList.classList.add("is-sorting");
  if (dragged instanceof HTMLElement && event.dataTransfer) {
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", dragged.dataset.blockId ?? "");
    dragGhost = dragged.querySelector(".builder-preview-toolbar")?.cloneNode(true);
    if (dragGhost instanceof HTMLElement) {
      dragGhost.className = "builder-drag-ghost";
      document.body.append(dragGhost);
      event.dataTransfer.setDragImage(dragGhost, 28, 20);
    }
  }
});

builderList?.addEventListener("dragend", () => {
  dragged?.classList.remove("dragging");
  builderList?.classList.remove("is-sorting");
  dragGhost?.remove();
  dragGhost = null;
  dragged = null;
});

builderList?.addEventListener("dragover", (event) => {
  event.preventDefault();
  if (!(dragged instanceof Element) || !(event.target instanceof Element)) {
    return;
  }
  const target = event.target.closest("[data-block-id]");
  if (!(target instanceof Element) || target === dragged || !builderList) {
    return;
  }
  const rectangle = target.getBoundingClientRect();
  const after = event.clientY > rectangle.top + rectangle.height / 2;
  builderList.insertBefore(dragged, after ? target.nextSibling : target);
  synchronizeOrder();
});

orderForm?.addEventListener("submit", synchronizeOrder);

const inspector = document.querySelector("[data-block-inspector]");
const inspectorTitle = document.querySelector("[data-inspector-title]");
const liveState = document.querySelector("[data-editor-live-state]");
const previewTimers = new WeakMap();
const previewRequests = new WeakMap();

function selectBlock(id) {
  if (!id) return;
  document.querySelectorAll("[data-block-select]").forEach((item) => {
    item.classList.toggle("selected", item.getAttribute("data-block-select") === id);
  });
  let selectedForm = null;
  document.querySelectorAll("[data-block-form]").forEach((form) => {
    const selected = form.getAttribute("data-block-form") === id;
    form.hidden = !selected;
    if (selected) selectedForm = form;
  });
  if (selectedForm instanceof HTMLFormElement) {
    if (inspectorTitle instanceof HTMLElement) {
      inspectorTitle.textContent = selectedForm.dataset.blockName ?? "Blok";
    }
    inspector?.classList.add("is-open");
    if (!selectedForm.querySelector("[data-markdown-editor]")) {
      inspector?.classList.remove("is-expanded");
    }
    window.CmsMarkdownEditors?.refresh(selectedForm);
  }
}

function setInspectorExpanded(expanded) {
  if (!(inspector instanceof HTMLElement)) return;
  inspector.classList.toggle("is-expanded", expanded);
  const button = inspector.querySelector("[data-inspector-expand]");
  if (button instanceof HTMLButtonElement) {
    button.setAttribute("aria-label", expanded ? "Zwęź panel edycji" : "Rozszerz panel edycji");
    button.title = expanded ? "Zwęź panel edycji" : "Rozszerz panel edycji";
    button.setAttribute("aria-pressed", String(expanded));
  }
  const visibleForm = inspector.querySelector("[data-block-form]:not([hidden])");
  if (visibleForm instanceof HTMLElement) {
    window.requestAnimationFrame(() =>
      window.CmsMarkdownEditors?.refresh(visibleForm),
    );
  }
}

function resizePreviewFrame(frame) {
  if (!(frame instanceof HTMLIFrameElement)) return;
  try {
    const body = frame.contentDocument?.body;
    const root = frame.contentDocument?.documentElement;
    const stage = frame.closest("[data-preview-stage]");
    if (!body || !root || !(stage instanceof HTMLElement)) return;
    const virtualWidth = 1440;
    const scale = Math.min(1, stage.clientWidth / virtualWidth);
    frame.style.width = `${virtualWidth}px`;
    frame.style.transform = `scale(${scale})`;
    const contentHeight = Math.max(body.scrollHeight, root.scrollHeight, 96);
    frame.style.height = `${contentHeight}px`;
    stage.style.height = `${Math.ceil(contentHeight * scale)}px`;
    body.addEventListener("click", () => selectBlock(frame.dataset.previewFrame));
    body.querySelectorAll("a, button, input, textarea, select").forEach((element) => {
      element.addEventListener("click", (event) => event.preventDefault());
    });
  } catch {
    frame.style.height = "240px";
  }
}

document.querySelectorAll("[data-preview-frame]").forEach((frame) => {
  if (!(frame instanceof HTMLIFrameElement)) return;
  frame.addEventListener("load", () => {
    resizePreviewFrame(frame);
    window.setTimeout(() => resizePreviewFrame(frame), 250);
    window.setTimeout(() => resizePreviewFrame(frame), 900);
  });
  if (frame.contentDocument?.readyState === "complete") resizePreviewFrame(frame);
});

if (typeof ResizeObserver === "function") {
  const previewResizeObserver = new ResizeObserver((entries) => {
    entries.forEach((entry) => {
      const frame = entry.target.querySelector("[data-preview-frame]");
      if (frame instanceof HTMLIFrameElement) resizePreviewFrame(frame);
    });
  });
  document.querySelectorAll("[data-preview-stage]").forEach((stage) =>
    previewResizeObserver.observe(stage),
  );
}

document.addEventListener("click", (event) => {
  if (!(event.target instanceof Element)) return;
  const edit = event.target.closest("[data-block-edit]");
  if (edit instanceof HTMLButtonElement) {
    selectBlock(edit.dataset.blockEdit);
    return;
  }
  const item = event.target.closest("[data-block-select]");
  if (item instanceof HTMLElement && !event.target.closest("form, button, a")) {
    selectBlock(item.dataset.blockSelect);
  }
  if (event.target.closest("[data-inspector-close]")) {
    if (inspector?.classList.contains("is-expanded")) {
      setInspectorExpanded(false);
    } else {
      inspector?.classList.remove("is-open");
    }
    return;
  }
  if (event.target.closest("[data-inspector-expand]")) {
    setInspectorExpanded(!inspector?.classList.contains("is-expanded"));
  }
});

inspector?.addEventListener("focusin", (event) => {
  if (!(event.target instanceof Element)) return;
  if (event.target.closest(".EasyMDEContainer") || event.target.matches("textarea[data-markdown-editor]")) {
    setInspectorExpanded(true);
  }
});

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape" && inspector?.classList.contains("is-expanded")) {
    setInspectorExpanded(false);
  }
});

async function refreshBlockPreview(form) {
  const id = form.dataset.blockForm;
  const frame = id
    ? document.querySelector(`[data-preview-frame="${CSS.escape(id)}"]`)
    : null;
  const status = form.querySelector("[data-block-preview-status]");
  if (!(frame instanceof HTMLIFrameElement)) return;

  previewRequests.get(form)?.abort();
  const controller = new AbortController();
  previewRequests.set(form, controller);
  if (status instanceof HTMLElement) status.textContent = "Aktualizuję podgląd…";
  if (liveState instanceof HTMLElement) liveState.textContent = "Aktualizuję podgląd…";

  try {
    const response = await fetch("/admin/pages/builder/render-preview", {
      method: "POST",
      body: new FormData(form),
      credentials: "same-origin",
      headers: { Accept: "application/json" },
      signal: controller.signal,
    });
    const payload = await response.json();
    if (!response.ok || typeof payload.preview !== "string") {
      throw new Error("Preview validation failed.");
    }
    frame.srcdoc = payload.preview;
    if (status instanceof HTMLElement) status.textContent = "Podgląd aktualny";
    if (liveState instanceof HTMLElement) liveState.textContent = "Niezapisane zmiany";
  } catch (error) {
    if (error instanceof DOMException && error.name === "AbortError") return;
    if (status instanceof HTMLElement) status.textContent = "Uzupełnij poprawnie pola";
    if (liveState instanceof HTMLElement) liveState.textContent = "Podgląd czeka na poprawne dane";
  }
}

document.querySelectorAll("[data-block-form]").forEach((form) => {
  if (!(form instanceof HTMLFormElement)) return;
  const schedule = () => {
    const current = previewTimers.get(form);
    if (current) window.clearTimeout(current);
    previewTimers.set(form, window.setTimeout(() => refreshBlockPreview(form), 350));
  };
  form.addEventListener("input", schedule);
  form.addEventListener("change", schedule);
  form.addEventListener("cms:content-added", schedule);
});

const blockSearch = document.querySelector("[data-block-search]");
if (blockSearch instanceof HTMLInputElement) {
  const cards = [...document.querySelectorAll("[data-block-card]")];
  const empty = document.querySelector("[data-block-library-empty]");
  blockSearch.addEventListener("input", () => {
    const query = blockSearch.value.trim().toLocaleLowerCase("pl");
    let visible = 0;
    cards.forEach((card) => {
      const matches = (card.dataset.blockSearchValue ?? "").includes(query);
      card.hidden = !matches;
      visible += matches ? 1 : 0;
    });
    if (empty instanceof HTMLElement) empty.hidden = visible !== 0;
  });
}
