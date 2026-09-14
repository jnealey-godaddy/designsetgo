/**
 * Re-export only. The real implementation lives in
 * `src/engine/testing/non-default-value.js` — engine source must not import
 * from `tests/`, so the probe moved there and this file exists solely to
 * keep every existing `./helpers/non-default-value` import working.
 */
export { nonDefaultValue } from '../../../src/engine/testing/non-default-value';
