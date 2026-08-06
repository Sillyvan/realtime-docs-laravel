/**
 * Yjs speaks in binary, the wire (JSON whispers, Inertia props, the autosave
 * body) speaks in text. These two are the only crossing points.
 */

/** Chunked so a large document doesn't blow the argument limit of `apply`. */
const CHUNK = 0x8000;

export function toBase64(bytes: Uint8Array): string {
    let binary = '';

    for (let i = 0; i < bytes.length; i += CHUNK) {
        binary += String.fromCharCode(...bytes.subarray(i, i + CHUNK));
    }

    return btoa(binary);
}

export function fromBase64(value: string): Uint8Array {
    const binary = atob(value);
    const bytes = new Uint8Array(binary.length);

    for (let i = 0; i < binary.length; i += 1) {
        bytes[i] = binary.charCodeAt(i);
    }

    return bytes;
}
