# Radle Translation System Documentation

## Overview
The Radle Translation System is a comprehensive solution for managing WordPress plugin translations using OpenAI's GPT models. It provides automated translation generation, statistics tracking, and translation state management.

## Translation Workflow

### Step 1: POT Generation and String Analysis
1. The system generates a new `radle.pot` template file by scanning all plugin files for translatable strings
2. During this process, it:
   - Records all translatable strings
   - Identifies new strings that weren't in the previous scan
   - Identifies modified strings that have changed since the last scan
   - Maintains file references and line numbers for each string

### Step 2: Translation State Management
1. After scanning, the system presents:
   - Total number of strings found
   - Number of new strings added
   - Number of strings modified
   - Current translation status for each language

2. The system then follows this interactive workflow:

   a. If `--force` flag is used:
      - Automatically translates all strings without prompting
      - Displays "Force flag detected - will translate all strings"

   b. If new or modified strings are found:
      - Prompts user to choose:
        ```
        Would you like to translate:
        1. All strings (full translation)
        2. Only new and modified strings (incremental)
        Enter choice (1/2):
        ```

   c. If no changes are detected:
      - Prompts user:
        ```
        No new or modified strings found. Would you like to translate all strings anyway? (y/n):
        ```
      - If yes: Proceeds with full translation
      - If no: Skips translation but continues to MO compilation prompt

### Step 3: Translation Generation
For each supported language:
1. Based on workflow outcome:
   - Full translation: Processes all strings in the POT file
   - Incremental translation: Processes only new/modified strings
   - No translation: Skips this step entirely
2. Generates translations using GPT for selected strings
3. Preserves existing translations for unchanged strings
4. Updates PO files with new translations

### Step 4: MO File Compilation
1. Unless `--no-mo` flag is used, the system:
   - Prompts user: "Would you like to compile MO files now? (y/n):"
   - If yes: Compiles all updated PO files into MO format
   - If no: Saves PO files only (compilation can be done later)

## System Components

### 1. Configuration (`scripts/config.json`)
```json
{
  "openai_api_key": "your-api-key-here",
  "translations": {
    "supported_languages": [
      {
        "name": "Spanish",
        "code": "es",
        "locale": "es_ES"
      },
      {
        "name": "German",
        "code": "de",
        "locale": "de_DE"
      }
    ]
  }
}
```

### 2. Core Scripts
- `translate.js`: Main orchestration script
- `translations/generate-pot.js`: POT template generation
- `translations/scan-translations.js`: Translation string scanning
- `translations/generate-translations.js`: PO file generation
- `translations/compile-mo.js`: MO file compilation
- `translations/translation-state.js`: Translation state management
- `translations/cleanup.js`: Cleanup and maintenance tasks

## File Structure
```
radle/
├── languages/
│   ├── radle.pot
│   ├── radle-es_ES.po
│   ├── radle-es_ES.mo
│   └── ... (other language files)
├── scripts/
│   ├── translate.js
│   ├── config.json
│   └── translations/
│       ├── generate-pot.js
│       ├── scan-translations.js
│       ├── generate-translations.js
│       ├── compile-mo.js
│       ├── translation-state.js
│       └── cleanup.js
```

## Usage

### Basic Translation Process
```bash
node scripts/translate.js
```

### Command Line Options
- `--pot-only`: Only generate POT file
- `--force`: Force re-translation of all strings
- `--lang [locale]`: Process specific language only
- `--no-mo`: Skip MO file compilation

## Interactive Prompts

### Translation Mode Selection
1. With changes detected:
   ```
   Would you like to translate:
   1. All strings (full translation)
   2. Only new and modified strings (incremental)
   Enter choice (1/2):
   ```

2. No changes detected:
   ```
   No new or modified strings found. Would you like to translate all strings anyway? (y/n):
   ```

3. MO Compilation:
   ```
   Would you like to compile MO files now? (y/n):
   ```

## Error Handling

### Translation Errors
1. API rate limiting
   - Automatic retry with backoff
   - State preservation
2. Network failures
   - Connection retry
   - Progress resumption
3. File system errors
   - Automatic backup
   - Recovery procedures

## Best Practices

### Translation Management
1. Regular POT updates
2. Incremental translations for efficiency
3. Quality verification
4. Backup maintenance

### Performance
1. Batch processing
2. Rate limit management
3. Resource cleanup
4. Progress tracking
