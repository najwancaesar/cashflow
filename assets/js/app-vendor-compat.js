(function () {
    'use strict';

    var NativePerfectScrollbar = window.PerfectScrollbar;

    if (typeof NativePerfectScrollbar !== 'function') {
        return;
    }

    function supportsNativeTouchScroll() {
        return 'ontouchstart' in window ||
            (window.navigator && Number(window.navigator.maxTouchPoints || 0) > 0);
    }

    function CashflowPerfectScrollbar(element, options) {
        if (supportsNativeTouchScroll()) {
            return {
                element: element,
                update: function () {},
                destroy: function () {}
            };
        }

        return new NativePerfectScrollbar(element, options);
    }

    CashflowPerfectScrollbar.prototype = NativePerfectScrollbar.prototype;
    window.PerfectScrollbar = CashflowPerfectScrollbar;
})();
