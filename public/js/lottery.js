import { Controller } from "@hotwired/stimulus";
import Handlers from "./handlers.js";

export default class extends Controller {
    static targets = ["drawButton", "drum", "ticketPlaceholder", "winnerNumber", "winnerName"];

    static values = {
        identifier: String,
        drawUrl: { type: String, default: "/shop/lottery/IDENTIFIER/draw/PRIZE_RANK" }
    };

    // Draws the lottery
    draw(event) {
        const button = event.currentTarget;
        const prizeRank = button.getAttribute("data-prize-rank");

        // Disables all buttons during the draw
        this.drawButtonTargets.forEach(btn => btn.disabled = true);

        // Show animation spin wheel
        this.animationDuration = Math.floor(8000 + Math.random() * 4000);
        if (this.hasDrumTarget) {
            this.drumTarget.style.animationDuration = `${this.animationDuration / 1000}s`;
            this.drumTarget.classList.add("visible");
            this.drumTarget.classList.add("spinning");

            // Animation for tickets
            this.animateTickets(this.animationDuration);
        }

        // Calls API
        this.drawWinner(prizeRank, this.animationDuration);
    }

    // Draws a winner
    drawWinner(prizeRank, duration) {
        // Build url by replacing identifier and prize rank
        let drawUrl = this.drawUrlValue
            .replace("IDENTIFIER", this.identifierValue)
            .replace("PRIZE_RANK", prizeRank);

        fetch(drawUrl, {
            method: "POST",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Content-Type": "application/json"
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                this.displayMessage(data.error, "alert-danger");

                return;
            }

            // Launches animation
            setTimeout(() => {
                this.showWinner(data, prizeRank);
            }, duration);
        })
        .catch(error => {
            console.error("Error:", error);
            this.showError("Failed to draw a winner. Please try again.");
            this.displayMessage(this.translate("failed.draw.winner"), "alert-danger");

            // Resets interface
            if (this.hasDrumTarget) {
                this.drumTarget.classList.remove("spinning");
            }

            this.activateDrawButtons();
        });
    }

    // Shows the winner
    showWinner(data, prizeRank) {
        this.displayWinner(data);

        // Updates data
        setTimeout(() => {
            this.updateNumber(data, prizeRank);
            this.updateName(data, prizeRank);
            this.replaceDrawButtonWithDate(prizeRank);
            this.activateDrawButtons();
        }, 500);
    }

    // Displays the winner
    displayWinner(data) {
        // Stops animation
        if (this.hasDrumTarget) {
            this.drumTarget.classList.remove("spinning");

            // Displays the winner
            if (this.hasTicketPlaceholderTarget) {
                this.ticketPlaceholderTarget.textContent = data.number;
                this.ticketPlaceholderTarget.classList.add("winner");
                this.ticketPlaceholderTarget.style.opacity = 1;
            }
        }
    }

    // Updates ticket number
    updateNumber(data, prizeRank) {
        if (this.hasWinnerNumberTarget) {
            const winnerNumberElement = this.winnerNumberTargets.find((element) =>
                element.closest("[data-prize-rank]")?.getAttribute("data-prize-rank") === prizeRank.toString()
            );

            if (winnerNumberElement) {
                winnerNumberElement.textContent = data.number;
                winnerNumberElement.classList.remove("not-drawn");
                winnerNumberElement.classList.add("winner");
            }
        }

        // Updates in the tickets list
        const ticketElement = document.getElementById(data.number);
        if (ticketElement) {
            ticketElement.classList.add("winner");
        }
    }

    // Updates winner name
    updateName(data, prizeRank) {
        if (this.hasWinnerNameTarget) {
            const winnerNameElement = this.winnerNameTargets.find((element) =>
                element.closest("[data-prize-rank]")?.getAttribute("data-prize-rank") === prizeRank.toString()
            );

            if (winnerNameElement) {
                const contributorName = data.name || this.translate("anonymous");
                winnerNameElement.innerHTML = `${this.translate("name")}: <strong>${contributorName}</strong>`;
            }
        }
    }

    // Replaces draw button by draw date
    replaceDrawButtonWithDate(prizeRank) {
        this.drawButtonTargets.forEach((btn) => {
            if (btn.getAttribute("data-prize-rank") === prizeRank.toString()) {
                const drawDateText = document.createElement("p");
                drawDateText.className = "text text-center";

                // Replaces button
                const formattedDate = Handlers.formatDate(new Date(), "fr-FR");
                drawDateText.innerHTML = `${this.translate("draw.date")} : <strong>${formattedDate}</strong>`;
                const parentElement = btn.parentElement;
                parentElement.replaceChild(drawDateText, btn);
            }
        });
    }

    // Activates draw buttons
    activateDrawButtons() {
        this.drawButtonTargets.forEach(btn => {
            if (!btn.closest(".prize-card")?.classList.contains("drawn")) {
                btn.disabled = false;
            }
        });
    }

    // Animates tickets
    animateTickets(duration) {
        if (!this.hasDrumTarget) return;

        // Hides placeholder
        if (this.hasTicketPlaceholderTarget) {
            this.ticketPlaceholderTarget.style.opacity = 0;
        }

        // Defines tickets to use
        const eligibleTickets = this.element.querySelectorAll(".ticket-number:not(.not-drawn):not(.winner)");
        const ticketNumbers = Array.from(eligibleTickets).map((el) => el.textContent.trim());
        const ticketCount = Math.min(30, ticketNumbers.length || 30);
        const interval = duration / ticketCount;

        // Animates tickets
        for (let i = 0; i < ticketCount; i++) {
            const appearTime = i * interval / 2;

            setTimeout(() => {
                const ticket = document.createElement("p");
                ticket.className = "lottery-ticket";
                ticket.textContent = ticketNumbers[Math.floor(Math.random() * ticketNumbers.length)];
                ticket.style.left = `${Math.random() * 80 + 10}%`;
                ticket.style.top = `${Math.random() * 80 + 10}%`;
                ticket.style.transform = `rotate(${Math.random() * 360}deg) scale(${Math.random() * 0.5 + 0.5})`;
                this.drumTarget.appendChild(ticket);

                setTimeout(() => ticket.style.opacity = 1, 50);

                // Suppress ticket after animation
                const displayDuration = 1500;
                setTimeout(() => {
                    ticket.style.opacity = 0;
                    setTimeout(() => ticket.remove(), 300);
                }, displayDuration);
            }, appearTime);
        }
    }

    // HANDLERS
    displayMessage(message, alertClass) {
        Handlers.displayMessage(message, alertClass);
    }

    translate(key) {
        return Handlers.translate(key);
    }
}