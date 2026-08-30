const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const error = document.querySelector('[data-auth-error]');

const decode = (value) => {
  const base64 = value.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(value.length / 4) * 4, '=');
  return Uint8Array.from(atob(base64), (character) => character.charCodeAt(0));
};

const encode = (value) => btoa(String.fromCharCode(...new Uint8Array(value)))
  .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

const request = async (url, body = {}) => {
  const response = await fetch(url, {
    method: 'POST',
    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf},
    body: JSON.stringify(body),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(data.error?.message ?? 'Operacja nie powiodła się.');
  return data;
};

const prepare = (options) => {
  options.publicKey.challenge = decode(options.publicKey.challenge);
  if (options.publicKey.user) options.publicKey.user.id = decode(options.publicKey.user.id);
  for (const key of ['allowCredentials', 'excludeCredentials']) {
    if (options.publicKey[key]) options.publicKey[key].forEach((credential) => { credential.id = decode(credential.id); });
  }
  return options;
};

document.querySelector('[data-webauthn-login]')?.addEventListener('click', async () => {
  try {
    const options = prepare(await request('/admin/webauthn/authentication/options'));
    const credential = await navigator.credentials.get(options);
    const result = await request('/admin/webauthn/authentication/verify', {
      id: encode(credential.rawId),
      clientDataJSON: encode(credential.response.clientDataJSON),
      authenticatorData: encode(credential.response.authenticatorData),
      signature: encode(credential.response.signature),
    });
    window.location.assign(result.redirect);
  } catch (exception) { error.textContent = exception.message; }
});

document.querySelector('[data-webauthn-register]')?.addEventListener('submit', async (event) => {
  event.preventDefault();
  try {
    const form = new FormData(event.currentTarget);
    const options = prepare(await request('/admin/security/webauthn/registration/options', {password: form.get('current_password')}));
    const credential = await navigator.credentials.create(options);
    const transports = credential.response.getTransports ? credential.response.getTransports() : [];
    const result = await request('/admin/security/webauthn/registration/verify', {
      name: form.get('key_name'),
      credential: {
        id: encode(credential.rawId),
        clientDataJSON: encode(credential.response.clientDataJSON),
        attestationObject: encode(credential.response.attestationObject),
        transports,
      },
    });
    window.location.assign(result.redirect);
  } catch (exception) { error.textContent = exception.message; }
});
