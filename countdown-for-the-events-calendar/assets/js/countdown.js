(function($) {

    // Collection of timers for this page.
    var timers = [];

    // Actual timer code, which wakes up and updates all the timers on the page.
    function updateTimers() {
        var i;
        for (i = 0; i < timers.length; i++) {
            updateTimer(timers[i]);
        }
        requestAnimationFrame(updateTimers);
    }

    // Utility function for adding zero padding.
    function zeroPad(n) {
        n = parseInt(n);
        if (n < 10) {
            return '0' + n;
        } else {
            return n;
        }
    }

    // Creates a new timer object.
    function Timer(id, endTime, format, complete) {
        this.id = id;
        this.endTime = endTime;
        this.format = format;
        this.complete = complete;
    }

    // Decrements a given timer object and renders it in the
    // appropriate div.
    function updateTimer(t) {
        var now = Date.now() / 1000;
        var remainingSeconds = t.endTime - now;
        var output = t.complete;

        if (remainingSeconds > 0) {
            var days = zeroPad(Math.floor(remainingSeconds / (60 * 60 * 24)));
            var hours = zeroPad(Math.floor((remainingSeconds % (60 * 60 * 24)) / (60 * 60)));
            var minutes = zeroPad(Math.floor((remainingSeconds % (60 * 60)) / 60));
            var seconds = zeroPad(Math.floor(remainingSeconds % 60));
            output = t.format.replace('DD', days).replace('HH', hours).replace('MM', minutes).replace('SS', seconds);
        }

        $("#" + t.id).html(output);
    }

    $(document).ready(function() {
        // Find all countdown timer divs, create a timer object for each
        // one and kick off the timers.
        var countdown_timers = $('.tec-countdown-timer-html');
        if ($(countdown_timers).length > 0) {
            $(countdown_timers).each(function(index, value) {
                var unique_id = 'tecc-uid-' + Math.floor(Math.random() * 99999);
                var seconds = parseInt($(value).find('span.tecc-seconds-section').text());
                var format = $(value).find('span.tecc-countdown-format').html();
                var complete = $(value).find('span.tecc-countdown-complete').html();

                // Wrap the timer in a span with a unique id so we can refer to it
                // in the timer update code.
                $(value).wrap($('<div>').attr('id', unique_id).addClass('tecc-timer-one'));

                // Calculate the end time based on the remaining seconds
                var endTime = (Date.now() / 1000) + seconds;
                timers.push(new Timer(unique_id, endTime, format, complete));

                // Kick off first update if we're at the end.
                if (index == countdown_timers.length - 1) {
                    requestAnimationFrame(updateTimers);
                }
            });
        }
    });
})(jQuery);
