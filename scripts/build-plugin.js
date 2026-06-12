#!/usr/bin/env node
/**
 * Radle — standalone build/package script.
 *
 * Produces a clean, install-ready plugin build with NO external dependencies
 * (pure Node built-ins), so it runs without `npm install`:
 *   - build/radle-lite/          unpacked plugin (dev/VCS/data files excluded)
 *   - dist/radle-lite-<ver>.zip  installable archive (top-level radle-lite/ folder)
 *
 * The version is read from the radle-lite.php header.
 *
 * Usage:
 *   node scripts/build-plugin.js          # build/ mirror + dist/ zip
 *   node scripts/build-plugin.js --no-zip # only refresh build/radle-lite/
 *   node scripts/build-plugin.js --no-dir # only (re)create the dist/ zip
 *
 * Exclusions mirror scripts/deploy.js: VCS dirs, node_modules, scripts, svn,
 * build/dist, all dotfiles/dotdirs, .data/.product/.snapshots/.claude, CLAUDE.md,
 * readme.md, package*.json, *.log, and translation sources (*.po/*.pot — only the
 * compiled *.mo files ship).
 */

'use strict';

const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const PLUGIN_SLUG = 'radle-lite';
const ROOT = path.resolve(__dirname, '..');
const BUILD_DIR = path.join(ROOT, 'build');
const PLUGIN_BUILD_DIR = path.join(BUILD_DIR, PLUGIN_SLUG);
const DIST_DIR = path.join(ROOT, 'dist');
const MAIN_FILE = path.join(ROOT, `${PLUGIN_SLUG}.php`);

// Names excluded anywhere in the tree.
const EXCLUDE_NAMES = new Set([
    'build', 'dist', 'node_modules', 'scripts', 'svn',
    'CLAUDE.md', 'package.json', 'package-lock.json',
]);
// Extensions excluded (translation sources + logs; only compiled .mo ships).
const EXCLUDE_EXT = new Set(['.po', '.pot', '.log']);

function shouldSkip(name) {
    if (name.startsWith('.')) return true;              // all dotfiles/dotdirs (.git, .data, .env, ...)
    if (EXCLUDE_NAMES.has(name)) return true;
    if (/^readme\.md$/i.test(name)) return true;        // keep readme.txt, drop readme.md
    if (EXCLUDE_EXT.has(path.extname(name).toLowerCase())) return true;
    return false;
}

function getVersion() {
    const php = fs.readFileSync(MAIN_FILE, 'utf8');
    const m = php.match(/Version:\s*([0-9]+\.[0-9]+\.[0-9]+(?:[-\w.]*)?)/);
    if (!m) throw new Error('Could not read "Version:" from ' + MAIN_FILE);
    return m[1];
}

// Recursively collect files to include; rel paths use '/'.
function collectFiles(dir, relBase) {
    const out = [];
    for (const name of fs.readdirSync(dir).sort()) {
        if (shouldSkip(name)) continue;
        const abs = path.join(dir, name);
        const rel = relBase ? relBase + '/' + name : name;
        const st = fs.statSync(abs);
        if (st.isDirectory()) out.push(...collectFiles(abs, rel));
        else if (st.isFile()) out.push({ abs, rel });
    }
    return out;
}

function copyToBuild(files) {
    fs.rmSync(PLUGIN_BUILD_DIR, { recursive: true, force: true });
    for (const f of files) {
        const dest = path.join(PLUGIN_BUILD_DIR, f.rel);
        fs.mkdirSync(path.dirname(dest), { recursive: true });
        fs.copyFileSync(f.abs, dest);
    }
}

// --- Minimal, dependency-free ZIP writer (STORE / DEFLATE) ---
const CRC_TABLE = (function () {
    const t = new Uint32Array(256);
    for (let n = 0; n < 256; n++) {
        let c = n;
        for (let k = 0; k < 8; k++) c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
        t[n] = c >>> 0;
    }
    return t;
})();

function crc32(buf) {
    let c = 0xFFFFFFFF;
    for (let i = 0; i < buf.length; i++) c = CRC_TABLE[(c ^ buf[i]) & 0xFF] ^ (c >>> 8);
    return (c ^ 0xFFFFFFFF) >>> 0;
}

function dosDateTime(d) {
    const time = ((d.getHours() & 0x1f) << 11) | ((d.getMinutes() & 0x3f) << 5) | ((Math.floor(d.getSeconds() / 2)) & 0x1f);
    const date = (((d.getFullYear() - 1980) & 0x7f) << 9) | (((d.getMonth() + 1) & 0x0f) << 5) | (d.getDate() & 0x1f);
    return { time: time & 0xffff, date: date & 0xffff };
}

function createZip(files, zipPath, topFolder) {
    const dt = dosDateTime(new Date());
    const localChunks = [];
    const centralChunks = [];
    let offset = 0;

    for (const f of files) {
        const nameBuf = Buffer.from(topFolder + '/' + f.rel, 'utf8');
        const data = fs.readFileSync(f.abs);
        const crc = crc32(data);
        const deflated = zlib.deflateRawSync(data, { level: 9 });
        const useDeflate = deflated.length < data.length;
        const method = useDeflate ? 8 : 0;
        const body = useDeflate ? deflated : data;

        const local = Buffer.alloc(30);
        local.writeUInt32LE(0x04034b50, 0);
        local.writeUInt16LE(20, 4);
        local.writeUInt16LE(0, 6);
        local.writeUInt16LE(method, 8);
        local.writeUInt16LE(dt.time, 10);
        local.writeUInt16LE(dt.date, 12);
        local.writeUInt32LE(crc, 14);
        local.writeUInt32LE(body.length, 18);
        local.writeUInt32LE(data.length, 22);
        local.writeUInt16LE(nameBuf.length, 26);
        local.writeUInt16LE(0, 28);
        localChunks.push(local, nameBuf, body);

        const cd = Buffer.alloc(46);
        cd.writeUInt32LE(0x02014b50, 0);
        cd.writeUInt16LE(20, 4);
        cd.writeUInt16LE(20, 6);
        cd.writeUInt16LE(0, 8);
        cd.writeUInt16LE(method, 10);
        cd.writeUInt16LE(dt.time, 12);
        cd.writeUInt16LE(dt.date, 14);
        cd.writeUInt32LE(crc, 16);
        cd.writeUInt32LE(body.length, 20);
        cd.writeUInt32LE(data.length, 24);
        cd.writeUInt16LE(nameBuf.length, 28);
        cd.writeUInt16LE(0, 30);
        cd.writeUInt16LE(0, 32);
        cd.writeUInt16LE(0, 34);
        cd.writeUInt16LE(0, 36);
        cd.writeUInt32LE(0, 38);
        cd.writeUInt32LE(offset, 42);
        centralChunks.push(cd, nameBuf);

        offset += local.length + nameBuf.length + body.length;
    }

    const centralBuf = Buffer.concat(centralChunks);
    const end = Buffer.alloc(22);
    end.writeUInt32LE(0x06054b50, 0);
    end.writeUInt16LE(0, 4);
    end.writeUInt16LE(0, 6);
    end.writeUInt16LE(files.length, 8);
    end.writeUInt16LE(files.length, 10);
    end.writeUInt32LE(centralBuf.length, 12);
    end.writeUInt32LE(offset, 16);
    end.writeUInt16LE(0, 20);

    fs.mkdirSync(path.dirname(zipPath), { recursive: true });
    fs.writeFileSync(zipPath, Buffer.concat(localChunks.concat([centralBuf, end])));
}

function main() {
    const args = process.argv.slice(2);
    const doDir = !args.includes('--no-dir');
    const doZip = !args.includes('--no-zip');

    const version = getVersion();
    console.log(`Building ${PLUGIN_SLUG} v${version}...`);

    const files = collectFiles(ROOT, '');
    console.log(`  collected ${files.length} files`);

    if (doDir) {
        copyToBuild(files);
        console.log(`  ✓ build/${PLUGIN_SLUG}/ refreshed`);
    }

    if (doZip) {
        const zipName = `${PLUGIN_SLUG}-${version}.zip`;
        const zipPath = path.join(DIST_DIR, zipName);
        createZip(files, zipPath, PLUGIN_SLUG);
        const sizeMB = (fs.statSync(zipPath).size / 1024 / 1024).toFixed(2);
        console.log(`  ✓ dist/${zipName} (${sizeMB} MB)`);
    }

    console.log('Done.');
}

main();
