import { Controller } from "@hotwired/stimulus";
import translationsEn from "./translations.en.js";
import translationsFr from "./translations.fr.js";

export default class extends Controller {
    static targets = [ "quantity", "total", "message", "currency", "shipping", "submitButton", "productItemTotal", "productItemQuantity" ];

    // Fetches data from the Symfony controller when the controller is connected
    connect() {
        // Event listeners as some controller are outside the basket controller
        document.addEventListener("basket:message", this.handleGlobalMessage.bind(this));
        document.addEventListener("basket:update", this.handleGlobalUpdate.bind(this));

        // Initialize translations
        this.language = "fr"; // Default language
        this.translations = {
            en: translationsEn,
            fr: translationsFr
        };

        // Updates data
        this.updateData();
    }

    // Updates data
    updateData() {
        if (this.hasTotalTarget && this.hasQuantityTarget) {
            fetch("/shop/basket/json", {
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

    // Deletes the basket
    delete() {
        fetch("/shop/basket", { method: "DELETE" })
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

    // Adds a quantity of productItem to the basket
    addProductItem(event) {
        // Adds an animation to the clicked button
        this.animation(event.currentTarget);

        // Fetches data from the Symfony controller
        this.fetchData(event.currentTarget);
    }

    // Removes a quantity of productItem to the basket
    removeProductItem(event) {
        // Adds an animation to the clicked button
        this.animation(event.currentTarget);

        // Fetches data from the Symfony controller
        this.fetchData(event.currentTarget);
    }

    // Deletes completely a productItem
    deleteProductItem(event) {
        // Store event data before the asynchronous call
        const target = event.currentTarget;

        fetch("/shop/basket/delete", {
            method: "DELETE",
            body: JSON.stringify({
                id: event.currentTarget.dataset.productItemId,
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
        fetch("/shop/basket", {
            method: "POST",
            body: JSON.stringify({
                id: target.dataset.productItemId,
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

    // Updates total and quantity
    update(data) {
        if (!data) {
            return;
        }
        this.updateBasketButton(data);
        this.updateBasketPage(data);

        // Dispatches a global event
        const event = new CustomEvent("basket:update", {
            bubbles: true,
            detail: { data }
        });
        document.dispatchEvent(event);
    }

    // Updates the basket button
    updateBasketButton(data) {
        // Updates the basket button if it exists
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
        if (!data.basket) {return;}

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

        this.removeDeletedProductItems(data);
        this.updateExistingProductItems(data);
        this.updateBasketTotals(data);
        this.updateSubmitButton(data);

        // Reloads the page if the basket is empty
        if (data.basket.total === 0) {
            window.location.reload();
        }
    }

    // Removes productItems that are no longer in the basket
    removeDeletedProductItems(data) {
        if (!data.basket || !data.basket.productItems) {
            return;
        }

        const currentProductItemIds = Object.keys(data.basket.productItems);
        const productItemRows = document.querySelectorAll("tr[id^=\"productItem-\"]");

        productItemRows.forEach((row) => {
            const productItemId = row.id.replace("productItem-", "");
            if (!currentProductItemIds.includes(productItemId)) {
                row.classList.add("fade-out");
                setTimeout(() => row.remove(), 100);
            }
        });
    }

    // Updates existing productItems
    updateExistingProductItems(data) {
        if (!data.basket || !data.basket.productItems) {
            return;
        }

        Object.entries(data.basket.productItems).forEach(([productItemId, productItemData]) => {
            this.updateProductItemRow(productItemId, productItemData);
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

    // Updates a single productItem row
    updateProductItemRow(productItemId, productItemData) {
        const productItemTotalElement = this.productItemTotalTargets.find(
            (target) => target.dataset.productItemId === productItemId
        );
        if (productItemTotalElement) {
            productItemTotalElement.textContent = (productItemData.total / 100).toFixed(2) + productItemData.productItem.currency;
        }

        const productItemQuantityElement = this.productItemQuantityTargets.find(
            (target) => target.dataset.productItemId === productItemId
        );
        if (productItemQuantityElement) {
            productItemQuantityElement.textContent = productItemData.quantity;
        }
    }

    // Displays a message locally or globally
    displayMessage(message, alertClass) {
        if (this.hasMessageTarget) {
            this.messageTarget.className = `alert ${alertClass}`;
            this.messageTarget.textContent = message;
        } else {
            // Dispatch event if no message target
            const event = new CustomEvent("basket:message", {
                bubbles: true,
                detail: { message, alertClass }
            });
            this.element.dispatchEvent(event);
        }
    }

    // Handles global messages - simplifiée pour éviter la duplication
    handleGlobalMessage(event) {
        if (this.hasMessageTarget && event.target !== this.element) {
            const { message, alertClass } = event.detail;
            this.messageTarget.className = `alert ${alertClass}`;
            this.messageTarget.textContent = message;
        }
    }

    // Handles global basket updates
    handleGlobalUpdate(event) {
        if (event.detail?.data && event.target !== this.element) {
            this.updateBasketButton(event.detail.data);
        }
    }

    // Adds an animation to the clicked button
    animation(clickedButton) {
        if (!clickedButton.classList.contains("btn-primary")) {
            return;
        }
        clickedButton.classList.remove("btn-primary");
        clickedButton.classList.add("btn-secondary", "zoom-out-animation");
        setTimeout(() => {
            clickedButton.classList.remove("zoom-out-animation", "btn-secondary");
            clickedButton.classList.add("btn-primary");
        }, 500);
    }

    // Translates messages
    translate(key) {
        if (typeof key !== "string") {return "";}

        const translations = this.translations?.[this.language];
        if (!translations) {return key;}

        const translationsMap = new Map(Object.entries(translations));

        return translationsMap.get(key) || key;
    }
}
