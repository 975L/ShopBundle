import translationsEn from "./translations.en.js";
import translationsFr from "./translations.fr.js";

export default {
    // Initialize translations
    translations: {
        "en": translationsEn,
        "fr": translationsFr
    },

    // Gets the language from the HTML document or browser
    getLanguage() {
        // Gets the language from the HTML document
        const langAttribute = document.documentElement.getAttribute("lang") || document.body.getAttribute("data-language");
        if (langAttribute) {
            return langAttribute.substring(0, 2).toLowerCase();
        }

        // Or uses the browser language
        const browserLang = navigator.language || navigator.userLanguage;
        return browserLang ? browserLang.substring(0, 2).toLowerCase() : "en";
    },

    // Displays a message
    displayMessage(message, alertClass) {
        let messageElement = document.querySelector(".global-message");
        messageElement.className = `alert ${alertClass}`;
        messageElement.textContent = message;

        // Launches a global event for listeners
        const event = new CustomEvent("basket:message", {
            bubbles: true,
            detail: { message, alertClass }
        });
        document.dispatchEvent(event);
    },

    // Translates messages
    translate(key) {
        if (typeof key !== "string") {return "";}

        const language = this.getLanguage();
        const translations = this.translations[language] || this.translations["en"];

        if (!translations) return key;

        return translations[key] || key;
    },

    // Updates the currency symbol based on the currency code
    getCurrencySymbol(currencyCode) {
        if (!currencyCode) { return ""; }

        const symbols = {
            "eur": "€",
            "usd": "$",
            "gbp": "£",
            "jpy": "¥",
            "chf": "CHF",
        };

        const code = currencyCode.toLowerCase();

        return " " + symbols[code] || currencyCode.toUpperCase();
    },

    // Formats the date
    formatDate(date, locale) {
        return date.toLocaleString(locale, {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit"
        });
    }
};