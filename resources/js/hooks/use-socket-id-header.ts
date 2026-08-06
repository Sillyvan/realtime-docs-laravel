import { http } from '@inertiajs/react';
import { useSocketId } from '@laravel/echo-react';
import { useEffect } from 'react';

/**
 * Puts the Echo socket id on every Inertia request.
 *
 * `broadcast()->toOthers()` reads it to work out which connection caused the
 * change and skip it; without the header the server has nothing to exclude and
 * the person who renamed a document hears about it from themselves. Axios used
 * to attach this automatically, and Inertia v3 dropped Axios.
 */
export function useSocketIdHeader(): void {
    const socketId = useSocketId();

    useEffect(() => {
        if (socketId === undefined) {
            return;
        }

        // onRequest hands back its own remover, which is exactly the cleanup.
        return http.onRequest((config) => {
            config.headers = { ...config.headers, 'X-Socket-Id': socketId };

            return config;
        });
    }, [socketId]);
}
