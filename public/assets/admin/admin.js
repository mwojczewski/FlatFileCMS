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
