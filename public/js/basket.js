import { Controller } from "@hotwired/stimulus";
import translationsEn from "./translations.en.js";
import translationsFr from "./translations.fr.js";

export default class extends Controller {
    static targets = [ "quantity", "total", "message", "shipping", "submitButton", "itemTotal", "itemQuantity" ];
    static basketDataPromise = null; // Store the fetch promise for reuse
    static lastFetchTime = 0;
    static CACHE_DURATION = 5000; // Cache duration in ms

    // Fetches data from the Symfony controller when the controller is connected
    connect() {
        // Event listeners as some controller are outside the basket controller
        document.addEventListener("basket:message", this.handleGlobalMessage.bind(this));
        document.addEventListener("basket:update", this.handleGlobalUpdate.bind(this));

        // Initialize translations
        this.language = "fr";
        this.translations = {
            en: translationsEn,
            fr: translationsFr
        };

        // Load basket data once and use for everything
        this.loadBasketData().then((data) => {
            if (data) {
                // Update basket UI and check product buttons
                this.update(data);
            }
        });
    }

    // Load basket data with caching
    loadBasketData() {
        const now = Date.now();

        // If cache expired or no promise exists
        if (!this.constructor.basketDataPromise ||
            (now - this.constructor.lastFetchTime > this.constructor.CACHE_DURATION)) {

            this.constructor.lastFetchTime = now;
            this.constructor.basketDataPromise = fetch("/shop/basket/json", {
                method: "GET"
            })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(this.translate("basket.load.error"));
                }
                return response.json();
            })
            .catch((error) => {
                this.displayMessage(this.translate("basket.load.error"), "alert-danger");
                // Reset promise on error to allow retry
                this.constructor.basketDataPromise = null;
                return null;
            });
        }

        return this.constructor.basketDataPromise;
    }

    // Deletes the basket
    delete() {
        fetch("/shop/basket", { method: "DELETE" })
        .then((response) => {
            if (!response.ok) {
                this.displayMessage(this.translate("basket.delete.error"), "alert-danger");
            }
            // Force refresh cache on basket deletion
            this.constructor.basketDataPromise = null;
            window.location.reload();
        })
        .catch((error) => {
            this.displayMessage(this.translate("basket.delete.error"), "alert-danger");
        });
    }

    // Adds a quantity of item to the basket
    addItem(event) {
        // Adds an animation to the clicked button
        this.animation(event.currentTarget);

        // Fetches data from the Symfony controller
        this.fetchData(event.currentTarget);
    }

    // Removes a quantity of item from the basket
    removeItem(event) {
        // Adds an animation to the clicked button
        this.animation(event.currentTarget);

        // Fetches data from the Symfony controller
        this.fetchData(event.currentTarget);
    }

    // Deletes completely a item
    deleteItem(event) {
        // Store event data before the asynchronous call
        const target = event.currentTarget;

        fetch("/shop/basket/delete", {
            method: "DELETE",
            body: JSON.stringify({
                id: event.currentTarget.dataset.itemId,
                quantity: 0,
                type: target.dataset.type,
            }),
            headers: {
                "Content-Type": "application/json",
            }
        })
        .then((response) => {
            if (!response.ok) {
                this.displayMessage(this.translate("product.delete.error"), "alert-danger");
            }
            return response.json();
        })
        .then((data) => {
            // Force refresh cache after deletion
            this.constructor.basketDataPromise = null;

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
                id: target.dataset.itemId,
                quantity: target.dataset.quantity,
                type: target.dataset.type,
            }),
            headers: {
                "Content-Type": "application/json",
            }
        })
        .then((response) => {
            if (!response.ok) {
                this.displayMessage(this.translate("basket.add.error"), "alert-danger");
            }
            return response.json();
        })
        .then((data) => {
            // Force refresh cache after modification
            this.constructor.basketDataPromise = null;

            if (data.error) {
                this.displayMessage(data.error, "alert-danger");
            } else {
                const message = `${target.dataset.title} ${target.dataset.text}`;
                this.displayMessage(message, "alert-" + target.dataset.alert);
                this.update(data);

                this.showToast(message + " Voir le panier");
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
            this.totalTarget.textContent = (data.basket.total / 100).toFixed(2) + this.getCurrencySymbol(data.basket.currency);
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

        // Display empty basket template if total is 0
        if (data.basket.total === 0) {
            const template = document.getElementById("empty-basket-template");
            const templateContent = template.content.cloneNode(true);
            basketPage.innerHTML = "";
            basketPage.appendChild(templateContent);

            return;
        }

        this.removeDeletedItems(data);
        this.updateExistingItems(data);
        this.updateBasketTotals(data);
        this.updateSubmitButton(data);
    }

    // Removes items that are no longer in the basket
    removeDeletedItems(data) {
        if (!data.basket || !data.basket.items) {
            return;
        }

        const itemPairs = [];
        Object.keys(data.basket.items).forEach((type) => {
            // Ensure that the type is a direct property and not inherited
            if (Object.prototype.hasOwnProperty.call(data.basket.items, type)) {
                const typeItems = data.basket.items[type];
                if (typeItems && typeof typeItems === "object") {
                    // Secure access to IDs with similar verification
                    Object.keys(typeItems).forEach((id) => {
                        if (Object.prototype.hasOwnProperty.call(typeItems, id)) {
                            itemPairs.push(`${type}-${id}`);
                        }
                    });
                }
            }
        });

        const itemRows = document.querySelectorAll("tr[id^=\"item-\"]");
        itemRows.forEach((row) => {
            const type = row.getAttribute("data-type");
            const itemId = row.getAttribute("data-item-id");

            if (!itemPairs.includes(`${type}-${itemId}`)) {
                row.classList.add("fade-out");
                setTimeout(() => row.remove(), 100);
            }
        });
    }

    // Updates existing items
    updateExistingItems(data) {
        if (!data.basket || !data.basket.items) {
            return;
        }

        Object.entries(data.basket.items).forEach(([type, items]) => {
            if (items && typeof items === "object") {
                Object.entries(items).forEach(([id, itemData]) => {
                    this.updateItemRow(`${type}-${id}`, itemData);
                });
            }
        });
    }

    // Updates the basket totals
    updateBasketTotals(data) {
        if (!data.basket) {
            return;
        }

        if (this.hasTotalTarget) {
            this.totalTarget.textContent = ((data.basket.total + data.basket.shipping) / 100).toFixed(2) + this.getCurrencySymbol(data.basket.currency);
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
            ? (data.basket.shipping / 100).toFixed(2) + this.getCurrencySymbol(data.basket.currency)
            : this.translate("basket.offered");
    }

    // Updates the submit button
    updateSubmitButton(data) {
        if (!this.hasSubmitButtonTarget || !data.basket) {
            return;
        }

        const label = this.translate("label.pay");
        const total = ((data.basket.total + data.basket.shipping) / 100).toFixed(2) + this.getCurrencySymbol(data.basket.currency);

        this.submitButtonTarget.value = `${label} ${total}`;
    }

    // Updates a single item row
    updateItemRow(combinedId, itemData) {
        // Quantity
        const itemQuantityElement = this.itemQuantityTargets.find(
            (target) => target.dataset.itemId === combinedId
        );
        if (itemQuantityElement) {
            itemQuantityElement.textContent = itemData.quantity;
        }

        // Total
        const itemTotalElement = this.itemTotalTargets.find(
            (target) => target.dataset.itemId === combinedId
        );
        if (itemTotalElement) {
            itemTotalElement.textContent = (itemData.total / 100).toFixed(2) + this.getCurrencySymbol(itemData.item.currency);
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

    // Handles global messages
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
            this.constructor.basketDataPromise = null;

            this.updateBasketButton(event.detail.data);
            this.updateAddButtons(event.detail.data);
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

    // Update product buttons with basket data
    updateAddButtons(data) {
        if (!data?.basket?.items) {
            return;
        }

        // Retrieve all add to cart buttons on the page
        const addButtons = document.querySelectorAll("[data-action='click->basket#addItem']");

        // If no buttons, stop here
        if (!addButtons.length) {
            return;
        }

        // For each add button
        addButtons.forEach((button) => {
            const type = button.dataset.type;
            const itemId = button.dataset.itemId;
            const basketItem = data.basket?.items?.[type]?.[itemId];

            // Updates quantity if item is in the basket
            if (basketItem && basketItem.quantity > 0) {
                const quantity = basketItem.quantity;
                const quantityElement = document.querySelector(`.quantity[data-item-id="${itemId}"]`);
                if (quantityElement && quantityElement.classList.contains("quantity")) {
                    quantityElement.textContent = `${quantity}`;
                }

                // Disable the button for digital item and quantity = 1
                const hasFile = !!basketItem.item?.file;
                if (hasFile && quantity >= 1) {
                    button.setAttribute("disabled", "disabled");
                }
            }

            // Disable the button if limited quantity is reached
            const limitedQuantity = parseInt(button.dataset.limited, 10);
            const orderedQuantity = parseInt(button.dataset.ordered, 10);
            const inBasketQuantity = basketItem ? basketItem.quantity : 0;

            if (!isNaN(limitedQuantity) && limitedQuantity > 0) {
                const totalOrdered = (orderedQuantity || 0) + inBasketQuantity;
                const remaining = limitedQuantity - totalOrdered;

                if (remaining <= 0) {
                    // Désactiver le bouton
                    button.setAttribute("disabled", "disabled");
                    button.classList.add("disabled");
                }
            }
        });
    }

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
    }

    // Displays a toast message
    showToast(message) {
        const oldToast = document.getElementById("basket-toast");
        if (oldToast) {
            oldToast.remove();
        }

        const toast = document.createElement("div");
        toast.id = "basket-toast";
        toast.style.position = "fixed";
        toast.style.bottom = "20px";
        toast.style.right = "20px";
        toast.style.backgroundColor = "#28a745";
        toast.style.color = "white";
        toast.style.padding = "15px 20px";
        toast.style.borderRadius = "5px";
        toast.style.boxShadow = "0 2px 10px rgba(0,0,0,0.3)";
        toast.style.zIndex = "100000";
        toast.style.opacity = "0";
        toast.style.transition = "opacity 0.3s ease";
        toast.style.fontSize = "16px";
        toast.style.display = "flex";
        toast.style.alignItems = "center";
        toast.style.cursor = "pointer";
        toast.textContent = message;

        toast.addEventListener("click", () => {
            window.location.href = "/shop/basket";
        });

        document.body.appendChild(toast);

        requestAnimationFrame(() => {
            toast.style.opacity = "1";
        });

        setTimeout(() => {
            toast.style.opacity = "0";
            setTimeout(() => toast.remove(), 300);
        }, 10000);
    }
}