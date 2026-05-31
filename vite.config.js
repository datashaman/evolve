import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/blog.css',
                'resources/js/app.js',
                'resources/js/passkeys.js',
            ],
            refresh: [
                'app/**/*.php',
                'routes/**/*.php',
                'resources/views/dashboard.blade.php',
                'resources/views/workbench.blade.php',
                'resources/views/evolve/**/*.blade.php',
                'resources/views/flux/**/*.blade.php',
                'resources/views/partials/**/*.blade.php',
                'resources/views/welcome.blade.php',
            ],
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
