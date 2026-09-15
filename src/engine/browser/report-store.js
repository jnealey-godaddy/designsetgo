/**
 * Small `@wordpress/data` store the finish flow and the Agent build sidebar
 * both read: the finish flow (`./finish/index.js`) writes the last agent
 * build report for the current post there, and the panel
 * (`./panel/BuildReportSection.js`) reads it to show "Pending agent
 * build"/"Last agent build" details without requiring a fresh Check. A real
 * registered `@wordpress/data` store, not a module-level global, so it
 * behaves correctly across re-renders and stays consistent with how every
 * other cross-module read in this codebase works.
 *
 * Import this module only for its registration side effect (from
 * `./index.js`, before `./panel` and `./finish`); everywhere else, reach the
 * store by its string name (`AGENT_BUILD_REPORT_STORE` in `./constants.js`)
 * through `@wordpress/data`'s `select`/`dispatch`/`useSelect` — this keeps
 * `AgentBuildPanel.js` and `finish/index.js` free of a hard import on a
 * module that calls `register()` at load time, which matters for
 * `AgentBuildPanel.test.js`, where `@wordpress/data` itself is stubbed.
 */
import { createReduxStore, register } from '@wordpress/data';
import { AGENT_BUILD_REPORT_STORE } from './constants';

const DEFAULT_STATE = { report: null };

/**
 * @param {Object} state  Current state.
 * @param {Object} action Dispatched action.
 * @return {Object} Next state.
 */
export function reducer(state = DEFAULT_STATE, action) {
	if (action.type === 'SET_REPORT') {
		return { ...state, report: action.report || null };
	}
	return state;
}

export const actions = {
	/**
	 * @param {Object|null} report `{ postId, status, invalid?, findings? }`, or `null` to clear.
	 * @return {Object} Redux action.
	 */
	setReport(report) {
		return { type: 'SET_REPORT', report: report || null };
	},
};

export const selectors = {
	/**
	 * @param {Object} state Store state.
	 * @return {Object|null} The last report set, or `null` if none / cleared.
	 */
	getReport(state) {
		return state.report;
	},
};

register(
	createReduxStore(AGENT_BUILD_REPORT_STORE, { reducer, actions, selectors })
);
