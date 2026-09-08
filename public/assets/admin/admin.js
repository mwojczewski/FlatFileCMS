const shell = document.querySelector(".admin-shell");
const toggle = document.querySelector("[data-admin-menu]");
const close = document.querySelector("[data-admin-menu-close]");
const backdrop = document.querySelector("[data-admin-backdrop]");
const sidebar = document.querySelector("[data-admin-sidebar]");
const sidebarCollapse = document.querySelector("[data-sidebar-collapse]");
const mobileNavigation = window.matchMedia("(max-width: 920px)");
let menuTrigger = null;

const setSidebarCollapsed = (collapsed) => {
  if (!(shell instanceof HTMLElement)) return;
  shell.classList.toggle("sidebar-collapsed", collapsed);
  if (sidebarCollapse instanceof HTMLButtonElement) {
    const label = collapsed ? "Rozwiń panel boczny" : "Zwiń panel boczny";
    sidebarCollapse.setAttribute("aria-label", label);
    sidebarCollapse.setAttribute("title", label);
    sidebarCollapse.setAttribute("aria-expanded", String(!collapsed));
  }
};

try {
  setSidebarCollapsed(localStorage.getItem("flatfile-admin-sidebar") === "collapsed");
} catch {
  setSidebarCollapsed(false);
}

sidebarCollapse?.addEventListener("click", () => {
  const collapsed = !shell?.classList.contains("sidebar-collapsed");
  setSidebarCollapsed(collapsed);
  try {
    localStorage.setItem("flatfile-admin-sidebar", collapsed ? "collapsed" : "expanded");
  } catch {
    // The selected width still applies for the current page.
  }
});

const setMenu = (open) => {
  const shouldOpen = open && mobileNavigation.matches;
  shell?.classList.toggle("menu-open", shouldOpen);
  toggle?.setAttribute("aria-expanded", String(shouldOpen));
  document.body.classList.toggle("menu-locked", shouldOpen);
  if (backdrop instanceof HTMLElement) {
    backdrop.hidden = !shouldOpen;
  }
  if (shouldOpen) {
    menuTrigger = document.activeElement;
    if (close instanceof HTMLElement) {
      close.focus();
    }
  } else if (open === false && menuTrigger instanceof HTMLElement) {
    menuTrigger.focus();
    menuTrigger = null;
  }
};

toggle?.addEventListener("click", () => setMenu(true));
close?.addEventListener("click", () => setMenu(false));
backdrop?.addEventListener("click", () => setMenu(false));
sidebar?.addEventListener("click", (event) => {
  if (event.target instanceof Element && event.target.closest("a")) {
    setMenu(false);
  }
});
mobileNavigation.addEventListener("change", () => setMenu(false));
document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") {
    setMenu(false);
  }
});

document.querySelectorAll(".success, .error").forEach((message) => {
  if (!(message instanceof HTMLElement) || message.textContent.trim() === "") return;
  message.setAttribute("role", message.classList.contains("error") ? "alert" : "status");
  message.setAttribute("aria-live", message.classList.contains("error") ? "assertive" : "polite");
});

const statusMessages = {
  created: "Element został utworzony.",
  updated: "Zmiany zostały zapisane.",
  saved: "Zmiany zostały zapisane.",
  reordered: "Nowa kolejność została zapisana.",
  uploaded: "Plik został przesłany.",
  deleted: "Element został usunięty.",
};
const currentUrl = new URL(window.location.href);
const activeStatus = Object.keys(statusMessages).find(
  (name) => currentUrl.searchParams.get(name) === "1",
);
const adminContent = document.querySelector(".admin-content");
if (activeStatus && adminContent instanceof HTMLElement) {
  const notification = document.createElement("div");
  notification.className = "panel-notification success";
  notification.setAttribute("role", "status");
  notification.setAttribute("aria-live", "polite");
  notification.innerHTML = `<span aria-hidden="true">✓</span><p>${statusMessages[activeStatus]}</p><button type="button" aria-label="Zamknij komunikat">×</button>`;
  adminContent.prepend(notification);
  notification.querySelector("button")?.addEventListener("click", () => notification.remove());
  currentUrl.searchParams.delete(activeStatus);
  window.history.replaceState({}, "", currentUrl);
}

document.addEventListener("submit", (event) => {
  if (!(event.target instanceof HTMLFormElement)) return;
  const message = event.target.dataset.confirm;
  if (message && !window.confirm(message)) {
    event.preventDefault();
    return;
  }
  if (event.defaultPrevented) return;
  const form = event.target;
  window.requestAnimationFrame(() => {
    form.setAttribute("aria-busy", "true");
    form.querySelectorAll('button[type="submit"]').forEach((button) => {
      button.disabled = true;
    });
  });
});

const firstInvalid = document.querySelector(
  '[aria-invalid="true"], .error + form input',
);
if (firstInvalid instanceof HTMLElement) {
  firstInvalid.focus({ preventScroll: true });
}

const pageSearch = document.querySelector("[data-page-search]");
if (pageSearch instanceof HTMLInputElement) {
  const pageRows = [...document.querySelectorAll("[data-page-row]")];
  const emptyRow = document.querySelector("[data-page-empty]");
  const resultCount = document.querySelector("[data-page-result-count]");
  const collapsedBranches = new Set();
  const updatePageResults = () => {
    const query = pageSearch.value.trim().toLocaleLowerCase("pl");
    let visible = 0;
    pageRows.forEach((row) => {
      const identity = row.dataset.pageIdentity ?? "";
      const matches = (row.dataset.pageSearchValue ?? "").includes(query);
      const containsMatch = pageRows.some(
        (candidate) =>
          (candidate.dataset.pageIdentity ?? "").startsWith(`${identity}/`) &&
          (candidate.dataset.pageSearchValue ?? "").includes(query),
      );
      const hiddenByBranch = [...collapsedBranches].some((branch) =>
        identity.startsWith(`${branch}/`),
      );
      const shouldShow = query ? matches || containsMatch : !hiddenByBranch;
      row.hidden = !shouldShow;
      visible += shouldShow ? 1 : 0;
    });
    if (emptyRow instanceof HTMLTableRowElement) emptyRow.hidden = visible !== 0;
    if (resultCount instanceof HTMLElement) resultCount.textContent = `${visible} ${visible === 1 ? "pozycja" : "pozycji"}`;
  };
  pageSearch.addEventListener("input", updatePageResults);
  document.querySelectorAll("[data-page-branch-toggle]").forEach((control) => {
    control.addEventListener("click", () => {
      const row = control.closest("[data-page-row]");
      const identity = row?.dataset.pageIdentity;
      if (!identity) return;
      const collapsed = !collapsedBranches.has(identity);
      if (collapsed) collapsedBranches.add(identity);
      else collapsedBranches.delete(identity);
      control.setAttribute("aria-expanded", String(!collapsed));
      control.setAttribute("aria-label", collapsed ? "Rozwiń podstrony" : "Zwiń podstrony");
      updatePageResults();
    });
  });
}

const pageTree = document.querySelector("[data-page-tree]");
if (pageTree instanceof HTMLTableSectionElement) {
  const state = document.querySelector("[data-page-tree-state]");
  let draggedRow = null;
  let dropMode = "before";
  const parentOf = (identity) => {
    const separator = identity.lastIndexOf("/");
    return separator < 0 ? "" : identity.slice(0, separator);
  };
  const setState = (message, type = "") => {
    if (!(state instanceof HTMLElement)) return;
    state.textContent = message;
    state.dataset.state = type;
  };
  pageTree.addEventListener("dragstart", (event) => {
    const row = event.target instanceof Element ? event.target.closest("[data-page-row][draggable=true]") : null;
    if (!(row instanceof HTMLTableRowElement)) return;
    draggedRow = row;
    row.classList.add("is-dragging");
    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = "move";
      event.dataTransfer.setData("text/plain", row.dataset.pageIdentity ?? "");
    }
  });
  pageTree.addEventListener("dragover", (event) => {
    const row = event.target instanceof Element ? event.target.closest("[data-page-row]") : null;
    if (!(row instanceof HTMLTableRowElement) || !draggedRow || row === draggedRow) return;
    const target = row.dataset.pageIdentity ?? "";
    const source = draggedRow.dataset.pageIdentity ?? "";
    if (target.startsWith(`${source}/`)) return;
    event.preventDefault();
    pageTree.querySelectorAll(".is-drop-before, .is-drop-inside, .is-drop-after").forEach((item) => item.classList.remove("is-drop-before", "is-drop-inside", "is-drop-after"));
    const rectangle = row.getBoundingClientRect();
    const ratio = (event.clientY - rectangle.top) / rectangle.height;
    dropMode = target !== "homepage" && ratio > 0.28 && ratio < 0.72 ? "inside" : ratio >= 0.5 ? "after" : "before";
    row.classList.add(`is-drop-${dropMode}`);
  });
  pageTree.addEventListener("drop", async (event) => {
    const targetRow = event.target instanceof Element ? event.target.closest("[data-page-row]") : null;
    if (!(targetRow instanceof HTMLTableRowElement) || !draggedRow || targetRow === draggedRow) return;
    event.preventDefault();
    const source = draggedRow.dataset.pageIdentity ?? "";
    const target = targetRow.dataset.pageIdentity ?? "";
    const parent = dropMode === "inside" ? target : parentOf(target);
    const siblings = [...pageTree.querySelectorAll("[data-page-row]")].filter((row) => parentOf(row.dataset.pageIdentity ?? "") === parent && row !== draggedRow);
    const targetIndex = siblings.indexOf(targetRow);
    const position = dropMode === "inside" ? Number.MAX_SAFE_INTEGER : Math.max(0, targetIndex + (dropMode === "after" ? 1 : 0));
    setState("Zapisywanie nowego położenia…", "saving");
    const body = new FormData();
    body.set("_csrf", pageTree.dataset.pageTreeCsrf ?? "");
    body.set("source", source);
    body.set("parent", parent);
    body.set("position", String(position));
    body.set("revision", draggedRow.dataset.pageRevision ?? "");
    try {
      const response = await fetch("/admin/pages/reorder", { method: "POST", headers: { Accept: "application/json" }, body });
      if (!response.ok) throw new Error("Nie udało się zmienić położenia strony.");
      setState("Położenie zapisane", "saved");
      window.location.reload();
    } catch (error) {
      setState(error instanceof Error ? error.message : "Błąd zapisu", "error");
    }
  });
  pageTree.addEventListener("dragend", () => {
    pageTree.querySelectorAll(".is-dragging, .is-drop-before, .is-drop-inside, .is-drop-after").forEach((item) => item.classList.remove("is-dragging", "is-drop-before", "is-drop-inside", "is-drop-after"));
    draggedRow = null;
  });
}

document.addEventListener("click", (event) => {
  if (!(event.target instanceof Element)) return;
  const activeMenu = event.target.closest("[data-page-actions-menu], [data-actions-menu], .navigation-actions-menu");
  document.querySelectorAll("[data-page-actions-menu][open], [data-actions-menu][open], .navigation-actions-menu[open]").forEach((menu) => {
    if (menu !== activeMenu) menu.removeAttribute("open");
  });
});

const mediaSearch = document.querySelector("[data-media-search]");
if (mediaSearch instanceof HTMLInputElement) {
  const mediaCards = [...document.querySelectorAll("[data-media-card]")];
  const emptyMedia = document.querySelector("[data-media-empty]");
  const mediaCount = document.querySelector("[data-media-result-count]");
  const updateMediaResults = () => {
    const query = mediaSearch.value.trim().toLocaleLowerCase("pl");
    let visible = 0;
    mediaCards.forEach((card) => {
      const matches = (card.dataset.mediaSearchValue ?? "").includes(query);
      card.hidden = !matches;
      visible += matches ? 1 : 0;
    });
    if (emptyMedia instanceof HTMLElement) emptyMedia.hidden = visible !== 0;
    if (mediaCount instanceof HTMLElement) mediaCount.textContent = `${visible} ${visible === 1 ? "plik" : "plików"}`;
  };
  mediaSearch.addEventListener("input", updateMediaResults);
}

const lightbox = document.querySelector("[data-media-lightbox]");
document.addEventListener("click", (event) => {
  if (
    !(event.target instanceof Element) ||
    !(lightbox instanceof HTMLDialogElement)
  ) {
    return;
  }
  const preview = event.target.closest("[data-media-preview]");
  if (preview instanceof HTMLButtonElement) {
    const image = lightbox.querySelector("[data-media-lightbox-image]");
    const caption = lightbox.querySelector("[data-media-lightbox-caption]");
    if (image instanceof HTMLImageElement && caption instanceof HTMLElement) {
      image.src = preview.dataset.mediaPreview ?? "";
      image.alt = preview.dataset.mediaPreviewName ?? "";
      caption.textContent = preview.dataset.mediaPreviewName ?? "";
      lightbox.showModal();
    }
    return;
  }
  if (
    event.target.closest("[data-media-lightbox-close]") ||
    event.target === lightbox
  ) {
    lightbox.close();
  }
});
