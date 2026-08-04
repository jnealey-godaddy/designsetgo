/**
 * Form Builder Block - Frontend JavaScript
 *
 * Handles AJAX form submission with validation
 *
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';

/* global designsetgoForm, dsgoIntegrations, sessionStorage */

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
		// Set at init time so server can verify user spent >= 3s on page (anti-spam).
		// Re-set on bfcache restore so stale timestamps don't cause false rejections.
		const timestampField = document.createElement('input');
		timestampField.type = 'hidden';
		timestampField.name = 'dsg_timestamp';
		timestampField.value = Date.now();
		formElement.appendChild(timestampField);

		window.addEventListener('pageshow', function (e) {
			if (e.persisted) {
				timestampField.value = Date.now();
			}
		});

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
		let turnstileTokenField = null;

		if (turnstileEnabled) {
			turnstileTokenField = document.createElement('input');
			turnstileTokenField.type = 'hidden';
			turnstileTokenField.name = 'dsg_turnstile_token';
			formElement.appendChild(turnstileTokenField);
		}

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
								if (turnstileTokenField) {
									turnstileTokenField.value = token;
								}
							},
							'expired-callback': () => {
								turnstileToken = null;
								if (turnstileTokenField) {
									turnstileTokenField.value = '';
								}
								// Reset widget for new token
								if (
									turnstileWidgetId !== null &&
									window.turnstile
								) {
									window.turnstile.reset(turnstileWidgetId);
								}
							},
							'error-callback': () => {
								turnstileToken = null;
								if (turnstileTokenField) {
									turnstileTokenField.value = '';
								}
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
					turnstileToken = null;
					if (turnstileTokenField) {
						turnstileTokenField.value = '';
					}
					// Graceful degradation - form will submit without Turnstile token
					// eslint-disable-next-line no-console -- Log for debugging
					console.warn('Turnstile failed to load:', error.message);
				});
		}

		const formId = formContainer.getAttribute('data-form-id');
		const storageKey = (() => {
			if (formId) {
				return `dsgo_confirmed_${formId}`;
			}
			if (formContainer.id) {
				return `dsgo_confirmed_instance_${formContainer.id.replace(/[^a-zA-Z0-9_-]/g, '_')}`;
			}
			const idx = Array.from(
				document.querySelectorAll('[data-form-id]')
			).indexOf(formContainer);
			return `dsgo_confirmed_instance_${idx >= 0 ? idx : 0}`;
		})();
		const successMessage = formContainer.getAttribute(
			'data-success-message'
		);
		const errorMessage = formContainer.getAttribute('data-error-message');

		function ensureNativePostFields() {
			formElement.action = designsetgoForm.adminPostUrl;
			formElement.method = 'post';

			let actionField = formElement.querySelector('input[name="action"]');
			if (!actionField) {
				actionField = document.createElement('input');
				actionField.type = 'hidden';
				actionField.name = 'action';
				formElement.appendChild(actionField);
			}
			actionField.value = 'designsetgo_form_submit';
			actionField.defaultValue = actionField.value;

			let nonceField = formElement.querySelector(
				'input[name="_wpnonce"]'
			);
			if (!nonceField) {
				nonceField = document.createElement('input');
				nonceField.type = 'hidden';
				nonceField.name = '_wpnonce';
				formElement.appendChild(nonceField);
			}
			nonceField.value = designsetgoForm.ajaxNonce;
			nonceField.defaultValue = nonceField.value;
		}

		function getStoredConfirmation() {
			try {
				return sessionStorage.getItem(storageKey);
			} catch {
				return null;
			}
		}

		function storeConfirmation(message) {
			try {
				sessionStorage.setItem(storageKey, message);
			} catch {
				// sessionStorage unavailable
			}
		}

		function clearConfirmation() {
			try {
				sessionStorage.removeItem(storageKey);
			} catch {
				// sessionStorage unavailable
			}
		}

		function showRedirectStatus() {
			const params = new URLSearchParams(window.location.search);
			const status = params.get('dsgo_form_status');
			const statusFormId = params.get('dsgo_form_id');
			const matchesCurrentForm = !statusFormId || statusFormId === formId;
			let shown = false;

			if (
				matchesCurrentForm &&
				(params.has('dsgo_form_success') || status === 'success')
			) {
				const msg =
					successMessage ||
					__('Form submitted successfully!', 'designsetgo');
				showMessage(messageContainer, msg, 'success');
				storeConfirmation(msg);
				formElement.reset();
				shown = true;
			} else if (
				matchesCurrentForm &&
				(params.has('dsgo_form_error') || status === 'error')
			) {
				clearConfirmation();
				showMessage(
					messageContainer,
					errorMessage ||
						__(
							'An error occurred. Please try again.',
							'designsetgo'
						),
					'error'
				);
				shown = true;
			}

			// Strip form status params from URL to prevent stale messages on refresh
			if (shown) {
				const cleanUrl = new URL(window.location.href);
				cleanUrl.searchParams.delete('dsgo_form_success');
				cleanUrl.searchParams.delete('dsgo_form_error');
				cleanUrl.searchParams.delete('dsgo_form_status');
				cleanUrl.searchParams.delete('dsgo_form_id');
				window.history.replaceState(null, '', cleanUrl.href);
			}

			return shown;
		}

		function markTransportBlocked(key) {
			try {
				sessionStorage.setItem(key, '1');
			} catch {
				// sessionStorage may be unavailable
			}
		}

		function isTransportBlocked(key) {
			try {
				return sessionStorage.getItem(key) === '1';
			} catch {
				return false;
			}
		}

		function submitViaNativePost() {
			ensureNativePostFields();
			if (turnstileTokenField) {
				turnstileTokenField.value = turnstileToken || '';
			}

			// Build field type map so server can validate/sanitize correctly
			const typeMap = {};
			formElement.querySelectorAll('[data-field-type]').forEach((el) => {
				if (el.name) {
					typeMap[el.name] = el.getAttribute('data-field-type');
				}
			});
			let typeMapField = formElement.querySelector(
				'input[name="dsg_field_types"]'
			);
			if (!typeMapField) {
				typeMapField = document.createElement('input');
				typeMapField.type = 'hidden';
				typeMapField.name = 'dsg_field_types';
				formElement.appendChild(typeMapField);
			}
			typeMapField.value = JSON.stringify(typeMap);

			window.HTMLFormElement.prototype.submit.call(formElement);
		}

		const shownFromRedirect = showRedirectStatus();

		// Restore confirmation message persisted from a previous submission (one-time)
		if (!shownFromRedirect) {
			const storedConfirmation = getStoredConfirmation();
			if (storedConfirmation) {
				showMessage(messageContainer, storedConfirmation, 'success');
				clearConfirmation();
			}
		}

		// Check if AJAX is enabled
		const ajaxEnabled =
			formContainer.getAttribute('data-ajax-submit') === 'true';

		// Non-AJAX: set up standard form POST to admin_post
		if (!ajaxEnabled) {
			ensureNativePostFields();

			return;
		}

		const safeRedirectUrl = getSafeRedirectUrl(
			formContainer.getAttribute('data-redirect-url')
		);

		// Handle form submission
		formElement.addEventListener('submit', async function (e) {
			e.preventDefault();

			// Clear previous messages and any persisted confirmation state
			hideMessage(messageContainer);
			clearConfirmation();

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
					name === 'dsg_timestamp' ||
					name === 'dsg_turnstile_token'
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
				const requestBody = JSON.stringify({
					formId,
					fields,
					honeypot: honeypot || '',
					timestamp: timestamp || Date.now(),
					// Include Turnstile token if available (graceful degradation: empty if failed)
					turnstile_token: turnstileToken || '',
				});

				let result;

				// Submit via admin-ajax (form-encoded) or REST API.
				// Some hosts (GoDaddy/Cloudflare) block JSON POSTs entirely,
				// so we use admin-ajax as primary when available, with REST
				// as fallback. sessionStorage remembers if REST failed before
				// to avoid wasting the rate limit window on a doomed request.
				const useAjax =
					designsetgoForm.ajaxUrl && designsetgoForm.ajaxNonce;
				const restBlocked =
					useAjax && isTransportBlocked('dsgo_rest_blocked');
				const ajaxBlocked =
					useAjax && isTransportBlocked('dsgo_ajax_blocked');

				if (ajaxBlocked) {
					redirecting = true;
					submitViaNativePost();
					return;
				}

				if (!restBlocked) {
					// Try REST API first
					let restResponse = await fetch(designsetgoForm.restUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': designsetgoForm.nonce,
						},
						body: requestBody,
					});

					// A full-page cache can serve markup that outlives the
					// nonce baked into it — nonces last ~24h, and cache TTLs
					// routinely exceed that. handle_form_submission() only
					// verifies a nonce that is *present*, so a stale one is
					// rejected where an absent one is accepted. Retry once
					// without it rather than stranding the visitor; the
					// endpoint is public by design (anonymous visitors never
					// have a nonce), so this concedes nothing an attacker
					// couldn't already do by submitting anonymously.
					if (restResponse.status === 403) {
						let staleNonce = false;
						try {
							const errorData = await restResponse.clone().json();
							// `rest_cookie_invalid_nonce` is the one that fires
							// in practice: core's rest_cookie_check_errors()
							// rejects a bad X-WP-Nonce during authentication,
							// before handle_form_submission() is ever reached.
							// The plugin's own `invalid_nonce` is kept for the
							// case where core lets the request through.
							staleNonce =
								errorData.code ===
									'rest_cookie_invalid_nonce' ||
								errorData.code === 'invalid_nonce';
						} catch {
							// Body isn't valid JSON — not our nonce error.
						}

						if (staleNonce) {
							restResponse = await fetch(
								designsetgoForm.restUrl,
								{
									method: 'POST',
									headers: {
										'Content-Type': 'application/json',
									},
									body: requestBody,
								}
							);
						}
					}

					if (restResponse.ok) {
						result = await restResponse.json();
					} else if (restResponse.status === 429 && useAjax) {
						// Remember that REST is blocked for this session
						markTransportBlocked('dsgo_rest_blocked');
						// Fall through to admin-ajax below
					} else {
						// Non-429 error from REST API
						let serverMessage;
						try {
							const errorData = await restResponse.json();
							serverMessage = errorData.message;
						} catch {
							// Response body isn't valid JSON
						}

						throw new Error(
							serverMessage ||
								__(
									'The server returned an unexpected response. Please try again later.',
									'designsetgo'
								)
						);
					}
				}

				// Admin-ajax fallback (or primary if REST was blocked)
				if (!result && useAjax) {
					const ajaxBody = new URLSearchParams();
					ajaxBody.set('action', 'designsetgo_form_submit');
					ajaxBody.set('_ajax_nonce', designsetgoForm.ajaxNonce);
					ajaxBody.set('form_data', requestBody);

					const ajaxResponse = await fetch(designsetgoForm.ajaxUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
						},
						body: ajaxBody.toString(),
					});

					if (!ajaxResponse.ok) {
						if (ajaxResponse.status === 429) {
							markTransportBlocked('dsgo_ajax_blocked');
							redirecting = true;
							submitViaNativePost();
							return;
						}

						let ajaxMessage;
						try {
							const ajaxError = await ajaxResponse.json();
							ajaxMessage =
								ajaxError.data?.message || ajaxError.message;
						} catch {
							// Non-JSON response
						}

						throw new Error(
							ajaxMessage ||
								__(
									'The server returned an unexpected response. Please try again later.',
									'designsetgo'
								)
						);
					}

					const ajaxResult = await ajaxResponse.json();
					// wp_send_json_success wraps in { success, data }
					result = ajaxResult.data;
				}

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

					// Redirect using the normalized safe URL instead of the raw
					// DOM attribute value so the browser never reparses unsanitized text.
					if (safeRedirectUrl) {
						redirecting = true;
						window.location.assign(safeRedirectUrl);
						return;
					}

					// Show success message and persist for reload
					const successMsg = successMessage || result.message;
					showMessage(messageContainer, successMsg, 'success');
					storeConfirmation(successMsg);

					// Reset form
					formElement.reset();
					turnstileToken = null;
					if (turnstileTokenField) {
						turnstileTokenField.value = '';
					}
					if (turnstileWidgetId !== null && window.turnstile) {
						window.turnstile.reset(turnstileWidgetId);
					}

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
	 * Return a canonical redirect URL when it uses an allowed protocol.
	 *
	 * @param {string|null} url URL to validate
	 * @return {string|null} Canonical URL or null when unsafe
	 */
	function getSafeRedirectUrl(url) {
		if (!url) {
			return null;
		}

		try {
			const parsed = new URL(url, window.location.origin);
			return parsed.protocol === 'http:' || parsed.protocol === 'https:'
				? parsed.toString()
				: null;
		} catch {
			return null;
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
