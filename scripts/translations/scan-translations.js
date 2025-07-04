/**
 * WordPress Translation String Scanner
 * 
 * Scans PHP files for translatable strings and generates the POT template.
 */

const fs = require('fs');
const path = require('path');
const glob = require('glob');
const { execSync } = require('child_process');

// WordPress translation function patterns
const translationPatterns = [
    /__\(['"](.+?)['"]\s*,\s*['"]radle-lite['"]\)/g,
    /_e\(['"](.+?)['"]\s*,\s*['"]radle-lite['"]\)/g,
    /esc_html__\(['"](.+?)['"]\s*,\s*['"]radle-lite['"]\)/g,
    /esc_attr__\(['"](.+?)['"]\s*,\s*['"]radle-lite['"]\)/g,
    /esc_html_e\(['"](.+?)['"]\s*,\s*['"]radle-lite['"]\)/g,
    /esc_attr_e\(['"](.+?)['"]\s*,\s*['"]radle-lite['"]\)/g
];

async function scanFile(filePath) {
    const content = fs.readFileSync(filePath, 'utf8');
    const strings = {};
    let match;

    for (const pattern of translationPatterns) {
        while ((match = pattern.exec(content)) !== null) {
            const [, string] = match;
            strings[string] = string;
        }
    }

    return strings;
}

async function scanDirectory(directory) {
    return new Promise((resolve, reject) => {
        glob('**/*.php', { cwd: directory }, async (err, files) => {
            if (err) return reject(err);

            const allStrings = {};
            for (const file of files) {
                const filePath = path.join(directory, file);
                const fileStrings = await scanFile(filePath);
                Object.assign(allStrings, fileStrings);
            }

            resolve(allStrings);
        });
    });
}

async function generatePOT(strings) {
    const potPath = path.join(__dirname, '../../languages/radle-lite.pot');
    const potDir = path.dirname(potPath);

    // Ensure languages directory exists
    if (!fs.existsSync(potDir)) {
        fs.mkdirSync(potDir, { recursive: true });
    }

    // Generate POT header
    const potContent = `# Copyright (C) ${new Date().getFullYear()} GBTI
# This file is distributed under the same license as the Radle Lite plugin.
msgid ""
msgstr ""
"Project-Id-Version:Radle Lite \\n"
"Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/radle-lite\\n"
"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n"
"Language-Team: LANGUAGE <LL@li.org>\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"POT-Creation-Date: ${new Date().toISOString()}\\n"
"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"
"X-Generator:Radle Lite Translation Scanner 1.0\\n"
"X-Domain: radle-lite\\n"\\n\\n`;

    // Add string entries
    const entries = Object.entries(strings)
        .map(([msgid]) => `msgid "${msgid}"\nmsgstr ""\n\n`)
        .join('');

    fs.writeFileSync(potPath, potContent + entries);
    return potPath;
}

async function scanTranslations() {
    try {
        console.log('Scanning PHP files for translatable strings...');
        
        // Get plugin root directory
        const pluginDir = path.join(__dirname, '../..');
        
        // Scan for translatable strings
        const strings = await scanDirectory(pluginDir);
        
        // Generate POT file
        const potFile = await generatePOT(strings);
        
        console.log(`✓ Generated POT file: ${potFile}`);
        console.log(`✓ Found ${Object.keys(strings).length} translatable strings`);
        
        return {
            strings,
            potFile
        };
    } catch (error) {
        console.error('Error scanning translations:', error);
        throw error;
    }
}

module.exports = scanTranslations;
