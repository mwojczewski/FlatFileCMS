document.querySelectorAll(".site-header").forEach((header) => {
  const button = header.querySelector(".menu-toggle");
  const navigation = header.querySelector(".site-nav");

  if (
    !(button instanceof HTMLButtonElement) ||
    !(navigation instanceof HTMLElement)
  ) {
    return;
  }

  const close = () => {
    button.setAttribute("aria-expanded", "false");
    button.setAttribute("aria-label", button.dataset.openLabel ?? "Open menu");
    navigation.classList.remove("is-open");
  };

  button.addEventListener("click", () => {
    const open = button.getAttribute("aria-expanded") !== "true";
    button.setAttribute("aria-expanded", String(open));
    button.setAttribute(
      "aria-label",
      open
        ? (button.dataset.closeLabel ?? "Close menu")
        : (button.dataset.openLabel ?? "Open menu"),
    );
    navigation.classList.toggle("is-open", open);
  });

  navigation
    .querySelectorAll("a")
    .forEach((link) => link.addEventListener("click", close));
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      close();
      button.focus();
    }
  });

  const desktop = window.matchMedia("(min-width: 761px)");
  const handleDesktop = (event) => {
    if (event.matches) {
      close();
    }
  };

  if (typeof desktop.addEventListener === "function") {
    desktop.addEventListener("change", handleDesktop);
  } else {
    desktop.addListener(handleDesktop);
  }
});

document.querySelectorAll(".site-header").forEach((header) => {
  let previousScrollY = window.scrollY;
  let frameRequested = false;

  const updateHeader = () => {
    const currentScrollY = window.scrollY;
    const scrollingUp = currentScrollY < previousScrollY;
    const menuOpen =
      header.querySelector(".menu-toggle")?.getAttribute("aria-expanded") ===
      "true";

    header.classList.toggle(
      "is-compact",
      currentScrollY > 72 && !scrollingUp && !menuOpen,
    );
    previousScrollY = currentScrollY;
    frameRequested = false;
  };

  window.addEventListener(
    "scroll",
    () => {
      if (!frameRequested) {
        window.requestAnimationFrame(updateHeader);
        frameRequested = true;
      }
    },
    { passive: true },
  );
});
