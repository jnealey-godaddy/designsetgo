/**
 * Custom hook to fetch breadcrumb data from the editor
 *
 * @return {Object} Breadcrumb data including post title, type, parents, and categories
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';

const EMPTY_ARRAY = [];

export function useBreadcrumbData() {
	const rawData = useSelect((select) => {
		const { getCurrentPost, getEditedPostAttribute } =
			select('core/editor') || {};
		const { getEntityRecords } = select('core') || {};

		if (!getCurrentPost || !getEditedPostAttribute) {
			return {
				postTitle: '',
				postType: 'post',
				parentId: 0,
				categoryIds: EMPTY_ARRAY,
				allPages: null,
				allCategories: null,
				isEditorReady: false,
			};
		}

		const title = getEditedPostAttribute('title');
		const type = getEditedPostAttribute('type');
		const parentId = getEditedPostAttribute('parent');
		const categoryIds = getEditedPostAttribute('categories') || EMPTY_ARRAY;

		let allPages = null;
		let allCategories = null;

		if (type === 'page' && parentId && getEntityRecords) {
			allPages = getEntityRecords('postType', 'page', {
				per_page: -1,
			});
		}

		if (type === 'post' && categoryIds.length > 0 && getEntityRecords) {
			allCategories = getEntityRecords('taxonomy', 'category', {
				per_page: -1,
			});
		}

		return {
			postTitle: title || __('(no title)', 'designsetgo'),
			postType: type || 'post',
			parentId: parentId || 0,
			categoryIds,
			allPages,
			allCategories,
			isEditorReady: true,
		};
	}, []);

	const {
		postTitle,
		postType,
		parentId,
		categoryIds,
		allPages,
		allCategories,
		isEditorReady,
	} = rawData;

	const postParents = useMemo(() => {
		if (postType !== 'page' || !parentId || !allPages) {
			return EMPTY_ARRAY;
		}
		const parents = [];
		let currentParentId = parentId;
		while (currentParentId) {
			const parent = allPages.find((p) => p.id === currentParentId);
			if (parent) {
				parents.unshift({
					title: parent.title.rendered,
					id: parent.id,
				});
				currentParentId = parent.parent;
			} else {
				break;
			}
		}
		return parents.length > 0 ? parents : EMPTY_ARRAY;
	}, [postType, parentId, allPages]);

	const postCategories = useMemo(() => {
		if (postType !== 'post' || categoryIds.length === 0 || !allCategories) {
			return EMPTY_ARRAY;
		}
		// Same rule as the PHP trail: the lowest term ID, then its ancestors.
		const firstCategoryId = Math.min(...categoryIds);
		const chain = [];
		let current = allCategories.find((cat) => cat.id === firstCategoryId);
		while (current && !chain.some((c) => c.id === current.id)) {
			chain.unshift({ title: current.name, id: current.id });
			current = current.parent
				? allCategories.find((cat) => cat.id === current.parent)
				: null;
		}
		if (chain.length > 0) {
			return chain;
		}
		return EMPTY_ARRAY;
	}, [postType, categoryIds, allCategories]);

	return {
		postTitle,
		postType,
		postParents,
		postCategories,
		isEditorReady,
	};
}
