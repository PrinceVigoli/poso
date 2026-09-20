import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..');
const publicRoot = path.join(root, 'public');
const required = ['css/portal-base.css', 'css/portal.css', 'css/public.css', 'css/enforcer.css', 'css/landing.css', 'vendor/bootstrap/bootstrap.min.css', 'vendor/bootstrap/bootstrap.bundle.min.js', 'vendor/bootstrap-icons/bootstrap-icons.css', 'vendor/bootstrap-icons/fonts/bootstrap-icons.woff2', 'vendor/fonts/fonts.css'];
const problems = [];
for (const name of required) {
    const file = path.join(publicRoot, name);
    if (!fs.existsSync(file)) { problems.push('Missing '+name); continue; }
    if (name.endsWith('.css')) {
        for (const match of fs.readFileSync(file, 'utf8').matchAll(/url\(\s*["']?([^"')\s]+)["']?\s*\)/g)) {
            if (match[1].startsWith('data:')) continue;
            if (/^https?:/.test(match[1])) { problems.push('External asset in '+name); continue; }
            const target = path.resolve(path.dirname(file), match[1].split('?')[0]);
            if (!target.startsWith(publicRoot + path.sep) || !fs.existsSync(target)) problems.push('Missing reference '+match[1]+' in '+name);
        }
    }
}
for (const name of ['app', 'enforcer', 'public']) {
    const layout = fs.readFileSync(path.join(root, 'resources/views/layouts/'+name+'.blade.php'), 'utf8');
    if (/(?:src|href)="https?:\/\//.test(layout)) problems.push('Remote dependency in '+name+' layout');
}
if (problems.length) { console.error(problems.join('\n')); process.exit(1); }
console.log('Validated locally served application CSS, Bootstrap, icons, and font references.');
