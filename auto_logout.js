(function () {

    const TIMEOUT = 5 * 60 * 1000; // 5 minutes

    let timer;

    function logoutAutomatically() {

        // Redirect automatically to the login page
        // without requiring the user to refresh.
        window.location.replace("login.php?timeout=1");

    }

    function resetTimer() {

        clearTimeout(timer);

        timer = setTimeout(function () {

            logoutAutomatically();

        }, TIMEOUT);

    }

    const events = [
        "mousemove",
        "mousedown",
        "keypress",
        "touchstart",
        "scroll",
        "click"
    ];

    events.forEach(function (event) {

        document.addEventListener(
            event,
            resetTimer,
            true
        );

    });

    // Start the timer immediately
    resetTimer();

})();