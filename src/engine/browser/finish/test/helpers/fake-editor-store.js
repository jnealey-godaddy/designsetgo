/**
 * A fake `core/editor` save state for `watchNextSave()`, shaped like core's
 * own reducer and selectors: `saving.options` outlives the request, but
 * `isAutosavingPost()`/`isPreviewingPost()` are only ever true while
 * `isSavingPost()` is (see `@wordpress/editor` `store/selectors.js`).
 */
import { watchNextSave } from '../../apply';

/**
 * @return {Object} `{ state, fire, save, watch }`.
 */
export function createFakeEditorStore() {
	const listeners = [];
	const state = { pending: false, options: {}, succeeded: true };

	const subscribe = (listener) => {
		listeners.push(listener);
		return () => {
			const index = listeners.indexOf(listener);
			if (index !== -1) {
				listeners.splice(index, 1);
			}
		};
	};
	const fire = () => [...listeners].forEach((listener) => listener());

	return {
		state,
		fire,
		/**
		 * Runs one save request start to finish, notifying listeners at each
		 * step the way the data store does.
		 *
		 * @param {Object}  [options]   `savePost()` options, e.g. `{ isAutosave: true }`.
		 * @param {boolean} [succeeded] Whether the request succeeds.
		 */
		save(options = {}, succeeded = true) {
			state.options = options;
			state.pending = true;
			fire();
			state.pending = false;
			state.succeeded = succeeded;
			fire();
		},
		watch: (onSuccess) =>
			watchNextSave({
				subscribe,
				isSavingPost: () => state.pending,
				isAutosavingPost: () =>
					state.pending && Boolean(state.options.isAutosave),
				isPreviewingPost: () =>
					state.pending && Boolean(state.options.isPreview),
				didPostSaveRequestSucceed: () => state.succeeded,
				onSuccess,
			}),
	};
}
