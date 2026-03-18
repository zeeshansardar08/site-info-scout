/**
 * Site Info Scout - Admin clipboard copy handler.
 *
 * All functions and variables are prefixed with zigsiteinfoscout to avoid
 * collisions with other admin scripts.
 *
 * Depends on: zigsiteinfoscoutData (object injected via wp_localize_script)
 *   - zigsiteinfoscoutData.report          {string} Full plain-text report.
 *   - zigsiteinfoscoutData.supportSummary  {string} Smart support summary.
 *   - zigsiteinfoscoutData.i18n.copied          {string} "Copied" feedback text.
 *   - zigsiteinfoscoutData.i18n.copyFailed      {string} "Failed" feedback text.
 *   - zigsiteinfoscoutData.i18n.summaryCopied   {string} "Summary copied" text.
 *   - zigsiteinfoscoutData.i18n.summaryCopyFail {string} "Summary failed" text.
 */

/* global zigsiteinfoscoutData */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		// Full report copy button.
		var btn = document.getElementById( 'zigsiteinfoscout-copy-btn' );
		if ( btn ) {
			btn.addEventListener( 'click', function () {
				var report =
					( 'object' === typeof zigsiteinfoscoutData && zigsiteinfoscoutData.report )
						? zigsiteinfoscoutData.report
						: '';
				zigsiteinfoscoutCopyText( report, 'zigsiteinfoscout-copy-feedback', false );
			} );
		}

		// Support summary copy button.
		var summaryBtn = document.getElementById( 'zigsiteinfoscout-summary-btn' );
		if ( summaryBtn ) {
			summaryBtn.addEventListener( 'click', function () {
				var summary =
					( 'object' === typeof zigsiteinfoscoutData && zigsiteinfoscoutData.supportSummary )
						? zigsiteinfoscoutData.supportSummary
						: '';
				zigsiteinfoscoutCopyText( summary, 'zigsiteinfoscout-summary-feedback', true );
			} );
		}
	} );

	/**
	 * Copies the given text to the clipboard and shows feedback.
	 *
	 * @param {string}  text       The text to copy.
	 * @param {string}  feedbackId ID of the feedback element to update.
	 * @param {boolean} isSummary  Whether this is the support summary copy.
	 */
	function zigsiteinfoscoutCopyText( text, feedbackId, isSummary ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard
				.writeText( text )
				.then( function () {
					zigsiteinfoscoutShowCopyFeedback( true, feedbackId, isSummary );
				} )
				.catch( function () {
					zigsiteinfoscoutFallbackCopy( text, feedbackId, isSummary );
				} );
		} else {
			zigsiteinfoscoutFallbackCopy( text, feedbackId, isSummary );
		}
	}

	/**
	 * Uses a temporary off-screen textarea and execCommand('copy') as a
	 * fallback for HTTP or browsers without Clipboard API support.
	 *
	 * @param {string}  text       The text to copy.
	 * @param {string}  feedbackId ID of the feedback element to update.
	 * @param {boolean} isSummary  Whether this is the support summary copy.
	 */
	function zigsiteinfoscoutFallbackCopy( text, feedbackId, isSummary ) {
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
		zigsiteinfoscoutShowCopyFeedback( success, feedbackId, isSummary );
	}

	/**
	 * Updates the feedback element with a success or failure message,
	 * then clears it after 4 seconds.
	 *
	 * The element uses aria-live="polite" so screen readers announce the result.
	 *
	 * @param {boolean} success    Whether the copy succeeded.
	 * @param {string}  feedbackId ID of the feedback element to update.
	 * @param {boolean} isSummary  Whether this is the support summary copy.
	 */
	function zigsiteinfoscoutShowCopyFeedback( success, feedbackId, isSummary ) {
		var feedback = document.getElementById( feedbackId );
		if ( ! feedback ) {
			return;
		}

		var i18n = ( 'object' === typeof zigsiteinfoscoutData && zigsiteinfoscoutData.i18n )
			? zigsiteinfoscoutData.i18n
			: {};

		var copied, failed;
		if ( isSummary ) {
			copied = i18n.summaryCopied   || 'Support summary copied to clipboard!';
			failed = i18n.summaryCopyFail || 'Copy failed. Please try again.';
		} else {
			copied = i18n.copied     || 'Report copied to clipboard!';
			failed = i18n.copyFailed || 'Copy failed. Please use the Download TXT button instead.';
		}

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
