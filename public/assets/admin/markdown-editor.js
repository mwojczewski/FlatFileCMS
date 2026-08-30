const editors = new WeakMap();

const allowedTags = new Set([
  "A",
  "BLOCKQUOTE",
  "BR",
  "CODE",
  "DEL",
  "EM",
  "H1",
  "H2",
  "H3",
  "H4",
  "H5",
  "H6",
  "HR",
  "IMG",
  "LI",
  "OL",
  "P",
  "PRE",
  "STRONG",
  "TABLE",
  "TBODY",
  "TD",
  "TH",
  "THEAD",
  "TR",
  "UL",
]);

const safeUrl = (value, image = false) => {
  const normalized = value
    .trim()
    .toLowerCase()
    .replace(/[\u0000-\u0020\u007f]+/g, "");
  if (normalized.startsWith("data:")) {
    return (
      image &&
      /^data:image\/(?:avif|gif|jpeg|png|webp);base64,/.test(normalized)
    );
  }

  const scheme = normalized.match(/^([a-z][a-z0-9+.-]*):/);
  if (scheme !== null) {
    return ["http", "https", "mailto", "tel"].includes(scheme[1]);
  }

  return true;
};

const sanitizePreview = (html) => {
  const documentFragment = new DOMParser().parseFromString(html, "text/html");
  [...documentFragment.body.querySelectorAll("*")].forEach((element) => {
    if (!allowedTags.has(element.tagName)) {
      element.replaceWith(...element.childNodes);
      return;
    }

    [...element.attributes].forEach((attribute) => {
      const name = attribute.name.toLowerCase();
      const permitted =
        (element.tagName === "A" && ["href", "title"].includes(name)) ||
        (element.tagName === "IMG" && ["src", "alt", "title"].includes(name));
      if (
        !permitted ||
        ((name === "href" || name === "src") &&
          !safeUrl(attribute.value, element.tagName === "IMG"))
      ) {
        element.removeAttribute(attribute.name);
      }
    });
  });

  return documentFragment.body.innerHTML;
};

const button = (name, text, action, title, noDisable = false) => ({
  name,
  text,
  action,
  title,
  className: `cms-mde-${name}`,
  noDisable,
});

const mount = (root = document) => {
  if (typeof window.EasyMDE !== "function") {
    return;
  }

  root
    .querySelectorAll("textarea[data-markdown-editor]")
    .forEach((textarea) => {
      if (!(textarea instanceof HTMLTextAreaElement) || editors.has(textarea)) {
        return;
      }

      const editor = new window.EasyMDE({
        element: textarea,
        autoDownloadFontAwesome: true,
        autofocus: false,
        forceSync: true,
        indentWithTabs: false,
        lineWrapping: true,
        minHeight: "240px",
        nativeSpellcheck: true,
        placeholder: textarea.placeholder,
        renderingConfig: { sanitizerFunction: sanitizePreview },
        sideBySideFullscreen: false,
        spellChecker: false,
        status: ["lines", "words"],
        // toolbar: [
        //   button("bold", "B", window.EasyMDE.toggleBold, "Pogrubienie"),
        //   button("italic", "I", window.EasyMDE.toggleItalic, "Kursywa"),
        //   button(
        //     "heading-1",
        //     "H1",
        //     window.EasyMDE.toggleHeading1,
        //     "Nagłówek pierwszego poziomu",
        //   ),
        //   button(
        //     "heading-2",
        //     "H2",
        //     window.EasyMDE.toggleHeading2,
        //     "Nagłówek drugiego poziomu",
        //   ),
        //   button(
        //     "heading-3",
        //     "H3",
        //     window.EasyMDE.toggleHeading3,
        //     "Nagłówek trzeciego poziomu",
        //   ),
        //   "|",
        //   button("quote", "❞", window.EasyMDE.toggleBlockquote, "Cytat"),
        //   button(
        //     "unordered-list",
        //     "•",
        //     window.EasyMDE.toggleUnorderedList,
        //     "Lista punktowana",
        //   ),
        //   button(
        //     "ordered-list",
        //     "1.",
        //     window.EasyMDE.toggleOrderedList,
        //     "Lista numerowana",
        //   ),
        //   button(
        //     "check-list",
        //     "☑",
        //     window.EasyMDE.toggleCheckList,
        //     "Lista zadań",
        //   ),
        //   "|",
        //   button("link", "", window.EasyMDE.drawLink, "Link"),
        //   button("image", "", window.EasyMDE.drawImage, "Obraz"),
        //   button("table", "", window.EasyMDE.drawTable, "Tabela"),
        //   "|",
        //   button(
        //     "preview",
        //     "Podgląd",
        //     window.EasyMDE.togglePreview,
        //     "Podgląd Markdown",
        //     true,
        //   ),
        //   button(
        //     "side-by-side",
        //     "Obok",
        //     window.EasyMDE.toggleSideBySide,
        //     "Edytor i podgląd",
        //   ),
        //   button(
        //     "fullscreen",
        //     "Pełny",
        //     window.EasyMDE.toggleFullScreen,
        //     "Pełny ekran",
        //   ),
        // ],
      });

      editors.set(textarea, editor);
    });
};

const refresh = (root = document) => {
  root
    .querySelectorAll("textarea[data-markdown-editor]")
    .forEach((textarea) => {
      editors.get(textarea)?.codemirror.refresh();
    });
};

window.CmsMarkdownEditors = { mount, refresh };
mount();
document.addEventListener("cms:content-added", (event) => mount(event.target));
document.addEventListener("cms:locale-changed", (event) =>
  refresh(event.target),
);
