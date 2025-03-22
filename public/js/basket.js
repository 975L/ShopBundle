import { Controller } from "@hotwired/stimulus";
import translationsEn from "./translations.en.js";
import translationsFr from "./translations.fr.js";

export default class extends Controller {
    static targets = [ "quantity", "total", "message", "currency", "shipping", "submitButton", "productTotal", "productQuantity" ];

    // Gets data from the Symfony controller
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
        const basketButton = document.getElementById("basket-button");
        if (!basketButton) {
            return;
        }
        basketButton.style.display = "block";

        // Hides the basket button if total = 0
        if (!data.basket || data.basket.total === 0) {
            basketButton.style.display = "none";

            return;
        }

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
        if (!basketPage) {
            return;
        }

        const currentProductIds = data.basket && data.basket.products ? Object.keys(data.basket.products) : [];
        const productRows = document.querySelectorAll('tr[id^="product-"]');

        // Removes products that are no longer in the basket
        productRows.forEach(row => {
            const productId = row.id.replace('product-', '');
            if (!currentProductIds.includes(productId)) {
                row.classList.add('fade-out');
                setTimeout(() => row.remove(), 100);
            }
        });

        // Checks if basket data is valid before continuing
        if (!data.basket || !data.basket.products) {
            return;
        }

        // Updates products still in the basket
        Object.entries(data.basket.products).forEach(([productId, productData]) => {
            this.updateProductRow(productId, productData);
        });

        // Reloads the page if total = 0
        if (data.basket.total === 0) {
            window.location.reload();
        }

        // Updates totals
        if (this.hasTotalTarget) {
            this.totalTarget.textContent = ((data.basket.total + data.basket.shipping) / 100).toFixed(2);
        }

        if (this.hasQuantityTarget) {
            this.quantityTarget.textContent = data.basket.quantity;
        }

        if (this.hasShippingTarget) {
            this.shippingTarget.textContent = data.basket.shipping > 0
                ? (data.basket.shipping / 100).toFixed(2) + data.basket.currency
                : this.translate("basket.offered");
        }

        // Updates the submit button text
        if (this.hasSubmitButtonTarget) {
            const label = this.translate("label.pay");
            const total = ((data.basket.total + data.basket.shipping) / 100).toFixed(2);
            const currency = data.basket.currency;

            this.submitButtonTarget.value = `${label} ${total} ${currency}`;
        }
    }

    // Updates a single product row
    updateProductRow(productId, productData) {
        const productTotalElement = this.productTotalTargets.find(
            target => target.dataset.productId === productId
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
        if (!this.translations || !this.language || !this.translations[this.language]) {
            return key;
        }
        return this.translations[this.language][key] || key;
    }
}
