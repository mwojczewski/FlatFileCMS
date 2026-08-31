const shell = document.querySelector('.admin-shell');
const toggle = document.querySelector('[data-admin-menu]');
const close = document.querySelector('[data-admin-menu-close]');
const backdrop = document.querySelector('[data-admin-backdrop]');

const setMenu = (open) => {
    shell?.classList.toggle('menu-open', open);
    toggle?.setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('menu-locked', open);
};

toggle?.addEventListener('click', () => setMenu(true));
close?.addEventListener('click', () => setMenu(false));
backdrop?.addEventListener('click', () => setMenu(false));
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        setMenu(false);
    }
});

const firstInvalid = document.querySelector('[aria-invalid="true"], .error + form input');
if (firstInvalid instanceof HTMLElement) {
    firstInvalid.focus({preventScroll: true});
}

const lightbox = document.querySelector('[data-media-lightbox]');
document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element) || !(lightbox instanceof HTMLDialogElement)) {
        return;
    }
    const preview = event.target.closest('[data-media-preview]');
    if (preview instanceof HTMLButtonElement) {
        const image = lightbox.querySelector('[data-media-lightbox-image]');
        const caption = lightbox.querySelector('[data-media-lightbox-caption]');
        if (image instanceof HTMLImageElement && caption instanceof HTMLElement) {
            image.src = preview.dataset.mediaPreview ?? '';
            image.alt = preview.dataset.mediaPreviewName ?? '';
            caption.textContent = preview.dataset.mediaPreviewName ?? '';
            lightbox.showModal();
        }
        return;
    }
    if (event.target.closest('[data-media-lightbox-close]') || event.target === lightbox) {
        lightbox.close();
    }
});
