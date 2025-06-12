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
    $('form.checkout').on('change', 'input, select, textarea', function() {
        const field = $(this);
        const fieldName = field.attr('name');
        const fieldType = field.attr('type');
        const fieldValue = field.val();

        trackFrictionPoint('field_interaction', {
            field_name: fieldName,
            field_type: fieldType,
            field_value: fieldValue
        });
    });

    // Track form validation errors
    $('form.checkout').on('checkout_error', function(event, error_message) {
        trackFrictionPoint('validation_error', {
            error_message: error_message
        });
    });

    // Track checkout step changes
    let currentStep = 'cart';
    $('body').on('updated_checkout', function() {
        const newStep = getCurrentCheckoutStep();
        if (newStep !== currentStep) {
            trackFrictionPoint('step_change', {
                from_step: currentStep,
                to_step: newStep
            });
            currentStep = newStep;
        }
    });

    // Track abandonment
    $(window).on('beforeunload', function() {
        if (isCheckoutPage() && !isOrderComplete()) {
            trackFrictionPoint('abandonment', {
                last_step: currentStep
            });
        }
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