/**
 * Translation Generator
 * 
 * Generates translations for PO files using OpenAI's GPT API.
 * Handles both new translations and updates to existing ones.
 */

const fs = require('fs');
const path = require('path');
const { OpenAI } = require('openai');

// Helper function to escape special characters in regex
function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

class TranslationGenerator {
    constructor(config) {
        this.config = config;
        this.openai = new OpenAI({
            apiKey: config.apiKey
        });
        this.failedStrings = {};
    }

    async translateString(text, targetLanguage, locale) {
        try {
            // Get language-specific context if available
            const langConfig = this.config.languages[locale];
            const context = langConfig.translationContext || {};
            const formality = context.formalityLevel || 'formal';
            const specialInstructions = context.specialInstructions || '';
            
            // Build system prompt with context
            const systemPrompt = `You are a professional translator specializing in WordPress plugin localization. 
Translate text accurately while preserving its meaning and technical context. 
Use ${formality} tone for ${targetLanguage}.
${specialInstructions}
Do not add quotation marks around your translations. Provide only the raw translated text.`;

            const prompt = `Translate the following text to ${targetLanguage}. Provide ONLY the direct translation without any quotation marks, explanations, or additional formatting:
${text}`;

            const response = await this.openai.chat.completions.create({
                model: "gpt-4-turbo",
                messages: [
                    { role: "system", content: systemPrompt },
                    { role: "user", content: prompt }
                ],
                temperature: 0.3,
                max_tokens: 500
            });

            // Clean the response by removing any quotation marks at the beginning and end
            let translation = response.choices[0].message.content.trim();
            
            // Remove surrounding quotes if present (both double and single quotes)
            translation = translation.replace(/^["'](.*)["']$/s, '$1');
            
            // Remove any additional quotation marks that might be present
            translation = translation.replace(/^"|"$/g, '');
            
            return translation;
        } catch (error) {
            console.error(`Error translating string: "${text}"`, error.message);
            // Track failed strings for later resumption
            if (error.message.includes('429')) {
                this.failedStrings[text] = true;
            }
            throw error;
        }
    }

    async translateBatch(strings, targetLanguage, locale, resumeMode = false) {
        const translations = {};
        let completed = 0;
        const total = Object.keys(strings).length;
        let rateLimitHit = false;
        
        // Create a log file path for tracking failed translations
        const logDir = path.join(__dirname, '../.data');
        if (!fs.existsSync(logDir)) {
            fs.mkdirSync(logDir, { recursive: true });
        }
        const failedLogPath = path.join(logDir, `failed-translations-${locale}.json`);

        // Load previously failed translations if in resume mode
        let previouslyFailedStrings = {};
        if (resumeMode && fs.existsSync(failedLogPath)) {
            try {
                previouslyFailedStrings = JSON.parse(fs.readFileSync(failedLogPath, 'utf8'));
                console.log(`Loaded ${Object.keys(previouslyFailedStrings).length} previously failed translations to resume`);
            } catch (error) {
                console.error('Error loading failed translations log:', error.message);
                previouslyFailedStrings = {};
            }
        }

        // Combine current strings with previously failed ones if in resume mode
        const stringsToTranslate = resumeMode ? { ...strings, ...previouslyFailedStrings } : strings;

        for (const [msgid, content] of Object.entries(stringsToTranslate)) {
            try {
                process.stdout.write(`\rTranslating string ${++completed}/${total}...`);
                translations[msgid] = await this.translateString(content, targetLanguage, locale);
                
                // Add a small delay to avoid rate limiting
                await new Promise(resolve => setTimeout(resolve, 200));
            } catch (error) {
                console.error(`\nFailed to translate: "${msgid}"`, error.message);
                
                if (error.message.includes('429')) {
                    rateLimitHit = true;
                    this.failedStrings[msgid] = content;
                }
                
                // Keep existing translation if available, otherwise empty string
                translations[msgid] = '';
            }
        }
        
        // Save failed translations to a log file for later resumption
        if (Object.keys(this.failedStrings).length > 0) {
            fs.writeFileSync(failedLogPath, JSON.stringify(this.failedStrings, null, 2));
            console.log(`\n⚠️ Rate limit hit. ${Object.keys(this.failedStrings).length} translations failed and saved to ${failedLogPath}`);
            console.log(`You can resume these translations later with the --resume option.`);
        } else if (resumeMode && fs.existsSync(failedLogPath)) {
            // If we're in resume mode and all translations succeeded, remove the log file
            fs.unlinkSync(failedLogPath);
            console.log(`\n✅ All previously failed translations completed successfully!`);
        }
        
        console.log('\n');
        return { translations, rateLimitHit };
    }

    async translateStrings(strings, locale) {
        try {
            const { translations, rateLimitHit } = await this.translateBatch(
                strings,
                this.config.languages[locale].name,
                locale
            );
            
            return translations;
        } catch (error) {
            console.error(`Error translating strings for ${locale}:`, error.message);
            throw error;
        }
    }
    
    async updatePoFile(locale, translations) {
        try {
            // Get the PO file path
            const poFile = path.join(__dirname, '../../languages', `radle-lite-${locale}.po`);
            
            if (!fs.existsSync(poFile)) {
                throw new Error(`PO file for ${locale} not found`);
            }
            
            // Read the current PO file
            let poContent = fs.readFileSync(poFile, 'utf8');
            
            // Update each translation in the PO file
            for (const [original, translation] of Object.entries(translations)) {
                // Escape special regex characters in the original string
                const escapedOriginal = escapeRegExp(original);
                
                // Create a regex to find the msgid/msgstr pair
                const regex = new RegExp(`(msgid\\s+"${escapedOriginal}"\\s+msgstr\\s+)("")`, 'g');
                
                // Replace the empty msgstr with the translation
                poContent = poContent.replace(regex, `$1"${translation}"`);
            }
            
            // Write the updated PO file
            fs.writeFileSync(poFile, poContent);
            console.log(`Updated PO file for ${locale} with ${Object.keys(translations).length} translations`);
            
            return true;
        } catch (error) {
            console.error(`Error updating PO file for ${locale}:`, error.message);
            throw error;
        }
    }

    async generatePOContent(translations, locale) {
        const now = new Date().toISOString();
        const header = `# Translation ofRadle Lite in ${this.config.languages[locale].name}
# This file is distributed under the same license as theRadle Lite plugin.
msgid ""
msgstr ""
"Project-Id-Version:Radle Lite 1.0.7.1\\n"
"Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/radle-lite\\n"
"POT-Creation-Date: ${now}\\n"
"PO-Revision-Date: ${now}\\n"
"Last-Translator:Radle Lite Translation System <opportunities@gbti.network>\\n"
"Language-Team: ${this.config.languages[locale].name} <${locale}@li.org>\\n"
"Language: ${locale}\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"X-Generator:Radle Lite Translation Generator 1.0\\n"
"X-Domain: radle-lite\\n"\\n\\n`;

        const entries = Object.entries(translations)
            .map(([msgid, msgstr]) => {
                // Properly escape the strings
                const escapedMsgid = this.escapeString(msgid);
                const escapedMsgstr = this.escapeString(msgstr);
                return `msgid "${escapedMsgid}"\nmsgstr "${escapedMsgstr}"\n\n`;
            })
            .join('');

        return header + entries;
    }

    escapeString(str) {
        // First, clean any extraneous quotes that might have been added by the translation
        let cleanedStr = str.trim();
        
        // Remove surrounding quotes if present
        cleanedStr = cleanedStr.replace(/^["'](.*)["']$/s, '$1');
        
        // Now escape the string properly for PO file format
        return cleanedStr
            .replace(/\\/g, '\\\\')
            .replace(/"/g, '\\"')
            .replace(/\n/g, '\\n')
            .replace(/\r/g, '\\r')
            .replace(/\t/g, '\\t');
    }

    unescapeString(str) {
        return str
            .replace(/\\"/g, '"')
            .replace(/\\n/g, '\n')
            .replace(/\\r/g, '\r')
            .replace(/\\t/g, '\t')
            .replace(/\\\\/g, '\\');
    }

    async mergeWithExisting(newTranslations, locale) {
        const poFile = path.join(__dirname, '../../languages', `radle-lite-${locale}.po`);
        let existingTranslations = {};

        if (fs.existsSync(poFile)) {
            const content = fs.readFileSync(poFile, 'utf8');
            const entries = content.matchAll(/msgid "(.*?)"\nmsgstr "(.*?)"\n/g);
            
            for (const [, msgid, msgstr] of entries) {
                if (msgid && msgstr) {
                    existingTranslations[this.unescapeString(msgid)] = this.unescapeString(msgstr);
                }
            }
        }

        return {
            ...existingTranslations,
            ...newTranslations
        };
    }

    async findEmptyTranslations(locale) {
        const poFile = path.join(__dirname, '../../languages', `radle-lite-${locale}.po`);
        const emptyTranslations = {};
        
        if (fs.existsSync(poFile)) {
            // Improved regex to handle multiline msgid/msgstr entries
            const pattern = /msgid\s+(?:"([^"\\]*(?:\\.[^"\\]*)*)"\s+)+msgstr\s+(?:"([^"\\]*(?:\\.[^"\\]*)*)"\s+)+/g;
            
            try {
                const content = fs.readFileSync(poFile, 'utf8');
                
                // Split content into entries (separated by empty lines)
                const entries = content.split('\n\n');
                let count = 0;
                
                // Skip the header (first entry)
                for (let i = 1; i < entries.length; i++) {
                    const entry = entries[i];
                    
                    // Extract msgid and msgstr using more reliable parsing
                    let msgid = '';
                    let msgstr = '';
                    
                    // Extract msgid - handle multiline
                    const msgidMatch = entry.match(/msgid\s+"(.+?)"\s+(?=msgstr)/s);
                    if (msgidMatch) {
                        // Handle potential multiline msgid
                        const msgidLines = msgidMatch[1].split('"\n"');
                        msgid = msgidLines.join('');
                    }
                    
                    // Extract msgstr - handle multiline
                    const msgstrMatch = entry.match(/msgstr\s+"(.*?)"\s*(?=\n\n|\n#|$)/s);
                    if (msgstrMatch) {
                        // Handle potential multiline msgstr
                        const msgstrLines = msgstrMatch[1].split('"\n"');
                        msgstr = msgstrLines.join('');
                    }
                    
                    // Skip empty msgid (header) and only count non-empty strings as untranslated
                    if (msgid && msgid.trim() !== '' && (!msgstr || msgstr.trim() === '')) {
                        emptyTranslations[msgid] = msgid;
                        count++;
                    }
                }
                
                console.log(`Found ${count} empty translations in ${locale} PO file`);
            } catch (error) {
                console.error(`Error reading PO file for ${locale}:`, error.message);
            }
        } else {
            console.log(`PO file for ${locale} not found at ${poFile}`);
            
            // If PO file doesn't exist, check POT file to get all strings
            const potFile = path.join(__dirname, '../../languages/radle-lite.pot');
            if (fs.existsSync(potFile)) {
                try {
                    const potContent = fs.readFileSync(potFile, 'utf8');
                    let count = 0;
                    
                    // Extract all msgid entries from POT
                    const potMatches = potContent.matchAll(/msgid\s+"(.+?)"\s+(?=msgstr)/gs);
                    for (const match of Array.from(potMatches)) {
                        if (match[1] && match[1].trim() !== '' && match[1] !== '""') {
                            emptyTranslations[match[1]] = match[1];
                            count++;
                        }
                    }
                    
                    console.log(`Found ${count} strings in POT file to translate for new ${locale} PO file`);
                } catch (error) {
                    console.error(`Error reading POT file:`, error.message);
                }
            } else {
                console.error(`POT file not found. Cannot determine strings to translate for ${locale}.`);
            }
        }

        return emptyTranslations;
    }

    async generateTranslations(strings, locale, resumeMode = false, createNewPO = false, completePO = false) {
        console.log(`Generating translations for ${this.config.languages[locale].name}...`);

        let stringsToTranslate = strings;
        let totalStrings = Object.keys(stringsToTranslate).length;
        let translatedCount = 0;
        let rateLimitHit = false;
        
        // Handle creating a new PO file
        if (createNewPO) {
            console.log(`Creating new PO file for ${locale}...`);
            
            // Get the POT file to use as a template
            const potFile = path.join(__dirname, '../../languages/radle-lite.pot');
            if (!fs.existsSync(potFile)) {
                throw new Error('POT file not found. Run "npm run makepot" first.');
            }
            
            // Create a new PO file based on the POT file
            const poFile = path.join(__dirname, `../../languages/radle-lite-${locale}.po`);
            
            // Copy POT to PO and update the header
            let potContent = fs.readFileSync(potFile, 'utf8');
            
            // Update header for the specific locale
            const langInfo = this.config.languages[locale];
            potContent = potContent.replace(
                'msgid ""\nmsgstr ""',
                `msgid ""\nmsgstr ""\n"Language: ${locale}\\n"\n"Language-Team: ${langInfo.name}\\n"\n"Plural-Forms: nplurals=2; plural=(n != 1);\\n"`
            );
            
            // Write the initial PO file
            fs.writeFileSync(poFile, potContent);
            console.log(`Created new PO file for ${locale}`);
            
            // Now find all empty translations to translate
            stringsToTranslate = await this.findEmptyTranslations(locale);
            totalStrings = Object.keys(stringsToTranslate).length;
            console.log(`Found ${totalStrings} strings to translate in new PO file`);
        }
        
        // Handle resume mode or completing incomplete PO files
        if (resumeMode || completePO) {
            console.log('Resume mode detected, checking for empty translations...');
            
            // Find all empty translations in the PO file
            const emptyTranslations = await this.findEmptyTranslations(locale);
            
            // If completePO is true, also check for missing strings compared to POT
            if (completePO) {
                console.log('Checking for missing strings compared to POT file...');
                const potFile = path.join(__dirname, '../../languages/radle-lite.pot');
                const poFile = path.join(__dirname, `../../languages/radle-lite-${locale}.po`);
                
                if (fs.existsSync(potFile) && fs.existsSync(poFile)) {
                    const potContent = fs.readFileSync(potFile, 'utf8');
                    const poContent = fs.readFileSync(poFile, 'utf8');
                    
                    // Extract all msgid entries from POT and PO files
                    const potMsgIds = new Set();
                    const poMsgIds = new Set();
                    
                    // Extract msgids from POT
                    const potMatches = potContent.matchAll(/msgid\s+"(.+?)"\s+(?=msgstr)/gs);
                    for (const match of potMatches) {
                        if (match[1] && match[1].trim() !== '') {
                            potMsgIds.add(match[1]);
                        }
                    }
                    
                    // Extract msgids from PO
                    const poMatches = poContent.matchAll(/msgid\s+"(.+?)"\s+(?=msgstr)/gs);
                    for (const match of poMatches) {
                        if (match[1] && match[1].trim() !== '') {
                            poMsgIds.add(match[1]);
                        }
                    }
                    
                    // Find msgids in POT but not in PO
                    const missingMsgIds = [...potMsgIds].filter(msgid => !poMsgIds.has(msgid));
                    
                    if (missingMsgIds.length > 0) {
                        console.log(`Found ${missingMsgIds.length} strings in POT that are missing from PO file`);
                        
                        // Add missing entries to the PO file
                        let poContentUpdated = poContent;
                        
                        for (const msgid of missingMsgIds) {
                            // Find the complete entry in POT
                            const entryRegex = new RegExp(`(#:.+?\\n)?msgid\\s+"${escapeRegExp(msgid)}"\\s+msgstr\\s+".*?"`, 'gs');
                            const entryMatch = potContent.match(entryRegex);
                            
                            if (entryMatch && entryMatch[0]) {
                                // Add to PO file with empty msgstr
                                const newEntry = entryMatch[0].replace(/msgstr\s+".*?"/, 'msgstr ""');
                                poContentUpdated += `\n\n${newEntry}`;
                                
                                // Add to strings to translate
                                emptyTranslations[msgid] = msgid;
                            }
                        }
                        
                        // Write updated PO file
                        fs.writeFileSync(poFile, poContentUpdated);
                        console.log(`Updated PO file with ${missingMsgIds.length} missing entries`);
                    }
                }
            }
            
            if (Object.keys(emptyTranslations).length > 0) {
                console.log(`Found ${Object.keys(emptyTranslations).length} empty translations to resume`);
                stringsToTranslate = emptyTranslations;
                totalStrings = Object.keys(stringsToTranslate).length;
            } else {
                console.log('No empty translations found to resume');
                return { translatedCount: 0, totalStrings: 0, rateLimitHit };
            }
        }

        if (totalStrings === 0) {
            console.log('No strings to translate');
            return { translatedCount: 0, totalStrings: 0, rateLimitHit };
        }

        console.log(`Translating ${totalStrings} strings for ${locale}...`);

        // Get language info
        const langInfo = this.config.languages[locale];
        if (!langInfo) {
            throw new Error(`Language ${locale} not found in configuration`);
        }

        // Batch strings for translation
        const batchSize = 5; // Adjust based on token limits and performance
        const stringEntries = Object.entries(stringsToTranslate);
        const batches = [];

        for (let i = 0; i < stringEntries.length; i += batchSize) {
            batches.push(stringEntries.slice(i, i + batchSize));
        }

        console.log(`Split into ${batches.length} batches of up to ${batchSize} strings each`);

        // Process each batch
        for (let i = 0; i < batches.length; i++) {
            const batch = batches[i];
            const batchStrings = {};
            batch.forEach(([key, value]) => {
                batchStrings[key] = value;
            });

            try {
                console.log(`Processing batch ${i + 1}/${batches.length} (${Object.keys(batchStrings).length} strings)`);
                
                const translations = await this.translateStrings(batchStrings, locale);
                
                // Update PO file with translations
                await this.updatePoFile(locale, translations);
                
                translatedCount += Object.keys(translations).length;
                console.log(`Translated ${translatedCount}/${totalStrings} strings so far`);
                
                // Add a small delay between batches to avoid rate limiting
                if (i < batches.length - 1) {
                    await new Promise(resolve => setTimeout(resolve, 1000));
                }
            } catch (error) {
                console.error(`Error processing batch ${i + 1}:`, error.message);
                
                if (error.message.includes('rate limit') || error.message.includes('429')) {
                    console.log('Rate limit hit, pausing translations');
                    rateLimitHit = true;
                    break;
                }
                
                // Continue with next batch on error
                console.log('Continuing with next batch...');
            }
        }

        console.log(`Completed translation for ${locale}: ${translatedCount}/${totalStrings} strings translated`);
        return { translatedCount, totalStrings, rateLimitHit };
    }
}

async function generateTranslations({ locale, strings, config, apiKey, resumeMode = false, createNewPO = false, completePO = false }) {
    try {
        const generator = new TranslationGenerator({
            ...config,
            languages: config.languages,
            apiKey
        });

        // Special handling for creating new PO files
        if (createNewPO) {
            console.log(`Creating new PO file for ${locale} with ${Object.keys(strings).length} strings`);
            return await generator.generateTranslations(strings, locale, false, true);
        }

        // Special handling for completing incomplete PO files
        if (completePO) {
            console.log(`Completing PO file for ${locale}`);
            return await generator.generateTranslations({}, locale, true, false, true);
        }

        return await generator.generateTranslations(strings, locale, resumeMode);
    } catch (error) {
        console.error('Error generating translations:', error);
        throw error;
    }
}

module.exports = generateTranslations;
