/**
 * The `designsetgo/agent-build` report store: registration, and the
 * reducer/selector pair the finish flow and the Agent build sidebar share.
 */
import { select, dispatch } from '@wordpress/data';
import { AGENT_BUILD_REPORT_STORE } from '../constants';
// Imported both for the store's `reducer` (unit-tested directly below) and
// for its registration side effect (the real store, exercised via
// select()/dispatch() below).
import { reducer } from '../report-store';

describe('reducer()', () => {
	test('defaults to a null report', () => {
		expect(reducer(undefined, { type: '@@INIT' })).toEqual({
			report: null,
		});
	});

	test('SET_REPORT stores the given report', () => {
		const report = {
			postId: 4,
			status: 'failed',
			invalid: [],
			findings: [],
		};
		expect(reducer(undefined, { type: 'SET_REPORT', report })).toEqual({
			report,
		});
	});

	test('SET_REPORT with no report normalizes to null', () => {
		expect(reducer(undefined, { type: 'SET_REPORT' })).toEqual({
			report: null,
		});
	});

	test('an unknown action is a no-op', () => {
		const state = { report: { status: 'failed' } };
		expect(reducer(state, { type: 'OTHER' })).toBe(state);
	});
});

describe('the registered designsetgo/agent-build store', () => {
	test('getReport() is null until a report is set', () => {
		expect(select(AGENT_BUILD_REPORT_STORE).getReport()).toBeNull();
	});

	test('setReport() stores the report and getReport() returns it', () => {
		const report = {
			postId: 12,
			status: 'awaiting_review',
			invalid: [],
			findings: [{ rule: 'no-custom-html', severity: 'warning' }],
		};

		dispatch(AGENT_BUILD_REPORT_STORE).setReport(report);

		expect(select(AGENT_BUILD_REPORT_STORE).getReport()).toEqual(report);
	});

	test('setReport(null) clears the report', () => {
		dispatch(AGENT_BUILD_REPORT_STORE).setReport({ status: 'failed' });
		dispatch(AGENT_BUILD_REPORT_STORE).setReport(null);

		expect(select(AGENT_BUILD_REPORT_STORE).getReport()).toBeNull();
	});
});
