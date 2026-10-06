/**
 * Form Builder — conditional field logic evaluator.
 *
 * Pure functions (no DOM) shared by the front-end view script and the editor.
 * Mirrored in PHP by DesignSetGo\Blocks\Form_Conditions; both are tested
 * against tests/fixtures/form-conditions-cases.json, so change them together.
 *
 * @since 2.10.0
 */

export const CONDITION_OPS = [
	'is',
	'is_not',
	'contains',
	'empty',
	'not_empty',
	'gt',
	'lt',
];

const TRIM_RE = /^[ \t\n\r\f\v]+|[ \t\n\r\f\v]+$/g;
const NUMERIC_RE = /^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/;
const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;
const TIME_RE = /^\d{2}:\d{2}(:\d{2})?$/;

/**
 * Read an own property only, so names like "constructor" never hit the prototype.
 *
 * @param {Object} obj Source object.
 * @param {string} key Property name.
 * @return {*} The value, or undefined when not an own property.
 */
function own(obj, key) {
	return obj && Object.prototype.hasOwnProperty.call(obj, key)
		? obj[key]
		: undefined;
}

function toText(value) {
	if (typeof value === 'string') {
		return value.replace(TRIM_RE, '');
	}
	if (typeof value === 'number' && Number.isFinite(value)) {
		return String(value);
	}
	return '';
}

function lower(text) {
	return text.toLocaleLowerCase('en');
}

function padTime(text) {
	return text.length === 5 ? `${text}:00` : text;
}

/**
 * Compare two trimmed values for gt/lt.
 *
 * @param {string} actual   Trimmed source value.
 * @param {string} expected Trimmed rule value.
 * @return {number|null} Negative, zero or positive; null when not comparable.
 */
function compareValues(actual, expected) {
	if (NUMERIC_RE.test(actual) && NUMERIC_RE.test(expected)) {
		return Number(actual) - Number(expected);
	}
	const bothDates = DATE_RE.test(actual) && DATE_RE.test(expected);
	const bothTimes = TIME_RE.test(actual) && TIME_RE.test(expected);
	if (!bothDates && !bothTimes) {
		return null;
	}
	const a = bothTimes ? padTime(actual) : actual;
	const b = bothTimes ? padTime(expected) : expected;
	if (a === b) {
		return 0;
	}
	return a < b ? -1 : 1;
}

/**
 * Normalize a raw dsgoConditions value to { operator, rules } with only
 * well-formed rules, or null when there are none.
 *
 * @param {*} raw Attribute value.
 * @return {Object|null} Normalized conditions.
 */
export function normalizeRules(raw) {
	if (!raw || typeof raw !== 'object' || !Array.isArray(raw.rules)) {
		return null;
	}
	const rules = raw.rules
		.filter(
			(rule) =>
				rule &&
				typeof rule === 'object' &&
				typeof rule.field === 'string' &&
				rule.field !== '' &&
				CONDITION_OPS.includes(rule.op)
		)
		.map((rule) => ({
			field: rule.field,
			op: rule.op,
			value: toText(rule.value),
		}));
	if (!rules.length) {
		return null;
	}
	const operator =
		typeof raw.operator === 'string' && raw.operator.toUpperCase() === 'OR'
			? 'OR'
			: 'AND';
	return { operator, rules };
}

/**
 * @param {*} raw Attribute value.
 * @return {boolean} Whether at least one well-formed rule exists.
 */
export function hasActiveRules(raw) {
	return normalizeRules(raw) !== null;
}

/**
 * @param {{op: string, value: string}} rule   Normalized rule.
 * @param {*}                           actual Source field value.
 * @return {boolean} Whether the rule matches.
 */
export function evaluateRule(rule, actual) {
	const a = toText(actual);
	const e = toText(rule.value);
	switch (rule.op) {
		case 'is':
			return lower(a) === lower(e);
		case 'is_not':
			return lower(a) !== lower(e);
		case 'contains':
			return e !== '' && lower(a).includes(lower(e));
		case 'empty':
			return a === '';
		case 'not_empty':
			return a !== '';
		case 'gt':
		case 'lt': {
			const diff = compareValues(a, e);
			if (diff === null) {
				return false;
			}
			return rule.op === 'gt' ? diff > 0 : diff < 0;
		}
		default:
			return false;
	}
}

/**
 * Work out which fields are visible.
 *
 * @param {string[]} fieldNames Field names in document order.
 * @param {Object}   conditions Raw dsgoConditions keyed by field name.
 * @param {Object}   values     Current values keyed by field name.
 * @return {string[]} Visible field names, document order.
 */
export function visibleFields(fieldNames, conditions, values) {
	const known = new Set(fieldNames);
	const visible = Object.create(null);
	const active = Object.create(null);
	fieldNames.forEach((name) => {
		visible[name] = true;
		const normalized = normalizeRules(own(conditions, name));
		if (!normalized) {
			return;
		}
		const rules = normalized.rules.filter(
			(rule) => rule.field !== name && known.has(rule.field)
		);
		if (rules.length) {
			active[name] = { operator: normalized.operator, rules };
		}
	});

	const read = (field) => (visible[field] ? own(values, field) : '');

	for (let pass = 0; pass < fieldNames.length; pass++) {
		let changed = false;
		fieldNames.forEach((name) => {
			if (!active[name]) {
				return;
			}
			const results = active[name].rules.map((rule) =>
				evaluateRule(rule, read(rule.field))
			);
			const next =
				active[name].operator === 'OR'
					? results.some(Boolean)
					: results.every(Boolean);
			if (visible[name] !== next) {
				visible[name] = next;
				changed = true;
			}
		});
		if (!changed) {
			break;
		}
	}

	return fieldNames.filter((name) => visible[name]);
}
