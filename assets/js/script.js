/**
 * Shared Event-Driven Module for Banana Game
 * Handles:
 *  - Password visibility toggles (login / register)
 *  - Asynchronous puzzle submission via fetch() with partial DOM updates
 *  - 45-second per-question countdown timer with auto-submit on expiry
 *  - Numeric keyboard shortcuts (0-9) for quick answer entry
 */
(function () {
    "use strict";

    var QUESTION_TIME_LIMIT = 45;
    var activeTimerId = null;

    function initPasswordToggles() {
        document.querySelectorAll(".toggle").forEach(function (btn) {
            if (btn.dataset.bound === "1") {
                return;
            }
            btn.dataset.bound = "1";
            btn.addEventListener("click", function () {
                var input = document.getElementById(btn.dataset.target);
                if (!input) {
                    return;
                }
                var show = input.type === "password";
                input.type = show ? "text" : "password";
                btn.textContent = show ? "Hide" : "Show";
            });
        });
    }

    function clearQuestionTimer() {
        if (activeTimerId !== null) {
            clearInterval(activeTimerId);
            activeTimerId = null;
        }
    }

    function initGameArea() {
        var gameArea = document.getElementById("game-area");
        if (!gameArea) {
            return;
        }

        clearQuestionTimer();

        var form = gameArea.querySelector(".answer-form");
        var answerInput = gameArea.querySelector("#answer");
        var timerSecondsEl = gameArea.querySelector("#timer-seconds");
        var timerBadgeEl = gameArea.querySelector("#timer-badge");
        var isSubmitting = false;

        if (answerInput) {
            answerInput.focus();
        }

        function submitAnswerAsync(isTimeout) {
            if (!form || isSubmitting) {
                return;
            }
            isSubmitting = true;
            clearQuestionTimer();

            var formData = new FormData(form);
            if (isTimeout) {
                formData.set("timeout", "1");
            }

            var submitBtn = form.querySelector("button[type='submit']");
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = "Checking...";
            }

            fetch("play.php", {
                method: "POST",
                body: formData,
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                },
                credentials: "same-origin"
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error("HTTP " + response.status);
                    }
                    return response.text();
                })
                .then(function (html) {
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, "text/html");
                    var nextGameArea = doc.getElementById("game-area");

                    if (nextGameArea) {
                        gameArea.innerHTML = nextGameArea.innerHTML;
                        initGameArea();
                    } else {
                        // Fallback if session expired and login page was returned
                        window.location.reload();
                    }
                })
                .catch(function () {
                    isSubmitting = false;
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = "Submit";
                    }
                    // Fallback to standard form submit if fetch fails
                    form.submit();
                });
        }

        if (form) {
            form.addEventListener("submit", function (event) {
                event.preventDefault();
                submitAnswerAsync(false);
            });
        }

        if (form && timerSecondsEl) {
            var configuredLimit = timerBadgeEl ? parseInt(timerBadgeEl.dataset.timeLimit, 10) : NaN;
            var remaining = (!isNaN(configuredLimit) && configuredLimit > 0) ? configuredLimit : QUESTION_TIME_LIMIT;
            timerSecondsEl.textContent = String(remaining);

            activeTimerId = setInterval(function () {
                remaining -= 1;
                if (remaining < 0) {
                    remaining = 0;
                }
                timerSecondsEl.textContent = String(remaining);

                if (timerBadgeEl) {
                    if (remaining <= 5) {
                        timerBadgeEl.classList.remove("warning");
                        timerBadgeEl.classList.add("critical");
                    } else if (remaining <= 15) {
                        timerBadgeEl.classList.remove("critical");
                        timerBadgeEl.classList.add("warning");
                    } else {
                        timerBadgeEl.classList.remove("warning", "critical");
                    }
                }

                if (remaining <= 0) {
                    clearQuestionTimer();
                    submitAnswerAsync(true);
                }
            }, 1000);
        }
    }

    function initKeyboardShortcuts() {
        document.addEventListener("keydown", function (event) {
            var gameArea = document.getElementById("game-area");
            if (!gameArea) {
                return;
            }
            var answerInput = gameArea.querySelector("#answer");
            if (!answerInput) {
                return;
            }
            if (event.ctrlKey || event.metaKey || event.altKey) {
                return;
            }

            // If user presses 0-9 while input isn't focused, focus and append digit
            if (event.key >= "0" && event.key <= "9" && document.activeElement !== answerInput) {
                event.preventDefault();
                answerInput.focus();
                answerInput.value = (answerInput.value || "") + event.key;
            }
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        initPasswordToggles();
        initGameArea();
        initKeyboardShortcuts();
    });
})();
