/**
 *Radle Lite Translation System - Main Script
 * 
 * Orchestrates the translation workflow:
 * 1. Generates POT file and tracks string changes
 * 2. Prompts user for translation mode
 * 3. Generates translations
 * 4. Compiles MO files (optional)
 * 5. Generates report
 */

 const generatePOT = require('./translations/generate-pot');
 const generateTranslations = require('./translations/generate-translations');
 const compileMO = require('./translations/compile-mo');
 const scanTranslations = require('./translations/scan-translations');
 const translationState = require('./translations/translation-state');
 const generateReport = require('./translations/generate-report');
 const readline = require('readline');
 const dotenv = require('dotenv');
 const path = require('path');
 const fs = require('fs');
 
 // Load environment variables
 dotenv.config();
 
 // Prompt helper function
 async function prompt(question) {
     return new Promise((resolve) => {
         const rl = readline.createInterface({
             input: process.stdin,
             output: process.stdout
         });
         rl.question(question, (answer) => {
             resolve(answer.toLowerCase().trim());
             rl.close();
         });
     });
 }
 
 // Configuration management
 function loadConfig() {
     const configPath = path.join(__dirname, 'config.json');
     const defaultConfig = {
         languages: {
             es_ES: { enabled: true, name: 'Spanish', code: 'es' },
             de_DE: { enabled: true, name: 'German', code: 'de' }
         }
     };
 
     try {
         const config = JSON.parse(fs.readFileSync(configPath, 'utf8'));
         return {
             ...defaultConfig,
             ...config,
             openai_api_key: process.env.OPENAI_API_KEY || config.openai_api_key
         };
     } catch (error) {
         console.warn('Warning: Could not load configuration. Using defaults.');
         return {
             ...defaultConfig,
             openai_api_key: process.env.OPENAI_API_KEY
         };
     }
 }
 
 // Check for failed translations
 function checkForFailedTranslations() {
     const dataDir = path.join(__dirname, '.data');
     if (!fs.existsSync(dataDir)) {
         return [];
     }
     
     const failedFiles = fs.readdirSync(dataDir)
         .filter(file => file.startsWith('failed-translations-') && file.endsWith('.json'));
     
     return failedFiles.map(file => {
         const locale = file.replace('failed-translations-', '').replace('.json', '');
         const filePath = path.join(dataDir, file);
         try {
             const content = JSON.parse(fs.readFileSync(filePath, 'utf8'));
             return {
                 locale,
                 count: Object.keys(content).length,
                 filePath
             };
         } catch (error) {
             console.error(`Error reading failed translations for ${locale}:`, error.message);
             return { locale, count: 0, filePath };
         }
     }).filter(item => item.count > 0);
 }
 
 // Find untranslated strings in PO files
async function findUntranslatedStrings(config) {
    const untranslatedByLocale = {};
    let totalUntranslated = 0;
    
    console.log('Scanning PO files for incomplete translations...');
    
    // Get a list of all PO files in the languages directory
    const languagesDir = path.join(__dirname, '../languages');
    let poFiles = [];
    
    if (fs.existsSync(languagesDir)) {
        poFiles = fs.readdirSync(languagesDir)
            .filter(file => file.endsWith('.po'))
            .map(file => {
                const locale = file.replace('radle-lite-', '').replace('.po', '');
                return { file, locale };
            });
    }
    
    // Check for configured languages that don't have PO files
    const configuredLocales = Object.keys(config.languages);
    const existingLocales = poFiles.map(p => p.locale);
    const missingLocales = configuredLocales.filter(locale => 
        !existingLocales.includes(locale) && config.languages[locale].enabled
    );
    
    if (missingLocales.length > 0) {
        console.log(`Found ${missingLocales.length} configured languages without PO files:`);
        for (const locale of missingLocales) {
            console.log(`  - ${config.languages[locale].name} (${locale})`);
            
            // Count these as untranslated
            const potFile = path.join(languagesDir, 'radle-lite.pot');
            if (fs.existsSync(potFile)) {
                try {
                    const potContent = fs.readFileSync(potFile, 'utf8');
                    // Count strings in POT file (excluding header)
                    const stringCount = (potContent.match(/msgid\s+"/g) || []).length - 1;
                    
                    if (stringCount > 0) {
                        untranslatedByLocale[locale] = {
                            name: config.languages[locale].name,
                            count: stringCount,
                            strings: [],
                            missingFile: true
                        };
                        totalUntranslated += stringCount;
                        console.log(`    ✓ Needs translation of ${stringCount} strings`);
                    }
                } catch (error) {
                    console.error(`    ❌ Error reading POT file:`, error.message);
                }
            } else {
                console.log(`    ❌ POT file not found, cannot determine string count`);
            }
        }
    }
    
    if (poFiles.length === 0 && missingLocales.length === 0) {
        console.log('No PO files found in languages directory');
        return { untranslatedByLocale, totalUntranslated };
    }
    
    console.log(`Scanning ${poFiles.length} existing PO files...`);
    
    for (const { file, locale } of poFiles) {
        // Skip if the locale is not in our config (but still log it)
        if (!config.languages[locale]) {
            console.log(`Found PO file for ${locale} but it's not configured in config.json`);
            continue;
        }
        
        const lang = config.languages[locale];
        if (!lang.enabled) {
            console.log(`Skipping disabled locale: ${locale}`);
            continue;
        }
        
        const poFile = path.join(languagesDir, file);
        
        try {
            console.log(`Scanning ${lang.name} (${locale}) PO file...`);
            const content = fs.readFileSync(poFile, 'utf8');
            const emptyTranslations = [];
            const incompleteEntries = [];
            
            // Check if PO file has fewer strings than POT file
            const potFile = path.join(languagesDir, 'radle-lite.pot');
            let potStringCount = 0;
            let poStringCount = 0;
            
            if (fs.existsSync(potFile)) {
                const potContent = fs.readFileSync(potFile, 'utf8');
                potStringCount = (potContent.match(/msgid\s+"/g) || []).length - 1; // Subtract 1 for header
                poStringCount = (content.match(/msgid\s+"/g) || []).length - 1;  // Subtract 1 for header
                
                if (potStringCount > poStringCount) {
                    console.log(`  ✓ PO file has fewer strings (${poStringCount}) than POT file (${potStringCount})`);
                    
                    // Count missing strings as untranslated
                    const missingCount = potStringCount - poStringCount;
                    if (!untranslatedByLocale[locale]) {
                        untranslatedByLocale[locale] = {
                            name: lang.name,
                            count: missingCount,
                            strings: [],
                            incompletePO: true
                        };
                    } else {
                        untranslatedByLocale[locale].count += missingCount;
                        untranslatedByLocale[locale].incompletePO = true;
                    }
                    
                    totalUntranslated += missingCount;
                }
            }
            
            // Split content into entries (separated by empty lines)
            const entries = content.split('\n\n');
            
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
                    emptyTranslations.push(msgid);
                    incompleteEntries.push(entry);
                }
            }
            
            if (emptyTranslations.length > 0) {
                console.log(`  ✓ Found ${emptyTranslations.length} untranslated strings in ${lang.name}`);
                
                if (!untranslatedByLocale[locale]) {
                    untranslatedByLocale[locale] = {
                        name: lang.name,
                        count: emptyTranslations.length,
                        strings: emptyTranslations,
                        entries: incompleteEntries
                    };
                } else {
                    untranslatedByLocale[locale].count += emptyTranslations.length;
                    untranslatedByLocale[locale].strings = emptyTranslations;
                    untranslatedByLocale[locale].entries = incompleteEntries;
                }
                
                totalUntranslated += emptyTranslations.length;
            } else if (!untranslatedByLocale[locale]) {
                console.log(`  ✓ All strings are translated in ${lang.name}`);
            }
        } catch (error) {
            console.error(`  ❌ Error scanning ${locale} for untranslated strings:`, error.message);
        }
    }
    
    return { untranslatedByLocale, totalUntranslated };
}
 
 // Main translation update function
 async function updateTranslations(options = {}) {
     const rl = readline.createInterface({
         input: process.stdin,
         output: process.stdout
     });

     try {
         console.log('🌍 Starting translation update process...\n');
         
         // Initialize translation state
         await translationState.initialize();
         
         // Load configuration
         const config = loadConfig();
         
         // Step 1: Generate POT file and analyze changes
         console.log('Step 1: Generating POT file and analyzing changes');
         console.log('---------------------------------------------');
         
         const scanResults = await scanTranslations();
         const changes = await translationState.compareStrings(scanResults.strings);
         
         // Find untranslated strings in existing PO files
         console.log('Scanning existing PO files for untranslated strings...');
         const { untranslatedByLocale, totalUntranslated } = await findUntranslatedStrings(config);
         
         // Check if we're in resume mode
         if (options.resume) {
             console.log('Resume mode detected - checking for incomplete translations...');
             const failedTranslations = checkForFailedTranslations();
             
             // If we have untranslated strings or missing PO files, offer to translate them
             if (totalUntranslated > 0) {
                 console.log(`\nFound ${totalUntranslated} untranslated strings across ${Object.keys(untranslatedByLocale).length} locales:`);
                 
                 for (const [locale, data] of Object.entries(untranslatedByLocale)) {
                     if (data.missingFile) {
                         console.log(`- ${data.name} (${locale}): Missing PO file - needs ${data.count} strings translated`);
                     } else if (data.incompletePO) {
                         console.log(`- ${data.name} (${locale}): Incomplete PO file - missing ${data.count} strings`);
                     } else {
                         console.log(`- ${data.name} (${locale}): ${data.count} untranslated strings`);
                     }
                 }
                 
                 const confirmTranslate = await new Promise((resolve) => {
                     rl.question('\nWould you like to translate these untranslated strings? (y/n): ',
                         (answer) => {
                             resolve(answer.toLowerCase().trim() === 'y');
                             rl.close();
                         }
                     );
                 });
                 
                 if (confirmTranslate) {
                     console.log('\nTranslating untranslated strings...');
                     
                     for (const [locale, data] of Object.entries(untranslatedByLocale)) {
                         const lang = config.languages[locale];
                         if (!lang || !lang.enabled) {
                             console.log(`Skipping ${locale} as it's not enabled in config.`);
                             continue;
                         }
                         
                         try {
                             console.log(`\nProcessing ${lang.name} (${locale})...`);
                             
                             // If missing PO file, translate all strings from POT
                             if (data.missingFile) {
                                 console.log(`Creating new PO file for ${lang.name} (${locale})...`);
                                 
                                 // Get all strings from POT file
                                 const potFile = path.join(__dirname, '../languages/radle-lite.pot');
                                 if (fs.existsSync(potFile)) {
                                     const potContent = fs.readFileSync(potFile, 'utf8');
                                     const allStrings = {};
                                     
                                     // Extract all msgid entries from POT
                                     const potMatches = potContent.matchAll(/msgid\s+"(.+?)"\s+(?=msgstr)/gs);
                                     for (const match of Array.from(potMatches)) {
                                         if (match[1] && match[1].trim() !== '' && match[1] !== '""') {
                                             allStrings[match[1]] = match[1];
                                         }
                                     }
                                     
                                     console.log(`Found ${Object.keys(allStrings).length} strings to translate from POT file`);
                                     
                                     const result = await generateTranslations({
                                         locale,
                                         strings: allStrings,
                                         config,
                                         apiKey: config.openai_api_key,
                                         createNewPO: true
                                     });
                                     
                                     if (result && result.rateLimitHit) {
                                         console.log(`⚠️ Rate limit hit for ${locale}. Some strings may remain untranslated.`);
                                     }
                                 } else {
                                     console.error(`❌ POT file not found. Cannot create PO file for ${locale}.`);
                                 }
                                 continue;
                             }
                             
                             // If incomplete PO file, use resume mode to handle it
                             if (data.incompletePO) {
                                 console.log(`Completing PO file for ${lang.name} (${locale})...`);
                                 
                                 const result = await generateTranslations({
                                     locale,
                                     strings: {}, // Empty as we'll detect missing strings in the function
                                     config,
                                     apiKey: config.openai_api_key,
                                     resumeMode: true,
                                     completePO: true
                                 });
                                 
                                 if (result && result.rateLimitHit) {
                                     console.log(`⚠️ Rate limit hit for ${locale}. Some strings may remain untranslated.`);
                                 }
                                 continue;
                             }
                             
                             // Create a dictionary of untranslated strings
                             const untranslatedDict = {};
                             data.strings.forEach(str => {
                                 untranslatedDict[str] = str;
                             });
                             
                             const result = await generateTranslations({
                                 locale,
                                 strings: untranslatedDict,
                                 config,
                                 apiKey: config.openai_api_key
                             });
                             
                             if (result && result.rateLimitHit) {
                                 console.log(`⚠️ Rate limit hit for ${locale}. Some strings may remain untranslated.`);
                             }
                         } catch (error) {
                             console.error(`❌ Error translating untranslated strings for ${locale}:`, error.message);
                         }
                     }
                     
                     // Ask about MO compilation
                     await compileMoFilesPrompt(config, options);
                     
                     console.log('\n✅ Translation of untranslated strings completed!');
                     return;
                 }
             }
             
             if (failedTranslations.length === 0) {
                 console.log('No failed translations found to resume. Proceeding with normal translation process.');
             } else {
                 console.log(`Found ${failedTranslations.length} locales with failed translations:`);
                 failedTranslations.forEach(item => {
                     console.log(`- ${config.languages[item.locale]?.name || item.locale}: ${item.count} strings`);
                 });
                 
                 const confirmResume = await new Promise((resolve) => {
                     rl.question('\nWould you like to resume these failed translations? (y/n): ',
                         (answer) => {
                             resolve(answer.toLowerCase().trim() === 'y');
                             rl.close();
                         }
                     );
                 });
                 
                 if (confirmResume) {
                     console.log('\nResuming failed translations...');
                     
                     for (const item of failedTranslations) {
                         const locale = item.locale;
                         const lang = config.languages[locale];
                         if (!lang || !lang.enabled) {
                             console.log(`Skipping ${locale} as it's not enabled in config.`);
                             continue;
                         }
                         
                         try {
                             console.log(`\nResuming translations for ${lang.name} (${locale})...`);
                             const result = await generateTranslations({
                                 locale,
                                 strings: {}, // Empty as we'll load from failed log
                                 config,
                                 apiKey: config.openai_api_key,
                                 resumeMode: true
                             });
                             
                             if (result && result.rateLimitHit) {
                                 console.log(`⚠️ Rate limit hit again for ${locale}. Try again later.`);
                             }
                         } catch (error) {
                             console.error(`❌ Error resuming translations for ${locale}:`, error.message);
                         }
                     }
                     
                     // Ask about MO compilation
                     await compileMoFilesPrompt(config, options);
                     
                     console.log('\n✅ Translation resume completed!');
                     return;
                 }
             }
         }
         
         // Display changes
         console.log('\nString Analysis:');
         console.log(`Total strings: ${Object.keys(scanResults.strings).length}`);
         console.log(`New strings: ${changes.new.length}`);
         console.log(`Modified strings: ${changes.modified.length}`);
         console.log(`Removed strings: ${changes.removed.length}`);
         
         // Display untranslated strings information
         if (totalUntranslated > 0) {
             console.log(`Untranslated strings: ${totalUntranslated} across ${Object.keys(untranslatedByLocale).length} locales`);
             for (const [locale, data] of Object.entries(untranslatedByLocale)) {
                 console.log(`  - ${data.name} (${locale}): ${data.count} untranslated`);
             }
         } else {
             console.log(`Untranslated strings: 0`);
         }
         
         let shouldTranslate = false;
         let translateAll = false;
         let translateUntranslated = false;
         const generatedMoFiles = [];
         let translatedStrings = [];
 
         // Determine if we should translate based on changes and user input
         if (options.nonInteractive) {
             // In non-interactive mode, only translate if there are changes
             shouldTranslate = changes.new.length > 0 || changes.modified.length > 0;
             translateAll = false;
             console.log('\nNon-interactive mode - will translate only new and modified strings.');
         } else if (options.force) {
             // Force flag always translates all strings
             shouldTranslate = true;
             translateAll = true;
             console.log('\nForce flag detected - will translate all strings.');
         } else if (changes.new.length > 0 || changes.modified.length > 0 || totalUntranslated > 0) {
             // There are changes or untranslated strings - ask about translation mode
             console.log('\nTranslation Mode:');
             const mode = await new Promise((resolve) => {
                 rl.question(
                     'Would you like to translate:\n' +
                     '1. All strings (full translation)\n' +
                     '2. Only new and modified strings (incremental)\n' +
                     '3. Only untranslated strings\n' +
                     '4. Resume failed translations from previous runs\n' +
                     'Enter choice (1/2/3/4): ',
                     (answer) => {
                         resolve(answer.toLowerCase().trim());
                         rl.close();
                     }
                 );
             });
             
             shouldTranslate = true;
             translateAll = mode === '1';
             translateUntranslated = mode === '3';
             
             if (mode === '4') {
                 // Switch to resume mode
                 options.resume = true;
                 return updateTranslations(options);
             }
         } else {
             // No changes - ask if user wants to translate anyway
             const answer = await new Promise((resolve) => {
                 rl.question('\nNo new or modified strings found. Would you like to:\n' +
                     '1. Translate all strings anyway\n' +
                     '2. Resume failed translations from previous runs\n' +
                     '3. Skip translation\n' +
                     'Enter choice (1/2/3): ',
                     (answer) => {
                         resolve(answer.toLowerCase().trim());
                         rl.close();
                     }
                 );
             });
             
             if (answer === '1') {
                 shouldTranslate = true;
                 translateAll = true;
             } else if (answer === '2') {
                 // Switch to resume mode
                 options.resume = true;
                 return updateTranslations(options);
             } else {
                 shouldTranslate = false;
             }
         }
         
         // Step 2: Update translations if needed
         let translatedStringsCount = 0;
         let anyRateLimitHit = false;
         
         if (shouldTranslate) {
             console.log('\nStep 2: Updating translations');
             console.log('---------------------------');
             
             let stringsToTranslate = {};
             
             if (translateAll) {
                 stringsToTranslate = scanResults.strings;
             } else if (translateUntranslated) {
                 // Only translate untranslated strings
                 for (const [locale, data] of Object.entries(untranslatedByLocale)) {
                     const lang = config.languages[locale];
                     if (!lang || !lang.enabled) continue;
                     
                     try {
                         console.log(`\nProcessing untranslated strings for ${lang.name} (${locale})...`);
                         
                         // Create a dictionary of untranslated strings
                         const untranslatedDict = {};
                         data.strings.forEach(str => {
                             untranslatedDict[str] = str;
                         });
                         
                         const result = await generateTranslations({
                             locale,
                             strings: untranslatedDict,
                             config,
                             apiKey: config.openai_api_key
                         });
                         
                         if (result && result.rateLimitHit) {
                             anyRateLimitHit = true;
                         }
                     } catch (error) {
                         console.error(`❌ Error translating untranslated strings for ${locale}:`, error.message);
                         if (error.message.includes('429')) {
                             anyRateLimitHit = true;
                         }
                     }
                 }
                 
                 // Skip the regular translation loop
                 shouldTranslate = false;
             } else {
                 // Only translate new and modified strings
                 stringsToTranslate = Object.fromEntries(
                     [...changes.new, ...changes.modified]
                         .map(key => [key, scanResults.strings[key]])
                 );
             }
             
             translatedStringsCount = Object.keys(stringsToTranslate).length;
             translatedStrings = Object.entries(stringsToTranslate).map(([key, value]) => `${key}: ${value}`);
             
             if (shouldTranslate && Object.keys(stringsToTranslate).length > 0) {
                 for (const locale of Object.keys(config.languages)) {
                     const lang = config.languages[locale];
                     if (!lang.enabled) continue;
                     
                     try {
                         console.log(`\nProcessing ${lang.name} (${locale})...`);
                         const result = await generateTranslations({
                             locale,
                             strings: stringsToTranslate,
                             config,
                             apiKey: config.openai_api_key
                         });
                         
                         if (result && result.rateLimitHit) {
                             anyRateLimitHit = true;
                         }
                     } catch (error) {
                         console.error(`❌ Error updating translations for ${locale}:`, error.message);
                         if (error.message.includes('429')) {
                             anyRateLimitHit = true;
                         }
                     }
                 }
             }
         } else {
             console.log('\nSkipping translations as no changes were detected.');
         }
         
         // If we hit rate limits, suggest resuming later
         if (anyRateLimitHit) {
             console.log('\n⚠️ Some translations failed due to rate limits.');
             console.log('You can resume these translations later by running:');
             console.log('node scripts/translate.js --resume');
         }
         
         // Step 3: Ask about MO compilation
         await compileMoFilesPrompt(config, options);
         
         // Step 4: Generate Report
         if (!options.noReport) {
             console.log('\nStep 4: Generating translation report');
             console.log('-----------------------------------');
             
             try {
                 const reportPath = await generateReport({
                     changes,
                     translatedStrings,
                     translatedStringsCount,
                     generatedMoFiles,
                     untranslatedByLocale,
                     totalUntranslated
                 });
                 
                 console.log(`✓ Generated report: ${reportPath}`);
             } catch (error) {
                 console.error('❌ Error generating report:', error.message);
             }
         }
         
         console.log('\n✅ Translation update completed successfully!');
     } catch (error) {
         console.error('❌ Error updating translations:', error);
     } finally {
         rl.close();
     }
 }
 
 // Helper function for MO compilation prompt
 async function compileMoFilesPrompt(config, options) {
     if (!options.noMo && !options.nonInteractive) {
         const rl = readline.createInterface({
             input: process.stdin,
             output: process.stdout
         });
         
         const compileMoFiles = await new Promise((resolve) => {
             rl.question('\nWould you like to compile MO files now? (y/n): ',
                 (answer) => {
                     resolve(answer.toLowerCase().trim());
                     rl.close();
                 }
             );
         });
         
         if (compileMoFiles === 'y') {
             console.log('\nStep 3: Compiling MO files');
             console.log('------------------------');
             
             for (const locale of Object.keys(config.languages)) {
                 const lang = config.languages[locale];
                 if (!lang.enabled) continue;
                 
                 try {
                     await compileMO(locale);
                     console.log(`✓ Compiled MO file for ${lang.name}`);
                 } catch (error) {
                     console.error(`❌ Error compiling MO file for ${locale}:`, error.message);
                 }
             }
         }
     }
 }
 
 // Command line interface
 if (require.main === module) {
     const args = process.argv.slice(2);
     const options = {
         force: args.includes('--force') || args.includes('-f'),
         noMo: args.includes('--no-mo'),
         noReport: args.includes('--no-report'),
         nonInteractive: args.includes('--non-interactive') || args.includes('-n'),
         resume: args.includes('--resume') || args.includes('-r')
     };
     
     updateTranslations(options).catch(console.error);
 }
 
 module.exports = updateTranslations;
 