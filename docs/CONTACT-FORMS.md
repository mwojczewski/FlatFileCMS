# Formularze kontaktowe

Blok `contact-form` przechowuje całą konfigurację w instancji bloku strony.
Administrator może ustawić odbiorcę, pola, ich kolejność i wymagalność oraz
opcjonalną wiadomość potwierdzającą dla osoby wypełniającej formularz.

## Uruchomienie

1. Skonfiguruj `MAIL_*` w `.env.local`.
2. Zmień przykładowy adres `kontakt@example.com` na docelowy adres w edytorze
   bloku strony Kontakt.
3. Ustaw limity `CONTACT_FORM_MAX_ATTEMPTS` i
   `CONTACT_FORM_WINDOW_SECONDS` odpowiednio do ruchu serwisu.
4. Wykonaj migracje CMS-a. Ograniczanie wysyłek korzysta z istniejącej tabeli
   `auth_rate_limits`.

Publiczny endpoint to `POST /forms/contact`. Przeglądarka przesyła wyłącznie
identyfikatory strony i bloku oraz wartości pól. Backend ponownie odczytuje
konfigurację z `content.yml`; adres odbiorcy nie jest publikowany w HTML.

## Ochrona i dostarczalność

- ukryte pole-pułapka odrzuca podstawowe automaty;
- limit wysyłek jest liczony dla adresu IP;
- wartości są sprawdzane według bieżącej konfiguracji bloku;
- pierwszy poprawny adres e-mail jest ustawiany jako `Reply-To`, nigdy `From`;
- potwierdzenie jest osobną wiadomością, więc nie ujawnia adresu odbiorcy;
- treść HTML wiadomości jest kodowana przed wysłaniem.

Przy serwisach wystawionych na intensywny ruch warto rozszerzyć kontroler o
weryfikację wybranego dostawcy CAPTCHA. Moduł celowo nie wiąże CMS-a z jednym
zewnętrznym dostawcą.
