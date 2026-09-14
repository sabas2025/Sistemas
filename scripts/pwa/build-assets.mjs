import fs from 'node:fs/promises';
import path from 'node:path';
import CleanCSS from 'clean-css';
import { minify } from 'terser';

const root = path.resolve(new URL('../..', import.meta.url).pathname);
const assetDir = path.join(root, 'public', 'assets');
const cssFiles = ['app.css','responsive-enterprise.css','scroll-enterprise.css','minimalist-enterprise.css','login.css','bootstrap-login-lite.css','offline.css'];
const jsFiles = ['pwa.js','notifications.js','futuristic-ui.js','minimalist-ui.js','offline.js'];

for (const file of cssFiles) {
  const input = await fs.readFile(path.join(assetDir, file), 'utf8');
  const result = new CleanCSS({ level: 2 }).minify(input);
  if (result.errors.length) throw new Error(`${file}: ${result.errors.join('; ')}`);
  await fs.writeFile(path.join(assetDir, file.replace(/\.css$/, '.min.css')), result.styles);
}
for (const file of jsFiles) {
  const input = await fs.readFile(path.join(assetDir, file), 'utf8');
  const result = await minify(input, { compress: true, mangle: true, format: { comments: false } });
  if (!result.code) throw new Error(`Falha ao minificar ${file}`);
  await fs.writeFile(path.join(assetDir, file.replace(/\.js$/, '.min.js')), result.code);
}
console.log('Assets PWA minificados com validação.');
