document.querySelectorAll("[data-canonical-suggest]").forEach((form) => {
  const slug = form.querySelector("[data-public-slug]");
  const canonical = form.querySelector("[data-canonical]");
  if (
    !(slug instanceof HTMLInputElement) ||
    !(canonical instanceof HTMLInputElement)
  )
    return;

  const siteUrl = form.dataset.siteUrl?.replace(/\/$/, "") ?? "";
  const basePath = form.dataset.canonicalBasePath?.replace(/\/$/, "") ?? "";
  let previousSuggestion = "";

  const suggest = () => {
    const segment = slug.value.trim().replace(/^\/+|\/+$/g, "");
    const suggestion =
      segment === "" || siteUrl === ""
        ? ""
        : `${siteUrl}${basePath}/${segment}`;
    if (canonical.value.trim() === "" || canonical.value === previousSuggestion)
      canonical.value = suggestion;
    previousSuggestion = suggestion;
  };

  slug.addEventListener("input", suggest);
  suggest();
});
