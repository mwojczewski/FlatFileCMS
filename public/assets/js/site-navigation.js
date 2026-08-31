document.querySelectorAll('.site-header').forEach((header) => {
    const button = header.querySelector('.menu-toggle');
    const navigation = header.querySelector('.site-nav');

    if (!(button instanceof HTMLButtonElement) || !(navigation instanceof HTMLElement)) {
        return;
    }

    const close = () => {
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-label', button.dataset.openLabel ?? 'Open menu');
        navigation.classList.remove('is-open');
    };

    button.addEventListener('click', () => {
        const open = button.getAttribute('aria-expanded') !== 'true';
        button.setAttribute('aria-expanded', String(open));
        button.setAttribute('aria-label', open ? button.dataset.closeLabel ?? 'Close menu' : button.dataset.openLabel ?? 'Open menu');
        navigation.classList.toggle('is-open', open);
    });

    navigation.querySelectorAll('a').forEach((link) => link.addEventListener('click', close));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
            button.focus();
        }
    });
    window.matchMedia('(min-width: 761px)').addEventListener('change', close);
});
