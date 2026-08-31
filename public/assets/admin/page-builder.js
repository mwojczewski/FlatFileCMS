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
let dragged = null;

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
}

builderList?.addEventListener("dragstart", (event) => {
  if (!(event.target instanceof Element)) {
    return;
  }
  dragged = event.target.closest("[data-block-id]");
  dragged?.classList.add("dragging");
});

builderList?.addEventListener("dragend", () => {
  dragged?.classList.remove("dragging");
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

document.addEventListener("submit", (event) => {
  if (!(event.target instanceof HTMLFormElement)) {
    return;
  }
  const message = event.target.dataset.confirm;
  if (message && !window.confirm(message)) {
    event.preventDefault();
  }
});
