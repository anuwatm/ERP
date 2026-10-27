import { existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(fileURLToPath(import.meta.url));
const modules = existsSync(resolve(root, 'node_modules/react')) ? resolve(root, 'node_modules') : resolve(root, '../backend/node_modules');
export default {
    root,
    publicDir: false,
    resolve: { alias: { 'react-dom/client': resolve(modules, 'react-dom/client.js'), react: resolve(modules, 'react/index.js') } },
    build: { outDir: 'public/build', manifest: 'manifest.json', rollupOptions: { input: [resolve(root, 'resources/pos.tsx'), resolve(root, 'resources/menu.tsx')] } },
};
