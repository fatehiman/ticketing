// Copies the self-hosted TinyMCE editor (and its Persian language pack) into public/vendor.
import { cpSync, mkdirSync, rmSync } from 'node:fs';

const src = 'node_modules/tinymce';
const dest = 'public/vendor/tinymce';

rmSync(dest, { recursive: true, force: true });
mkdirSync(dest, { recursive: true });
for (const item of ['tinymce.min.js', 'license.md', 'icons', 'models', 'plugins', 'skins', 'themes']) {
    cpSync(`${src}/${item}`, `${dest}/${item}`, { recursive: true });
}
mkdirSync(`${dest}/langs`, { recursive: true });
cpSync('node_modules/tinymce-i18n/langs7/fa.js', `${dest}/langs/fa.js`);
console.log('TinyMCE copied to', dest);
