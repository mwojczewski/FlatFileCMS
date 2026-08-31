document.querySelectorAll("[data-copy-code]").forEach((button) => {
  button.addEventListener("click", async () => {
    const code =
      button.closest(".code-showcase__panel")?.querySelector("code")
        ?.textContent ?? "";
    try {
      await navigator.clipboard.writeText(code);
      button.textContent = button.dataset.copiedLabel ?? "Copied";
      setTimeout(() => {
        button.textContent = button.dataset.copyLabel ?? "Copy";
      }, 1600);
    } catch (_) {
      button.textContent = button.dataset.copyLabel ?? "Copy";
    }
  });
});
