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
            success: function(response) {
                console.log('Friction tracking success:', response);
            },
            error: function(xhr, status, error) {
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
    $(document).ready(function() {
        // Track session start
        trackFriction('session_start', {
            timestamp: new Date().toISOString()
        });

        // Track page load time
        const pageLoadTime = performance.now();
        trackFriction('page_load', {
            load_time: pageLoadTime
        });
    });

    // Track form field interactions
    $('form.checkout input, form.checkout select, form.checkout textarea').on('change', function () {
        trackFriction('field_change', {
            field_id: $(this).attr('id'),
            field_name: $(this).attr('name'),
            field_type: $(this).attr('type'),
            value: $(this).val()
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
            errors: errors
        });
    });

    // Track cart updates
    $(document.body).on('added_to_cart removed_from_cart', function (event, fragments, cart_hash, $button) {
        trackFriction(event.type, {
            product_id: $button.data('product_id'),
            quantity: $button.data('quantity')
        });
    });

    // Track checkout steps
    $('form.checkout').on('checkout_place_order', function () {
        trackFriction('checkout_submit', {
            timestamp: new Date().toISOString()
        });
    });

    // Track payment method changes
    $('form.checkout').on('payment_method_selected', function () {
        trackFriction('payment_method_change', {
            method: $('input[name="payment_method"]:checked').val()
        });
    });

    // Track shipping method changes
    $(document.body).on('updated_checkout', function () {
        trackFriction('shipping_method_change', {
            method: $('input[name="shipping_method[0]"]:checked').val()
        });
    });    // Track form abandonment
    let formStartTime = new Date();
    $(window).on('beforeunload', function () {
        if ($('form.checkout').length) {
            // Collect required fields that are empty
            var abandonedFields = [];
            $('form.checkout input[required], form.checkout select[required], form.checkout textarea[required]').each(function () {
                var $field = $(this);
                var fieldValue = $field.val();
                var fieldName = $field.attr('name');
                
                // Skip if field doesn't have a name
                if (!fieldName) return;
                
                // Consider field abandoned if:
                // 1. Empty or only whitespace
                // 2. Invalid format (for specific field types)
                var isAbandoned = false;
                
                if (!fieldValue || !fieldValue.trim()) {
                    isAbandoned = true;
                } else {
                    // Additional validation for specific fields
                    switch(fieldName) {
                        case 'billing_email':
                            isAbandoned = !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(fieldValue);
                            break;
                        case 'billing_phone':
                            isAbandoned = !/^\d{10,}$/.test(fieldValue.replace(/[^0-9]/g, ''));
                            break;
                        case 'billing_postcode':
                            isAbandoned = !/^[A-Z0-9]{3,}$/.test(fieldValue.toUpperCase());
                            break;
                    }
                }
                
                if (isAbandoned) {
                    abandonedFields.push({
                        id: $field.attr('id'),
                        name: fieldName,
                        type: $field.attr('type'),
                        value: fieldValue // Include the invalid value for analysis
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
            
            // Convert validation errors into abandoned fields if not already included
            lastErrors.forEach(function(error) {
                var fieldName = '';
                if (error.includes('PIN Code')) fieldName = 'billing_postcode';
                else if (error.includes('Street address')) fieldName = 'billing_address_1';
                else if (error.includes('email')) fieldName = 'billing_email';
                else if (error.includes('phone')) fieldName = 'billing_phone';
                
                if (fieldName && !abandonedFields.some(f => f.name === fieldName)) {
                    abandonedFields.push({
                        name: fieldName,
                        type: 'text',
                        error: error.trim()
                    });
                }
            });
            
            trackFriction('form_abandonment', {
                time_spent: (new Date() - formStartTime) / 1000,
                fields_filled: $('form.checkout input[value!=""]').length,
                abandoned_fields: abandonedFields,
                last_errors: lastErrors.map(error => error.trim())
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
    });    function getCurrentCheckoutStep() {
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