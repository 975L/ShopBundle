 import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = [ "quantity", "total", "message" ]

    // Gets data from Symfony controller
    connect() {
        // check if total Target exist
        if (this.hasTotalTarget && this.hasQuantityTarget) {
            fetch("/basket/total", {
                method: "GET",
            })
            .then((response) => response.json())
            .then((data) => {
                this.totalTarget.textContent = (data.total / 100).toFixed(2);
                this.quantityTarget.textContent = data.quantity;
            });
        }
    }

    // Adds a product to the basket
    add(event) {
        // Deletes the info message but keeps the space
        this.messageTarget.innerHTML = "&nbsp;";

        //Adds an animation to clicked button
        this.animation(event.currentTarget);

        // Fetches data to Symfony controller
        this.fetchData(event.currentTarget);
    }

    // Adds an animation to the clicked button
    animation(clickedButton) {
        clickedButton.textContent = "Ajout";
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
                this.messageTarget.textContent = data.error;
            } else {
                this.messageTarget.classList.add("alert");
                this.messageTarget.classList.add("alert-info");
                this.messageTarget.textContent = "Produit ajouté !";
                this.update(data);
                setTimeout(() => {
                    target.textContent = "-> Panier (" + data.productQuantity + ")";
                }, 500);
            }
        })
    }

    // Updates total and quantity
    update(data) {
        this.totalTarget.textContent = (data.total / 100).toFixed(2);
        this.quantityTarget.textContent = data.quantity;
    }
}