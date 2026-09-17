import { readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';

const root = fileURLToPath(new URL('.', import.meta.url));

function testFiles(directory) {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const path = join(directory, entry.name);

        if (entry.isDirectory()) {
            return testFiles(path);
        }

        return entry.isFile() && entry.name.endsWith('.test.js') ? [path] : [];
    });
}

const files = testFiles(root).sort();
const result = spawnSync(process.execPath, ['--test', ...files], {
    cwd: process.cwd(),
    stdio: 'inherit',
});

process.exit(result.status ?? 1);
