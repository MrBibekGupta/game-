/**
 * Shared Event-Driven Module for Banana Game
 * Handles:
 *  - Password visibility toggles (login / register)
 *  - Asynchronous puzzle submission via fetch() with partial DOM updates
 *  - 30-second per-question countdown timer with auto-submit on expiry
 *  - Web Audio API countdown ticking sounds (soft 30-6s, louder 5-1s, stop at 0)
 *  - Web Speech API English voice feedback for correct/incorrect answers
 *  - Numeric keyboard shortcuts (0-9) for quick answer entry
 */
(function () {
    "use strict";

    var QUESTION_TIME_LIMIT = 30;
    var activeTimerId = null;
    var audioCtx = null;
    var activeAudioNodes = [];
    var audioUnlockBound = false;
    var isResumingAudio = false;
    var isSpeakingFeedback = false;
    var selectedEnglishVoice = null;
    var speechFallbackTimerId = null;

    function getAudioContext() {
        if (audioCtx && audioCtx.state !== "closed") {
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

    function unbindAudioUnlock() {
        if (!audioUnlockBound) {
            return;
        }
        audioUnlockBound = false;
        ["pointerdown", "mousedown", "keydown", "touchstart", "click"].forEach(function (evtName) {
            document.removeEventListener(evtName, unlockAudio, { capture: true });
        });
    }

    function unlockAudio() {
        var ctx = getAudioContext();
        if (!ctx) {
            return;
        }
        if (ctx.state === "running") {
            unbindAudioUnlock();
            return;
        }
        if (ctx.state === "suspended" && !isResumingAudio) {
            isResumingAudio = true;
            ctx.resume()
                .then(function () {
                    isResumingAudio = false;
                    if (ctx.state === "running") {
                        unbindAudioUnlock();
                    }
                })
                .catch(function () {
                    isResumingAudio = false;
                });
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
        var sustainEnd = startTime + Math.min(0.035, duration * 0.35);

        osc.type = waveType;
        osc.frequency.setValueAtTime(freqStart, startTime);
        osc.frequency.exponentialRampToValueAtTime(freqEnd, startTime + duration);

        gain.gain.setValueAtTime(0.0001, startTime);
        gain.gain.linearRampToValueAtTime(peakGain, startTime + 0.006);
        gain.gain.setValueAtTime(peakGain, sustainEnd);
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
        osc.stop(startTime + duration + 0.02);
    }

    function scheduleTickPulses(ctx, isFinalFive) {
        try {
            var now = ctx.currentTime;
            if (isFinalFive) {
                // Louder, urgent double-tick for final 5..1 seconds
                playTickPulse(ctx, now, 1200, 880, 0.78, 0.1, "triangle");
                playTickPulse(ctx, now + 0.115, 1500, 1050, 0.68, 0.095, "triangle");
            } else {
                // Clear, audible clock-like tick for 30..6 seconds
                playTickPulse(ctx, now, 1000, 700, 0.38, 0.11, "triangle");
            }
        } catch (e) {
            // Ignore audio scheduling errors
        }
    }

    function playTickSound(isFinalFive) {
        var ctx = getAudioContext();
        if (!ctx || ctx.state !== "running") {
            return;
        }
        scheduleTickPulses(ctx, isFinalFive);
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

    function clearSpeechFallbackTimer() {
        if (speechFallbackTimerId !== null) {
            clearTimeout(speechFallbackTimerId);
            speechFallbackTimerId = null;
        }
    }

    function selectEnglishVoice() {
        if (!("speechSynthesis" in window) || typeof window.speechSynthesis.getVoices !== "function") {
            return null;
        }
        var voices = window.speechSynthesis.getVoices();
        if (!voices || voices.length === 0) {
            return selectedEnglishVoice;
        }

        var preferred = null;
        for (var i = 0; i < voices.length; i++) {
            var lang = (voices[i].lang || "").toLowerCase();
            if (lang === "en-us") {
                preferred = voices[i];
                break;
            }
            if (!preferred && lang.indexOf("en") === 0) {
                preferred = voices[i];
            }
        }
        selectedEnglishVoice = preferred || voices[0];
        return selectedEnglishVoice;
    }

    function initSpeechVoices() {
        if (!("speechSynthesis" in window)) {
            return;
        }
        selectEnglishVoice();
        if (typeof window.speechSynthesis.onvoiceschanged !== "undefined") {
            window.speechSynthesis.onvoiceschanged = selectEnglishVoice;
        }
    }

    function stopSpeech() {
        clearSpeechFallbackTimer();
        isSpeakingFeedback = false;
        if ("speechSynthesis" in window) {
            try {
                window.speechSynthesis.cancel();
            } catch (e) {
                // Ignore speech cancellation errors
            }
        }
    }

    function speakFeedback(text) {
        if (!text || !("speechSynthesis" in window) || typeof window.SpeechSynthesisUtterance !== "function") {
            isSpeakingFeedback = false;
            return;
        }

        stopSpeech();
        isSpeakingFeedback = true;

        try {
            var utterance = new SpeechSynthesisUtterance(text);
            var voice = selectEnglishVoice();
            utterance.lang = "en-US";
            if (voice) {
                utterance.voice = voice;
            }
            utterance.rate = 1.0;
            utterance.pitch = 1.0;
            utterance.volume = 1.0;

            var finished = false;
            function finishSpeech() {
                if (finished) {
                    return;
                }
                finished = true;
                clearSpeechFallbackTimer();
                isSpeakingFeedback = false;
            }

            utterance.onend = finishSpeech;
            utterance.onerror = finishSpeech;
            speechFallbackTimerId = setTimeout(finishSpeech, 4500);

            window.speechSynthesis.speak(utterance);
        } catch (e) {
            isSpeakingFeedback = false;
            clearSpeechFallbackTimer();
        }
    }

    function announceFeedbackFromDom(gameArea) {
        var status = gameArea.dataset.feedbackStatus || "";
        var correctAnswer = gameArea.dataset.correctAnswer;

        delete gameArea.dataset.feedbackStatus;
        delete gameArea.dataset.correctAnswer;

        if (status === "correct") {
            speakFeedback("Correct! Great Job!");
        } else if (status === "incorrect" && typeof correctAnswer !== "undefined" && correctAnswer !== "") {
            speakFeedback("Incorrect! The correct answer is " + correctAnswer + ".");
        }
    }

    function clearQuestionTimer() {
        if (activeTimerId !== null) {
            clearInterval(activeTimerId);
            activeTimerId = null;
        }
        stopAllSounds();
    }

    function initGameArea(shouldSpeakFeedback) {
        var gameArea = document.getElementById("game-area");
        if (!gameArea) {
            return;
        }

        clearQuestionTimer();

        if (shouldSpeakFeedback) {
            announceFeedbackFromDom(gameArea);
        } else {
            delete gameArea.dataset.feedbackStatus;
            delete gameArea.dataset.correctAnswer;
        }

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
            stopSpeech();

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
                        var nextStatus = nextGameArea.getAttribute("data-feedback-status") || "";
                        var nextAnswer = nextGameArea.getAttribute("data-correct-answer");
                        if (nextStatus !== "") {
                            gameArea.dataset.feedbackStatus = nextStatus;
                        }
                        if (nextAnswer !== null && nextAnswer !== "") {
                            gameArea.dataset.correctAnswer = nextAnswer;
                        }
                        initGameArea(true);
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
                } else if (!isSpeakingFeedback) {
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
        unlockAudio();
        initSpeechVoices();
        initPasswordToggles();
        initGameArea(false);
        initKeyboardShortcuts();
    });

    function cleanupAllMedia() {
        clearQuestionTimer();
        stopSpeech();
    }

    window.addEventListener("pagehide", cleanupAllMedia);
    window.addEventListener("beforeunload", cleanupAllMedia);
})();
