(function($) {
    'use strict';

    // Generate or retrieve session ID
    function getSessionId() {
        let sessionId = sessionStorage.getItem('cfa_session_id');
        if (!sessionId) {
            sessionId = Math.random().toString(36).substring(2, 15);
            sessionStorage.setItem('cfa_session_id', sessionId);
        }
        return sessionId;
    }

    // Track session start
    trackFrictionPoint('session_start', {
        timestamp: new Date().toISOString()
    });

    // Track page load time
    const pageLoadStart = performance.now();
    $(window).on('load', function() {
        const pageLoadTime = performance.now() - pageLoadStart;
        trackFrictionPoint('page_load', {
            load_time: pageLoadTime
        });
    });

    // Track form field interactions
    $('form.checkout input, form.checkout select, form.checkout textarea').on('change', function() {
        trackFriction('field_change', {
            field_id: $(this).attr('id'),
            field_name: $(this).attr('name'),
            field_type: $(this).attr('type'),
            value: $(this).val()
        });
    });

    // Track form validation errors
    $(document.body).on('checkout_error', function() {
        trackFriction('validation_error', {
            errors: $('.woocommerce-error li').map(function() {
                return $(this).text();
            }).get()
        });
    });

    // Track cart updates
    $(document.body).on('added_to_cart removed_from_cart', function(event, fragments, cart_hash, $button) {
        trackFriction(event.type, {
            product_id: $button.data('product_id'),
            quantity: $button.data('quantity')
        });
    });

    // Track checkout steps
    $('form.checkout').on('checkout_place_order', function() {
        trackFriction('checkout_submit', {
            timestamp: new Date().toISOString()
        });
    });

    // Track payment method changes
    $('form.checkout').on('payment_method_selected', function() {
        trackFriction('payment_method_change', {
            method: $('input[name="payment_method"]:checked').val()
        });
    });

    // Track shipping method changes
    $(document.body).on('updated_checkout', function() {
        trackFriction('shipping_method_change', {
            method: $('input[name="shipping_method[0]"]:checked').val()
        });
    });

    // Track form abandonment
    let formStartTime = new Date();
    $(window).on('beforeunload', function() {
        if ($('form.checkout').length) {
            trackFriction('form_abandonment', {
                time_spent: (new Date() - formStartTime) / 1000,
                fields_filled: $('form.checkout input[value!=""]').length
            });
        }
    });

    // Track field focus/blur
    $('form.checkout input, form.checkout select, form.checkout textarea').on('focus blur', function(e) {
        trackFriction('field_' + e.type, {
            field_id: $(this).attr('id'),
            field_name: $(this).attr('name')
        });
    });

    // Track scrolling
    let lastScrollTop = 0;
    $(window).on('scroll', function() {
        let currentScroll = $(this).scrollTop();
        if (Math.abs(currentScroll - lastScrollTop) > 100) {
            trackFriction('scroll', {
                direction: currentScroll > lastScrollTop ? 'down' : 'up',
                position: currentScroll
            });
            lastScrollTop = currentScroll;
        }
    });

    // Track time spent
    let startTime = new Date();
    setInterval(function() {
        trackFriction('time_spent', {
            seconds: Math.floor((new Date() - startTime) / 1000)
        });
    }, 30000);

    // Track device and browser info
    trackFriction('page_view', {
        user_agent: navigator.userAgent,
        screen_width: window.innerWidth,
        screen_height: window.innerHeight
    });

    // Helper functions
    function trackFrictionPoint(type, data) {
        $.ajax({
            url: cfaData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cfa_track_friction',
                nonce: cfaData.nonce,
                session_id: getSessionId(),
                type: type,
                data: data
            }
        });
    }

    function getCurrentCheckoutStep() {
        if ($('#payment').is(':visible')) {
            return 'payment';
        } else if ($('#customer_details').is(':visible')) {
            return 'customer_details';
        } else if ($('#order_review').is(':visible')) {
            return 'order_review';
        }
        return 'cart';
    }

    function isCheckoutPage() {
        return $('form.checkout').length > 0;
    }

    function isOrderComplete() {
        return $('body').hasClass('woocommerce-order-received');
    }

})(jQuery); 