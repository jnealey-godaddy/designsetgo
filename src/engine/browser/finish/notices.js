/**
 * Notice-text and notice-action builders shared by `finish-build.js` and
 * `review.js`: failure-reason text (U5), the "View details" action, the
 * singular/plural "with N issue(s)" suffix used by both the draft-save and
 * review-save success notices (U4), and the shared "unexpected failure"
 * reason text a report's `invalid` entry should carry whenever the notice
 * shown is the generic catch-all one (see `UNEXPECTED_FAILURE_REASON`
 * below).
 *
 * Split out of `./apply.js` (which stayed close to the 300-line cap) so
 * pure notice-text logic and pure tree-assembly/timing logic each have
 * their own file; none of this imports `@wordpress/data` or any WordPress
 * store, matching `./apply.js`'s reasoning for staying unit-testable with
 * plain fakes.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	AGENT_BUILD_SIDEBAR_IDENTIFIER,
	COMPLEMENTARY_AREA_SCOPE,
	COMPLEMENTARY_AREA_STORE,
} from '../constants';

/** Reasons longer than this are trimmed before they reach a notice. */
const MAX_REASON_LENGTH = 140;

/**
 * Builds the reason text for a "could not be applied" notice: the first
 * `invalid` entry's `reason`, trimmed to `MAX_REASON_LENGTH`, plus how many
 * more entries there are when there's more than one — see U5's "Failure and
 * conflict notices give no reason".
 *
 * @param {Array<{reason?: string}>} [invalid] Mapped `invalid` entries.
 * @return {string} The reason text, or `''` when there is nothing to report.
 */
export function describeInvalid(invalid = []) {
	if (!invalid.length) {
		return '';
	}

	const [first, ...rest] = invalid;
	const reason = (first.reason || '').trim();
	const trimmed =
		reason.length > MAX_REASON_LENGTH
			? `${reason.slice(0, MAX_REASON_LENGTH).trimEnd()}…`
			: reason;

	if (!rest.length || !trimmed) {
		return trimmed;
	}

	return sprintf(
		/* translators: 1: first failure reason (already trimmed); 2: how many more failures there are. */
		__('%1$s and %2$d more', 'designsetgo'),
		trimmed,
		rest.length
	);
}

/**
 * A "View details" notice action that opens the Agent build sidebar.
 *
 * @param {Function} openSidebar `() => void` — opens the Agent build sidebar.
 * @return {{label: string, onClick: Function}} The action.
 */
export function viewDetailsAction(openSidebar) {
	return { label: __('View details', 'designsetgo'), onClick: openSidebar };
}

/**
 * @param {Array<{reason?: string}>} invalid Mapped `invalid` entries.
 * @return {string} "The agent build could not be applied[: <reason>]."
 */
export function failedMessage(invalid) {
	const reason = describeInvalid(invalid);
	return reason
		? sprintf(
				/* translators: %s: why the agent build failed, trimmed to ~140 characters. */
				__('The agent build could not be applied: %s', 'designsetgo'),
				reason
			)
		: __('The agent build could not be applied.', 'designsetgo');
}

/**
 * Appends how many lint issues a build has to an already-translated base
 * sentence, replacing its trailing period — shared by review.js's "Agent
 * changes saved..." and finish-build.js's "Agent build applied and
 * saved..." success notices so the singular/plural handling exists in one
 * place (previously duplicated as `savedMessage()`/`savedWithIssuesMessage()`).
 *
 * @param {string} baseSentence Already-translated sentence ending in "." with no issue count, e.g. "Agent build applied and saved.".
 * @param {Array}  findings     Lint findings, already mapped to the REST shape.
 * @return {string} `baseSentence` unchanged, or with its period replaced by " with N issue(s).".
 */
export function withIssuesCount(baseSentence, findings) {
	if (!findings.length) {
		return baseSentence;
	}
	return sprintf(
		/* translators: 1: the base sentence without its trailing period, e.g. "Agent build applied and saved"; 2: how many lint issues, e.g. "3 issues". */
		__('%1$s with %2$s.', 'designsetgo'),
		baseSentence.replace(/\.$/, ''),
		sprintf(
			/* translators: %d: number of lint issues. */
			_n('%d issue', '%d issues', findings.length, 'designsetgo'),
			findings.length
		)
	);
}

/**
 * Reason text for a report's `invalid` entry whenever the visible notice is
 * the generic catch-all one below — kept as a single exported string so the
 * two never drift apart (the whole point is that the stored report's
 * reason always matches what the notice says).
 */
export const UNEXPECTED_FAILURE_REASON = __(
	'Could not check for a pending agent build.',
	'designsetgo'
);

/**
 * Builds the `openSidebar` dep from a real `@wordpress/data` `dispatch`:
 * opens the Agent build sidebar via `core/interface`'s
 * `enableComplementaryArea`. `core/interface` is registered by `wp-editor`
 * itself — WordPress core bundles that store's registration inside the
 * editor script rather than shipping a standalone `wp-interface` script
 * (see `../constants.js`) — so it is present as soon as `wp-editor`, an
 * existing script dependency of this bundle, has loaded. Guarded anyway so
 * a "View details" click can never throw if that ever changes.
 *
 * @param {Function} dispatch `@wordpress/data` `dispatch`.
 * @return {Function} `() => void`.
 */
export function makeOpenSidebar(dispatch) {
	return () => {
		const store = dispatch(COMPLEMENTARY_AREA_STORE);
		if (typeof store?.enableComplementaryArea !== 'function') {
			return;
		}
		store.enableComplementaryArea(
			COMPLEMENTARY_AREA_SCOPE,
			AGENT_BUILD_SIDEBAR_IDENTIFIER
		);
	};
}
