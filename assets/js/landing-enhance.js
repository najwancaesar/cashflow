(function () {
    var navbar = document.querySelector('.site-navbar');
    var fadeItems = Array.prototype.slice.call(document.querySelectorAll('.fade'));
    var chart = document.querySelector('.line-chart-enhanced');

    function updateNavbarState() {
        if (!navbar) {
            return;
        }

        if (window.scrollY > 18) {
            navbar.classList.add('is-scrolled');
        } else {
            navbar.classList.remove('is-scrolled');
        }
    }

    function revealElements() {
        if (!fadeItems.length) {
            return;
        }

        if (!('IntersectionObserver' in window)) {
            fadeItems.forEach(function (item) {
                item.classList.add('is-visible');
            });

            if (chart) {
                chart.classList.add('is-drawn');
            }
            return;
        }

        fadeItems.forEach(function (item, index) {
            item.style.setProperty('--reveal-delay', (index % 5) * 90 + 'ms');
        });

        var revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }

                var element = entry.target;
                requestAnimationFrame(function () {
                    element.classList.add('is-visible');
                });
                revealObserver.unobserve(element);
            });
        }, {
            threshold: 0.12,
            rootMargin: '0px 0px -6% 0px'
        });

        fadeItems.forEach(function (item) {
            revealObserver.observe(item);
        });

        if (chart) {
            var chartObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    chart.classList.add('is-drawn');
                    chartObserver.unobserve(chart);
                });
            }, {
                threshold: 0.28
            });

            chartObserver.observe(chart);
        }
    }

    function setupMobileNavigation() {
        var collapse = document.getElementById('navbarMain');
        var toggler = document.querySelector('.navbar-toggler');

        if (!collapse) {
            return;
        }

        Array.prototype.slice.call(collapse.querySelectorAll('a')).forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth > 767) {
                    return;
                }

                if (window.jQuery && window.jQuery.fn && window.jQuery.fn.collapse) {
                    window.jQuery(collapse).collapse('hide');
                } else {
                    collapse.classList.remove('in');
                    collapse.style.height = '';
                }

                if (toggler) {
                    toggler.setAttribute('aria-expanded', 'false');
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        updateNavbarState();
        revealElements();
        setupMobileNavigation();
    });

    window.addEventListener('scroll', updateNavbarState, {
        passive: true
    });
})();
