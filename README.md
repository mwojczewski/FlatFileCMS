# FlatFile CMS — opcjonalne prerendery React

Pakiet dodaje opcjonalny silnik `react-prerender` dla pojedynczych stron.
Zawartość archiwum zachowuje strukturę katalogów projektu i można ją nałożyć
na odpowiadającą jej wersję FlatFile CMS.

## Użycie

W panelu strony wybierz „React prerender” albo dodaj do `content.yml`:

```yaml
render:
  engine: react-prerender
```

CMS najpierw rozwiązuje normalną, zlokalizowaną trasę strony, a następnie czyta
pełny dokument HTML według wzoru:

```text
public/app/prerender/{locale}/{localized-path}/index.html
```

Przykłady:

```text
/en/privacy-policy -> public/app/prerender/en/privacy-policy/index.html
/pl/                -> public/app/prerender/pl/index.html
```

Brak pliku dla strony korzystającej z Reacta zwraca HTTP 503 z kodem
`PRERENDER_NOT_AVAILABLE`. Zwykłe strony nadal używają renderera PHP.

## Wdrożenie

1. Rozpakuj archiwum w katalogu głównym zgodnej wersji projektu.
2. Po wygenerowaniu lub wymianie prerenderów wykonaj `php bin/cms cache:clear`.
3. Zablokuj bezpośredni dostęp HTTP do `/app/prerender/`; pliki powinny być
   czytane wyłącznie przez CMS.
4. Uruchom `composer check`.

## Weryfikacja

Pakiet sprawdzono za pomocą PHPUnit: 204 testy, 510 asercji. Kontrola stylu
przeszła bez zmian. PHPStan wykrywa dwa istniejące, niezwiązane z pakietem
błędy w `app/Media/PublicMediaController.php`; nowe pliki nie dodały błędów.
