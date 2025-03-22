import { Controller } from "@hotwired/stimulus";
import translationsEn from "./translations.en.js";
import translationsFr from "./translations.fr.js";

export default class extends Controller {
    static targets = [ "quantity", "total", "message", "currency", "shipping", "submitButton", "productTotal", "productQuantity" ];

    // Fetches data from the Symfony controller when the controller is connected
    connect() {
        // Initialize translations
        this.language = "fr"; // Default language
        this.translations = {
            en: translationsEn,
            fr: translationsFr
        };

        // Check if totalTarget exists
        if (this.hasTotalTarget && this.hasQuantityTarget) {
            fetch("/basket/json", {
                method: "GET",
            })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(this.translate("basket.load.error"));
                }
                return response.json();
            })
            .then((data) => {
                if (data) {
                    this.update(data);
                }
            })
            .catch((error) => {
                this.displayMessage(this.translate("basket.load.error"), "alert-danger");
            });
        }
    }

    // Adds a product to the basket
    add(event) {
        // Adds an animation to the clicked button
        this.animation(event.currentTarget);

        // Fetches data from the Symfony controller
        this.fetchData(event.currentTarget);
    }

    // Adds an animation to the clicked button
    animation(clickedButton) {
        if (!clickedButton.classList.contains("btn-primary")) {
            return;
        }
        clickedButton.classList.remove("btn-primary");
        clickedButton.classList.add("btn-secondary", "zoom-animation");
        setTimeout(() => {
            clickedButton.classList.remove("zoom-animation", "btn-secondary");
            clickedButton.classList.add("btn-primary");
        }, 500);
    }

    // Deletes the basket
    delete() {
        fetch("/basket", { method: "DELETE" })
        .then((response) => {
            if (!response.ok) {
                throw new Error(this.translate("basket.delete.error"));
            }
            window.location.reload();
        })
        .catch((error) => {
            this.displayMessage(this.translate("basket.delete.error"), "alert-danger");
        });
    }

    // Deletes a product
    deleteProduct(event) {
        // Store event data before the asynchronous call
        const target = event.currentTarget;

        fetch("/basket/delete", {
            method: "DELETE",
            body: JSON.stringify({
                id: event.currentTarget.dataset.productid,
                quantity: 0,
            }),
            headers: {
                "Content-Type": "application/json",
            }
        })
        .then((response) => {
            if (!response.ok) {
                throw new Error(this.translate("product.delete.error"));
            }
            return response.json();
        })
        .then((data) => {
            const message = `${target.dataset.title} ${target.dataset.text}`;
            this.displayMessage(message, "alert-" + target.dataset.alert);
            this.update(data);
        })
        .catch((error) => {
            this.displayMessage(this.translate("product.delete.error"), "alert-danger");
        });
    }

    // Fetches data from the Symfony controller
    fetchData(target) {
        fetch("/basket", {
            method: "POST",
            body: JSON.stringify({
                id: target.dataset.productid,
                quantity: target.dataset.quantity,
            }),
            headers: {
                "Content-Type": "application/json",
            }
        })
        .then((response) => {
            if (!response.ok) {
                throw new Error(this.translate("basket.add.error"));
            }
            return response.json();
        })
        .then((data) => {
            if (data.error) {
                this.displayMessage(data.error, "alert-danger");
            } else {
                const message = `${target.dataset.title} ${target.dataset.text}`;
                this.displayMessage(message, "alert-" + target.dataset.alert);
                this.update(data);
            }
        })
        .catch((error) => {
            this.displayMessage(this.translate("basket.add.error"), "alert-danger");
        });
    }

    // Displays a message
    displayMessage(message, alertClass) {
        this.messageTarget.className = `alert ${alertClass}`;
        this.messageTarget.textContent = message;
    }

    // Updates total and quantity
    update(data) {
        if (!data) {
            return;
        }
        this.updateBasketButton(data);
        this.updateBasketPage(data);
    }

    // Updates the basket button
    updateBasketButton(data) {
        // Vérification rapide des conditions d'arrêt
        const basketButton = document.getElementById("basket-button");
        if (!basketButton) {
            return;
        }

        // Updates the visibility of the basket button
        this.updateBasketButtonDisplay(basketButton, data);

        // Updates the counters if the targets exist
        this.updateBasketCounters(data);
    }

    // Updates the visibility of the basket button
    updateBasketButtonDisplay(basketButton, data) {
        const isEmpty = !data.basket || data.basket.total === 0;
        basketButton.style.display = isEmpty ? "none" : "block";
    }

    // Updates the counters
    updateBasketCounters(data) {
        if (!data.basket) return;

        if (this.hasTotalTarget) {
            this.totalTarget.textContent = (data.basket.total / 100).toFixed(2);
        }

        if (this.hasQuantityTarget) {
            this.quantityTarget.textContent = data.basket.quantity;
        }
    }

    // Updates the basket page
    updateBasketPage(data) {
        const basketPage = document.getElementById("basket-page");
        if (!basketPage || !data.basket) {
            return;
        }

        this.removeDeletedProducts(data);
        this.updateExistingProducts(data);
        this.updateBasketTotals(data);
        this.updateSubmitButton(data);

        // Reloads the page if the basket is empty
        if (data.basket.total === 0) {
            window.location.reload();
        }
    }

    // Removes products that are no longer in the basket
    removeDeletedProducts(data) {
        if (!data.basket || !data.basket.products) {
            return;
        }

        const currentProductIds = Object.keys(data.basket.products);
        const productRows = document.querySelectorAll('tr[id^="product-"]');

        productRows.forEach((row) => {
            const productId = row.id.replace("product-", "");
            if (!currentProductIds.includes(productId)) {
                row.classList.add("fade-out");
                setTimeout(() => row.remove(), 100);
            }
        });
    }

    // Updates existing products
    updateExistingProducts(data) {
        if (!data.basket || !data.basket.products) {
            return;
        }

        Object.entries(data.basket.products).forEach(([productId, productData]) => {
            this.updateProductRow(productId, productData);
        });
    }

    // Updates the basket totals
    updateBasketTotals(data) {
        if (!data.basket) {
            return;
        }

        if (this.hasTotalTarget) {
            this.totalTarget.textContent = ((data.basket.total + data.basket.shipping) / 100).toFixed(2);
        }

        if (this.hasQuantityTarget) {
            this.quantityTarget.textContent = data.basket.quantity;
        }

        this.updateShippingDisplay(data);
    }

    // Updates the shipping display
    updateShippingDisplay(data) {
        if (!this.hasShippingTarget || !data.basket) {
            return;
        }

        this.shippingTarget.textContent = data.basket.shipping > 0
            ? (data.basket.shipping / 100).toFixed(2) + data.basket.currency
            : this.translate("basket.offered");
    }

    // Updates the submit button
    updateSubmitButton(data) {
        if (!this.hasSubmitButtonTarget || !data.basket) {
            return;
        }

        const label = this.translate("label.pay");
        const total = ((data.basket.total + data.basket.shipping) / 100).toFixed(2);
        const currency = data.basket.currency;

        this.submitButtonTarget.value = `${label} ${total} ${currency}`;
    }

    // Updates a single product row
    updateProductRow(productId, productData) {
        const productTotalElement = this.productTotalTargets.find(
            (target) => target.dataset.productId === productId
        );
        if (productTotalElement) {
            productTotalElement.textContent = (productData.total / 100).toFixed(2);
        }

        const productQuantityElement = this.productQuantityTargets.find(
            target => target.dataset.productId === productId
        );
        if (productQuantityElement) {
            productQuantityElement.textContent = productData.quantity;
        }
    }

    // Translates messages
    translate(key) {
        // Vérification simple pour language
        if (!this.language) {
            return key;
        }

        // Accès aux traductions
        const translations = this.translations?.[this.language];
        if (!translations) {
            return key;
        }

        // Retourne la traduction ou la clé par défaut
        return translations[key] || key;
    }
}
