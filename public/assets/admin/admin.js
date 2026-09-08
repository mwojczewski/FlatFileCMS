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

document.addEventListener("click", (event) => {
  if (!(event.target instanceof Element)) return;
  const activeMenu = event.target.closest("[data-page-actions-menu]");
  document.querySelectorAll("[data-page-actions-menu][open]").forEach((menu) => {
    if (menu !== activeMenu) menu.removeAttribute("open");
  });
});

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
