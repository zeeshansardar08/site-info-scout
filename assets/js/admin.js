/**
 * Site Info Scout — Admin clipboard copy handler.
 *
 * All functions and variables are prefixed with zigsiteinfoscout to avoid
 * collisions with other admin scripts.
 *
 * Depends on: zigsiteinfoscoutData (object injected via wp_localize_script)
 *   - zigsiteinfoscoutData.report     {string} Full plain-text report.
 *   - zigsiteinfoscoutData.i18n.copied     {string} "Copied" feedback text.
 *   - zigsiteinfoscoutData.i18n.copyFailed {string} "Failed" feedback text.
 */

/* global zigsiteinfoscoutData */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var btn = document.getElementById( 'zigsiteinfoscout-copy-btn' );
		if ( ! btn ) {
			return;
		}

		btn.addEventListener( 'click', function () {
			var report =
				( 'object' === typeof zigsiteinfoscoutData && zigsiteinfoscoutData.report )
					? zigsiteinfoscoutData.report
					: '';

			zigsiteinfoscoutCopyReport( report );
		} );
	} );

	/**
	 * Copies the given text to the clipboard.
	 *
	 * Uses the modern Clipboard API when available in a secure context (HTTPS),
	 * with a textarea-based execCommand fallback for HTTP environments or
	 * older browsers.
	 *
	 * @param {string} text The text to copy.
	 */
	function zigsiteinfoscoutCopyReport( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard
				.writeText( text )
				.then( function () {
					zigsiteinfoscoutShowCopyFeedback( true );
				} )
				.catch( function () {
					// Clipboard API rejected — attempt execCommand fallback.
					zigsiteinfoscoutFallbackCopy( text );
				} );
		} else {
			zigsiteinfoscoutFallbackCopy( text );
		}
	}

	/**
	 * Uses a temporary off-screen textarea and execCommand('copy') as a
	 * fallback for HTTP or browsers without Clipboard API support.
	 *
	 * @param {string} text The text to copy.
	 */
	function zigsiteinfoscoutFallbackCopy( text ) {
		var ta = document.createElement( 'textarea' );
		ta.value = text;

		// Position off-screen without display:none so it can receive focus.
		ta.style.position   = 'fixed';
		ta.style.top        = '0';
		ta.style.left       = '0';
		ta.style.width      = '2em';
		ta.style.height     = '2em';
		ta.style.padding    = '0';
		ta.style.border     = 'none';
		ta.style.outline    = 'none';
		ta.style.boxShadow  = 'none';
		ta.style.background = 'transparent';
		ta.style.opacity    = '0';

		document.body.appendChild( ta );
		ta.focus();
		ta.select();

		var success = false;
		try {
			success = document.execCommand( 'copy' );
		} catch ( err ) {
			success = false;
		}

		document.body.removeChild( ta );
		zigsiteinfoscoutShowCopyFeedback( success );
	}

	/**
	 * Updates the feedback element with a success or failure message,
	 * then clears it after 4 seconds.
	 *
	 * The element uses aria-live="polite" so screen readers announce the result.
	 *
	 * @param {boolean} success Whether the copy succeeded.
	 */
	function zigsiteinfoscoutShowCopyFeedback( success ) {
		var feedback = document.getElementById( 'zigsiteinfoscout-copy-feedback' );
		if ( ! feedback ) {
			return;
		}

		var i18n     = ( 'object' === typeof zigsiteinfoscoutData && zigsiteinfoscoutData.i18n )
			? zigsiteinfoscoutData.i18n
			: {};
		var copied   = i18n.copied     || 'Report copied to clipboard!';
		var failed   = i18n.copyFailed || 'Copy failed. Please use the Download TXT button instead.';

		if ( success ) {
			feedback.textContent = copied;
			feedback.className   = 'zigsiteinfoscout-copy-feedback zigsiteinfoscout-copy-feedback--success';
		} else {
			feedback.textContent = failed;
			feedback.className   = 'zigsiteinfoscout-copy-feedback zigsiteinfoscout-copy-feedback--error';
		}

		// Auto-clear after 4 seconds.
		setTimeout( function () {
			feedback.textContent = '';
			feedback.className   = 'zigsiteinfoscout-copy-feedback';
		}, 4000 );
	}
}() );
