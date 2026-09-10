import { spawn } from 'node:child_process';
import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defaultAllowedOrigins } from 'vite';
import { defineConfig, lazyPlugins } from 'vite-plus';

const vitePort = 5173;

const config = defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Plus Jakarta Sans', {
                    weights: [400, 500, 600, 700, 800],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'demo/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'demo/**',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            entryPoint: 'resources/css/app.css',
        },
    },
});

/**
 * Boot a Cloudflare Quick Tunnel in front of the Vite dev server, resolving
 * with the public URL cloudflared prints on stderr once the tunnel is up.
 */
function startQuickTunnel(port: number): Promise<string> {
    return new Promise((resolve, reject) => {
        const tunnel = spawn(
            'cloudflared',
            ['tunnel', '--url', `http://127.0.0.1:${port}`],
            { stdio: ['ignore', 'ignore', 'pipe'] },
        );

        const timeout = setTimeout(
            () => reject(new Error('cloudflared reported no URL within 30s.')),
            30_000,
        );

        // Ctrl-C reaches cloudflared through the terminal's process group;
        // this only covers Vite exiting on its own.
        process.on('exit', () => tunnel.kill());

        tunnel.on('error', reject);
        tunnel.stderr.on('data', (chunk: Buffer) => {
            const [url] =
                chunk
                    .toString()
                    .match(/https:\/\/[-\w]+\.trycloudflare\.com/) ?? [];

            if (!url) {
                return;
            }

            clearTimeout(timeout);
            resolve(url);
        });
    });
}

/**
 * `TUNNEL=1` (see composer's "dev:tunnel") exposes the running dev stack over
 * a Cloudflare Quick Tunnel. cloudflared fronts Vite rather than Laravel, and
 * Vite proxies everything it does not serve itself to `artisan serve`, so the
 * app, its dev assets and the HMR socket all share one HTTPS origin.
 */
export default defineConfig(async () => {
    if (!process.env.TUNNEL) {
        return config;
    }

    const url = await startQuickTunnel(vitePort);

    return {
        ...config,
        plugins: [
            config.plugins,
            {
                name: 'inkspire:tunnel-urls',
                configureServer(server) {
                    // Inertia builds its SSR stylesheet <link> tags from
                    // resolvedUrls (not server.origin), which would emit
                    // http://localhost URLs the tunnelled page blocks as
                    // mixed content. Vite prepends its own listener, so this
                    // one runs after the URLs it would otherwise resolve.
                    server.httpServer?.once('listening', () => {
                        server.resolvedUrls = {
                            local: [`${url}/`],
                            network: [],
                        };
                    });
                },
            },
        ],
        server: {
            ...config.server,
            port: vitePort,
            // cloudflared is already pointed at this port, so drifting to the
            // next free one would silently tunnel to nothing.
            strictPort: true,
            // Written to public/hot, so @vite emits HTTPS tunnel URLs. Local
            // http://127.0.0.1:8000 keeps working; it just pulls dev assets
            // (and HMR) over the tunnel while this is running.
            origin: url,
            allowedHosts: [new URL(url).hostname],
            cors: { origin: [defaultAllowedOrigins, url] },
            proxy: {
                '^(?!/(@|__|resources/|node_modules/))': {
                    target: 'http://127.0.0.1:8000',
                },
            },
        },
    };
});
