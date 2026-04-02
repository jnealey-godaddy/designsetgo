/**
 * Form Builder Block - Frontend JavaScript
 *
 * Handles AJAX form submission with validation
 *
 * @since 1.0.0
 */

/* global designsetgoForm, dsgoIntegrations */

// Track Turnstile script loading state
let turnstileScriptLoaded = false;
let turnstileScriptLoading = false;
const turnstileLoadCallbacks = [];

/**
 * Load Turnstile script dynamically
 *
 * @return {Promise} Resolves when script is loaded
 */
function loadTurnstileScript() {
	return new Promise((resolve, reject) => {
		// Already loaded
		if (turnstileScriptLoaded && window.turnstile) {
			resolve();
			return;
		}

		// Currently loading - queue callback
		if (turnstileScriptLoading) {
			turnstileLoadCallbacks.push({ resolve, reject });
			return;
		}

		turnstileScriptLoading = true;

		const script = document.createElement('script');
		script.src =
			'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
		script.async = true;
		script.defer = true;

		script.onload = () => {
			turnstileScriptLoaded = true;
			turnstileScriptLoading = false;
			resolve();
			// Resolve queued callbacks
			turnstileLoadCallbacks.forEach((cb) => cb.resolve());
			turnstileLoadCallbacks.length = 0;
		};

		script.onerror = () => {
			turnstileScriptLoading = false;
			const error = new Error('Failed to load Turnstile script');
			reject(error);
			// Reject queued callbacks
			turnstileLoadCallbacks.forEach((cb) => cb.reject(error));
			turnstileLoadCallbacks.length = 0;
		};

		document.head.appendChild(script);
	});
}

function initFormBuilder() {
	const forms = document.querySelectorAll('.dsgo-form-builder');

	forms.forEach((formContainer) => {
		// Guard against duplicate initialization (e.g. bfcache restore)
		if (formContainer.dataset.dsgoInitialized) {
			return;
		}
		formContainer.dataset.dsgoInitialized = 'true';

		const formElement = formContainer.querySelector('.dsgo-form');
		const submitButton = formElement?.querySelector('.dsgo-form__submit');
		const messageContainer = formElement?.querySelector(
			'.dsgo-form__message'
		);

		if (!formElement || !submitButton || !messageContainer) {
			return;
		}

		// Add timestamp field dynamically (not in save.js to avoid validation errors)
		const timestampField = document.createElement('input');
		timestampField.type = 'hidden';
		timestampField.name = 'dsg_timestamp';
		timestampField.value = Date.now();
		formElement.appendChild(timestampField);

		// Turnstile state for this form
		let turnstileToken = null;
		let turnstileWidgetId = null;

		// Check if Turnstile is enabled for this form
		const turnstileEnabled =
			formContainer.getAttribute('data-dsgo-turnstile') === 'true';
		const turnstileContainer = formElement.querySelector(
			'[data-dsgo-turnstile-container]'
		);
		const turnstileSiteKey =
			typeof dsgoIntegrations !== 'undefined'
				? dsgoIntegrations.turnstileSiteKey
				: null;

		// Initialize Turnstile if enabled
		if (turnstileEnabled && turnstileContainer && turnstileSiteKey) {
			loadTurnstileScript()
				.then(() => {
					if (!window.turnstile) {
						throw new Error('Turnstile not available');
					}

					turnstileWidgetId = window.turnstile.render(
						turnstileContainer,
						{
							sitekey: turnstileSiteKey,
							theme: 'auto',
							size: 'normal',
							// Mode (managed/non-interactive/invisible) is configured in Cloudflare dashboard
							callback: (token) => {
								turnstileToken = token;
							},
							'expired-callback': () => {
								turnstileToken = null;
								// Reset widget for new token
								if (
									turnstileWidgetId !== null &&
									window.turnstile
								) {
									window.turnstile.reset(turnstileWidgetId);
								}
							},
							'error-callback': () => {
								// Graceful degradation - form will submit without Turnstile token
								// eslint-disable-next-line no-console -- Log for debugging
								console.warn(
									'Turnstile widget error - form will submit without verification'
								);
							},
						}
					);
				})
				.catch((error) => {
					// Graceful degradation - form will submit without Turnstile token
					// eslint-disable-next-line no-console -- Log for debugging
					console.warn('Turnstile failed to load:', error.message);
				});
		}

		// Check if AJAX is enabled
		const ajaxEnabled =
			formContainer.getAttribute('data-ajax-submit') === 'true';

		// If AJAX is not enabled, use standard form submission
		if (!ajaxEnabled) {
			return;
		}

		// Get form settings from data attributes (only if AJAX enabled)
		const formId = formContainer.getAttribute('data-form-id');
		const successMessage = formContainer.getAttribute(
			'data-success-message'
		);
		const errorMessage = formContainer.getAttribute('data-error-message');
		const redirectUrl = formContainer.getAttribute('data-redirect-url');

		// Handle form submission
		formElement.addEventListener('submit', async function (e) {
			e.preventDefault();

			// Clear previous messages
			hideMessage(messageContainer);

			// Validate form using HTML5 validation
			if (!formElement.checkValidity()) {
				formElement.reportValidity();
				return;
			}

			// Disable submit button and show loading state
			submitButton.disabled = true;
			submitButton.classList.add('dsgo-form__submit--loading');
			const originalText = submitButton.textContent;
			submitButton.setAttribute('aria-busy', 'true');

			// Collect form data
			const formData = new FormData(formElement);
			const fields = [];

			for (const [name, value] of formData.entries()) {
				// Skip honeypot and system fields
				if (
					name === 'dsg_website' ||
					name === 'dsg_form_id' ||
					name === 'dsg_timestamp'
				) {
					continue;
				}

				const fieldElement = formElement.querySelector(
					`[name="${name}"]`
				);
				const fieldType =
					fieldElement?.getAttribute('data-field-type') ||
					fieldElement?.type ||
					'text';

				fields.push({
					name,
					value,
					type: fieldType,
				});
			}

			// Get honeypot and timestamp values
			const honeypot = formData.get('dsg_website');
			const timestamp = formData.get('dsg_timestamp');

			let redirecting = false;

			try {
				// Make AJAX request to WordPress REST API
				const response = await fetch(designsetgoForm.restUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': designsetgoForm.nonce,
					},
					body: JSON.stringify({
						formId,
						fields,
						honeypot: honeypot || '',
						timestamp: timestamp || Date.now(),
						// Include Turnstile token if available (graceful degradation: empty if failed)
						turnstile_token: turnstileToken || '',
					}),
				});

				const result = await response.json();

				if (result.success) {
					// Fire custom event for tracking/analytics
					formContainer.dispatchEvent(
						new CustomEvent('dsgoFormSubmitted', {
							detail: {
								formId,
								submissionId: result.submissionId,
							},
							bubbles: true,
						})
					);

					// Redirect if URL is configured and safe
					if (redirectUrl && isSafeRedirectUrl(redirectUrl)) {
						redirecting = true;
						window.location.href = redirectUrl;
						return;
					}

					// Show success message
					showMessage(
						messageContainer,
						successMessage || result.message,
						'success'
					);

					// Reset form
					formElement.reset();

					// Scroll to message if not visible
					if (!isElementInViewport(messageContainer)) {
						messageContainer.scrollIntoView({
							behavior: 'smooth',
							block: 'nearest',
						});
					}
				} else {
					throw new Error(result.message || errorMessage);
				}
			} catch (error) {
				// eslint-disable-next-line no-console -- Error logging for debugging
				console.error('Form submission error:', error);

				// Show error message
				showMessage(
					messageContainer,
					error.message ||
						errorMessage ||
						'An error occurred. Please try again.',
					'error'
				);

				// Fire custom event for error tracking
				formContainer.dispatchEvent(
					new CustomEvent('dsgoFormError', {
						detail: {
							formId,
							error: error.message,
						},
						bubbles: true,
					})
				);
			} finally {
				// Skip button reset if navigating away (redirect)
				if (!redirecting) {
					submitButton.disabled = false;
					submitButton.classList.remove('dsgo-form__submit--loading');
					submitButton.textContent = originalText;
					submitButton.removeAttribute('aria-busy');
				}
			}
		});
	});

	/**
	 * Show message to user
	 *
	 * @param {HTMLElement} container Message container element
	 * @param {string}      message   Message text to display
	 * @param {string}      type      Message type: 'success' or 'error'
	 */
	function showMessage(container, message, type) {
		container.textContent = message;
		container.className = `dsgo-form__message dsgo-form__message--${type}`;
		container.style.display = 'block';
		container.setAttribute('role', type === 'error' ? 'alert' : 'status');

		// Announce to screen readers
		const announcement = document.createElement('span');
		announcement.className = 'screen-reader-text';
		announcement.textContent = message;
		announcement.setAttribute('aria-live', 'polite');
		container.appendChild(announcement);
	}

	/**
	 * Hide message container
	 *
	 * @param {HTMLElement} container Message container element
	 */
	function hideMessage(container) {
		container.style.display = 'none';
		container.textContent = '';
		container.className = 'dsgo-form__message';
	}

	/**
	 * Validate that a redirect URL is safe (not javascript: or data: protocol)
	 *
	 * @param {string} url URL to validate
	 * @return {boolean} True if safe to redirect to
	 */
	function isSafeRedirectUrl(url) {
		try {
			const parsed = new URL(url, window.location.origin);
			return parsed.protocol === 'http:' || parsed.protocol === 'https:';
		} catch {
			return false;
		}
	}

	/**
	 * Check if element is in viewport
	 *
	 * @param {HTMLElement} element Element to check
	 * @return {boolean} True if element is in viewport
	 */
	function isElementInViewport(element) {
		const rect = element.getBoundingClientRect();
		return (
			rect.top >= 0 &&
			rect.left >= 0 &&
			rect.bottom <=
				(window.innerHeight || document.documentElement.clientHeight) &&
			rect.right <=
				(window.innerWidth || document.documentElement.clientWidth)
		);
	}
}

document.addEventListener('DOMContentLoaded', initFormBuilder);
document.addEventListener('dsgo-content-loaded', initFormBuilder);
