(function ($) {
    'use strict';

    // Helper functions
    function trackFriction(type, data) {
        // Add debug logging
        console.log('Sending friction data:', {
            type: type,
            data: data,
            session_id: getSessionId()
        });

        return $.ajax({
            url: cfaData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cfa_track_friction',
                nonce: cfaData.nonce,
                session_id: getSessionId(),
                type: type,
                data: JSON.stringify(data)
            },
            success: function (response) {
                console.log('Friction tracking success:', response);
            },
            error: function (xhr, status, error) {
                console.error('Friction tracking error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
            }
        });
    }

    function getSessionId() {
        let sessionId = sessionStorage.getItem('cfa_session_id');
        if (!sessionId) {
            sessionId = Math.random().toString(36).substring(2, 15);
            sessionStorage.setItem('cfa_session_id', sessionId);
        }
        return sessionId;
    }    // Initialize tracking when document is ready
    $(document).ready(function () {
        // Generate and store session ID if not exists
        let sessionId = sessionStorage.getItem('cfa_session_id');
        if (!sessionId) {
            sessionId = Math.random().toString(36).substring(2, 15);
            sessionStorage.setItem('cfa_session_id', sessionId);
        }

        // Track session start
        trackFriction('session_start', {
            timestamp: new Date().toISOString(),
            session_id: sessionId
        });

        // Track page load time
        const pageLoadTime = performance.now();
        trackFriction('page_load', {
            load_time: pageLoadTime,
            session_id: sessionId
        });

        // If on checkout page, track checkout start
        if (isCheckoutPage()) {
            trackFriction('checkout_start', {
                timestamp: new Date().toISOString(),
                session_id: sessionId
            });
        }

        // If on order received page, track order completion
        if (isOrderComplete()) {
            trackFriction('order_completed', {
                timestamp: new Date().toISOString(),
                session_id: sessionId
            });
        }
    });

    // Track form field interactions
    $('form.checkout input, form.checkout select, form.checkout textarea').on('change', function () {
        trackFriction('field_change', {
            field_id: $(this).attr('id'),
            field_name: $(this).attr('name'),
            field_type: $(this).attr('type'),
            value: $(this).val(),
            session_id: getSessionId()
        });
    });

    // Track form validation errors
    $(document.body).on('checkout_error', function () {
        var errors = $('.woocommerce-error li').map(function () {
            return $(this).text();
        }).get();

        // Store latest errors in sessionStorage
        sessionStorage.setItem('cfa_last_checkout_errors', JSON.stringify(errors));

        trackFriction('validation_error', {
            errors: errors,
            session_id: getSessionId()
        });
    });

    // Track cart updates
    $(document.body).on('added_to_cart removed_from_cart', function (event, fragments, cart_hash, $button) {
        const sessionId = getSessionId();
        const productId = $button.data('product_id');
        const quantity = $button.data('quantity');

        console.log('CFA: Cart event - Type:', event.type, 'Product ID:', productId, 'Quantity:', quantity);

        trackFriction(event.type, {
            session_id: sessionId,
            product_id: productId,
            quantity: quantity,
            cart_hash: cart_hash,
            timestamp: new Date().toISOString()
        });
    });

    // Track cart item quantity changes
    $(document.body).on('cart_item_removed', function (event, cart_item_key) {
        const sessionId = getSessionId();

        console.log('CFA: Cart item removed - Key:', cart_item_key);

        trackFriction('cart_item_removed', {
            session_id: sessionId,
            cart_item_key: cart_item_key,
            timestamp: new Date().toISOString()
        });
    });

    // Track cart updates
    $(document.body).on('updated_cart_totals', function () {
        const sessionId = getSessionId();
        const cartItems = $('.woocommerce-cart-form__cart-item').length;

        console.log('CFA: Cart updated - Items:', cartItems);

        trackFriction('cart_updated', {
            session_id: sessionId,
            cart_items: cartItems,
            timestamp: new Date().toISOString()
        });
    });

    // Track checkout steps
    $('form.checkout').on('checkout_place_order', function () {
        trackFriction('checkout_submit', {
            timestamp: new Date().toISOString(),
            session_id: getSessionId()
        });
    });

    // Track payment method changes
    $('form.checkout').on('payment_method_selected', function () {
        trackFriction('payment_method_change', {
            method: $('input[name="payment_method"]:checked').val(),
            session_id: getSessionId()
        });
    });

    // Track shipping method changes
    $(document.body).on('updated_checkout', function () {
        trackFriction('shipping_method_change', {
            method: $('input[name="shipping_method[0]"]:checked').val(),
            session_id: getSessionId()
        });
    });

    // Track form abandonment
    let formStartTime = new Date();
    let orderCompleted = false;

    // Set order completed flag when order is processed
    $(document.body).on('checkout_place_order', function () {
        orderCompleted = true;
    });

    $(window).on('beforeunload', function () {
        if (isCheckoutPage() && !orderCompleted && !isOrderComplete()) {
            var abandonedFields = [];

            // Get all form fields
            $('form.checkout input, form.checkout select, form.checkout textarea').each(function () {
                if ($(this).val() === '') {
                    abandonedFields.push({
                        name: $(this).attr('name'),
                        type: $(this).attr('type'),
                        id: $(this).attr('id')
                    });
                }
            });

            // Get last validation errors from sessionStorage
            var lastErrors = [];
            try {
                lastErrors = JSON.parse(sessionStorage.getItem('cfa_last_checkout_errors')) || [];
            } catch (e) {
                lastErrors = [];
            }

            trackFriction('form_abandonment', {
                time_spent: (new Date() - formStartTime) / 1000,
                fields_filled: $('form.checkout input[value!=""]').length,
                abandoned_fields: abandonedFields,
                last_errors: lastErrors.map(error => error.trim()),
                session_id: getSessionId()
            });
        }
    });

    // Track field focus/blur
    $('form.checkout input, form.checkout select, form.checkout textarea').on('focus blur', function (e) {
        trackFriction('field_' + e.type, {
            field_id: $(this).attr('id'),
            field_name: $(this).attr('name')
        });
    });

    // Track scrolling
    let lastScrollTop = 0;
    $(window).on('scroll', function () {
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
    setInterval(function () {
        trackFriction('time_spent', {
            seconds: Math.floor((new Date() - startTime) / 1000)
        });
    }, 30000);

    // Track device and browser info
    trackFriction('page_view', {
        user_agent: navigator.userAgent,
        screen_width: window.innerWidth,
        screen_height: window.innerHeight
    }); function getCurrentCheckoutStep() {
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