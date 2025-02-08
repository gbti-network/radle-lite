/**
 * Debug utility for Radle plugin
 * Provides file-level debug logging control while ensuring critical errors are always logged
 */
class RadleDebugger {
    constructor(fileName, enabled = false) {
        this.fileName = fileName;
        this.enabled = enabled;
    }

    /**
     * Enable debug logging for this file
     */
    enable() {
        this.enabled = true;
    }

    /**
     * Disable debug logging for this file
     */
    disable() {
        this.enabled = false;
    }

    /**
     * Log debug message if debugging is enabled for this file
     * @param {...any} args - Arguments to log
     */
    log(...args) {
        if (this.enabled) {
            console.log(`[${this.fileName}]`, ...args);
        }
    }

    /**
     * Log warning if debugging is enabled for this file
     * @param {...any} args - Arguments to log
     */
    warn(...args) {
        if (this.enabled) {
            console.warn(`[${this.fileName}]`, ...args);
        }
    }

    /**
     * Always log errors, regardless of debug status
     * @param {...any} args - Arguments to log
     */
    error(...args) {
        console.error(`[${this.fileName}]`, ...args);
    }
}

// Export the RadleDebugger class
if (typeof module !== 'undefined' && module.exports) {
    module.exports = RadleDebugger;
}
