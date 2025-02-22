 import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = [ "quantity", "total", "message", "currency" ]

    // Gets data from Symfony controller
    connect() {
        // check if total Target exist
        if (this.hasTotalTarget && this.hasQuantityTarget) {
            fetch("/basket/total", {
                method: "GET",
            })
            .then((response) => response.json())
            .then((data) => {
                this.update(data);
            });
        }
    }

    // Adds a product to the basket
    add(event) {
        // Removes message
        this.removeMessage();

        //Adds an animation to clicked button
        this.animation(event.currentTarget);

        // Fetches data to Symfony controller
        this.fetchData(event.currentTarget);
    }

    // Adds an animation to the clicked button
    animation(clickedButton) {
        clickedButton.classList.remove("btn-primary");
        clickedButton.classList.add("btn-secondary");
        clickedButton.classList.add("zoom-animation");
        setTimeout(() => {
            clickedButton.classList.remove("zoom-animation");
            clickedButton.classList.remove("btn-secondary");
            clickedButton.classList.add("btn-primary");
        }, 500);
    }

    // Deletes the basket
    delete() {
        fetch("/basket", { method: "DELETE" })
        .then(() => {
            window.location.reload();
        });
    }

    // Deletes the product
    deleteProduct(event) {
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
        .then(response => response.json())
        .then(data => {
            window.location.reload();
        });
    }

    // Fetches data to Symfony controller
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
        .then((response) => response.json())
        .then((data) => {
            if (data.error) {
                this.displayMessage(data.error, "alert-danger");
            } else {
                this.displayMessage(target.dataset.title + " " + target.dataset.added, "alert-info");
                this.update(data);
            }
        })
    }

    // Removes the alert this.messageTarget if exist
    removeMessage() {
        if (this.hasMessageTarget) {
            this.messageTarget.classList.remove("alert");
            this.messageTarget.classList.remove("alert-info");
            this.messageTarget.classList.remove("alert-danger");
            this.messageTarget.textContent = "";
        }
    }

    // Displays a message
    displayMessage(message, alertClass) {
        this.messageTarget.classList.add("alert");
        this.messageTarget.classList.add(alertClass);
        this.messageTarget.textContent = message;
    }

    // Updates total and quantity
    update(data) {
        const basketButton = document.getElementById("basket-button");
        basketButton.style.display = "block";

        // Hides the element basket-button if total = 0
        if (data.total === 0) {
            basketButton.style.display = "none";

            return;
        }

        this.totalTarget.textContent = (data.total / 100).toFixed(2);
        const currencies = {
            "eur": "€",
            "usd": "$",
            "gbp": "£",
        }
        this.currencyTarget.textContent = currencies[data.currency ? data.currency : "EUR"];
        this.quantityTarget.textContent = data.quantity;
    }
}
