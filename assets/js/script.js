/**
 * Shared Event-Driven Module for Banana Game
 * Handles:
 *  - Password visibility toggles (login / register)
 *  - Asynchronous puzzle submission via fetch() with partial DOM updates
 *  - 30-second per-question countdown timer with auto-submit on expiry
 *  - Web Audio API countdown ticking sounds (soft 30-6s, louder 5-1s, stop at 0)
 *  - Numeric keyboard shortcuts (0-9) for quick answer entry
 */
(function () {
    "use strict";

    var QUESTION_TIME_LIMIT = 30;
    var activeTimerId = null;
    var audioCtx = null;
    var activeAudioNodes = [];
    var audioUnlockBound = false;

    function getAudioContext() {
        if (audioCtx) {
            return audioCtx;
        }
        var AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) {
            return null;
        }
        try {
            audioCtx = new AudioCtx();
        } catch (e) {
            audioCtx = null;
        }
        return audioCtx;
    }

    function unlockAudio() {
        var ctx = getAudioContext();
        if (ctx && ctx.state === "suspended") {
            ctx.resume().catch(function () {});
        }
    }

    function bindAudioUnlock() {
        if (audioUnlockBound) {
            return;
        }
        audioUnlockBound = true;
        ["pointerdown", "mousedown", "keydown", "touchstart", "click"].forEach(function (evtName) {
            document.addEventListener(evtName, unlockAudio, { capture: true, passive: true });
        });
    }

    function stopAllSounds() {
        while (activeAudioNodes.length > 0) {
            var node = activeAudioNodes.pop();
            try {
                node.stop(0);
                node.disconnect();
            } catch (e) {
                // Node already stopped or disconnected
            }
        }
    }

    function playTickPulse(ctx, startTime, freqStart, freqEnd, peakGain, duration, waveType) {
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();

        osc.type = waveType;
        osc.frequency.setValueAtTime(freqStart, startTime);
        osc.frequency.exponentialRampToValueAtTime(freqEnd, startTime + duration);

        gain.gain.setValueAtTime(0.0001, startTime);
        gain.gain.linearRampToValueAtTime(peakGain, startTime + 0.005);
        gain.gain.exponentialRampToValueAtTime(0.0001, startTime + duration);

        osc.connect(gain);
        gain.connect(ctx.destination);

        activeAudioNodes.push(osc);
        osc.onended = function () {
            var idx = activeAudioNodes.indexOf(osc);
            if (idx !== -1) {
                activeAudioNodes.splice(idx, 1);
            }
            try {
                gain.disconnect();
            } catch (e) {}
        };

        osc.start(startTime);
        osc.stop(startTime + duration + 0.015);
    }

    function scheduleTickPulses(ctx, isFinalFive) {
        try {
            var now = ctx.currentTime;
            if (isFinalFive) {
                // Louder, urgent double-tick for final 5..1 seconds
                playTickPulse(ctx, now, 1100, 820, 0.55, 0.075, "triangle");
                playTickPulse(ctx, now + 0.085, 1320, 980, 0.45, 0.07, "triangle");
            } else {
                // Audible, soft tick for 30..6 seconds
                playTickPulse(ctx, now, 900, 620, 0.22, 0.07, "triangle");
            }
        } catch (e) {
            // Ignore audio scheduling errors
        }
    }

    function playTickSound(isFinalFive) {
        var ctx = getAudioContext();
        if (!ctx) {
            return;
        }
        if (ctx.state === "running") {
            scheduleTickPulses(ctx, isFinalFive);
            return;
        }
        if (ctx.state === "suspended") {
            ctx.resume().then(function () {
                if (ctx.state === "running" && activeTimerId !== null) {
                    scheduleTickPulses(ctx, isFinalFive);
                }
            }).catch(function () {});
        }
    }

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
        stopAllSounds();
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
                } else {
                    playTickSound(remaining <= 5);
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
        bindAudioUnlock();
        initPasswordToggles();
        initGameArea();
        initKeyboardShortcuts();
    });

    window.addEventListener("pagehide", clearQuestionTimer);
    window.addEventListener("beforeunload", clearQuestionTimer);
})();
