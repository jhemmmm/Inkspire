/**
 * A random v4 UUID that also works over plain http.
 *
 * `crypto.randomUUID()` exists only in secure contexts (https, or localhost). The shop PCs open Inkspire over a LAN
 * address such as http://192.168.1.50:8080, where it is undefined and anything calling it throws. `getRandomValues()`
 * is available everywhere, so fall back to building the UUID from it.
 */
export function uuid(cryptoApi: Crypto = globalThis.crypto): string {
    if (typeof cryptoApi.randomUUID === 'function') {
        return cryptoApi.randomUUID();
    }

    const bytes = cryptoApi.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40; // version 4
    bytes[8] = (bytes[8] & 0x3f) | 0x80; // RFC 4122 variant

    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0'));

    return [
        hex.slice(0, 4).join(''),
        hex.slice(4, 6).join(''),
        hex.slice(6, 8).join(''),
        hex.slice(8, 10).join(''),
        hex.slice(10, 16).join(''),
    ].join('-');
}
