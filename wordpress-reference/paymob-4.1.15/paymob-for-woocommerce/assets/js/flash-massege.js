jQuery(document).ready(function ($) {
	function getQueryParam(paramName) {
		const urlParams = new URLSearchParams(window.location.search);
		return urlParams.get(paramName);
	}

	const errorMessage = getQueryParam('gatewayerror');
	if (errorMessage) {
		displayWooCommerceError(decodeURIComponent(errorMessage.replace(/\+/g, ' ')));
		// Pixel remount is owned by paymob-pixel_block.js (once only).
	}
});

function displayPaymobNotice(message, type = 'error', options = {}) {
	const settings = {
		title:
			options.title ||
			(type === 'error' ? 'Payment could not be completed' : 'Notice'),
		autoHideMs:
			typeof options.autoHideMs === 'number' ? options.autoHideMs : 8000,
	};

	const containerSelector = '.paymob-notice-stack';
	let noticeStack = jQuery(containerSelector);

	if (!noticeStack.length) {
		noticeStack = jQuery(
			'<div class="paymob-notice-stack" aria-live="polite" aria-atomic="true"></div>'
		);
		jQuery('body').append(noticeStack);
	}

	const notice = jQuery('<div class="paymob-modern-notice" role="alert"></div>');
	notice.addClass(type === 'error' ? 'is-error' : 'is-info');

	const icon = jQuery('<span class="paymob-modern-notice__icon" aria-hidden="true"></span>');
	icon.text(type === 'error' ? '!' : 'i');

	const body = jQuery('<div class="paymob-modern-notice__body"></div>');
	body.append(jQuery('<h4 class="paymob-modern-notice__title"></h4>').text(settings.title));
	body.append(
		jQuery('<p class="paymob-modern-notice__message"></p>').text(
			message || 'Unexpected error occurred.'
		)
	);

	const closeBtn = jQuery(
		'<button type="button" class="paymob-modern-notice__close" aria-label="Close notice"></button>'
	);
	closeBtn.text('×');

	notice.append(icon, body, closeBtn);
	noticeStack.append(notice);

	requestAnimationFrame(function () {
		notice.addClass('is-visible');
	});

	const closeNotice = function () {
		notice.removeClass('is-visible');
		setTimeout(function () {
			notice.remove();
			if (!noticeStack.children().length) {
				noticeStack.remove();
			}
		}, 220);
	};

	closeBtn.on('click', closeNotice);

	if (settings.autoHideMs > 0) {
		setTimeout(closeNotice, settings.autoHideMs);
	}

	if (type === 'error') {
		logPaymobError(message, options.context || 'checkout');
	}
}

function displayWooCommerceError(message) {
	displayPaymobNotice(message, 'error');
}

function logPaymobError(message, context) {
	if (!window.paymobFlashLogger || !window.paymobFlashLogger.ajaxUrl || !window.paymobFlashLogger.nonce) {
		return;
	}

	jQuery.ajax({
		url: window.paymobFlashLogger.ajaxUrl,
		method: 'POST',
		data: {
			action: 'paymob_log_error',
			nonce: window.paymobFlashLogger.nonce,
			message: message,
			context: context || 'frontend',
		},
	});
}

document.addEventListener('DOMContentLoaded', function () {
	const selected = document.querySelector(
		'input[name="radio-control-wc-payment-method-options"]:checked'
	);

	if (!selected) {
		const fallback = document.querySelector(
			'input[name="radio-control-wc-payment-method-options"][value="paymob-subscription"]'
		);
		if (fallback) {
			fallback.click();
		}
	}
});
