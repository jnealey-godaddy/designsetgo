/**
 * DynamicTagPicker — modal-based source + field picker.
 *
 * Consumed by both the Dynamic Image block's inspector and the extension
 * that binds dynamic tags to core/heading, core/paragraph, core/image etc.
 *
 * Public props:
 *  - value:     { source, args } | null
 *  - onChange:  fn({ source, args } | null)
 *  - returns:   return-type filter ('text' | 'image' | 'url' | …)
 *  - postId:    preview post ID (defaults to the current editor post)
 *  - postType:  for field discovery
 *  - title:     modal title
 *  - allowClear: boolean
 *  - isOpen:    boolean (controlled)
 *  - onClose:   fn()
 */
import { useState, useEffect, useMemo } from '@wordpress/element';
import {
	Modal,
	Button,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';

import { useDynamicTagSources } from './useDynamicTagSources';
import { useDynamicTagFields } from './useDynamicTagFields';
import { useDynamicTagPreview } from './useDynamicTagPreview';
import SourceSidebar from './SourceSidebar';
import SourceArgsForm from './SourceArgsForm';
import PreviewPanel from './PreviewPanel';
import './style.scss';

const DEFAULT_RETURNS = ['text', 'url', 'image', 'number', 'date'];

export default function DynamicTagPicker({
	value,
	onChange,
	returns = DEFAULT_RETURNS,
	postId: postIdProp,
	postType: postTypeProp,
	title = __('Dynamic Tag', 'designsetgo'),
	allowClear = true,
	isOpen,
	onClose,
}) {
	const editorContext = useSelect((select) => {
		const editor = select(editorStore);
		if (!editor) {
			return {};
		}
		return {
			postId: editor.getCurrentPostId?.(),
			postType: editor.getCurrentPostType?.(),
		};
	}, []);

	const postId = postIdProp || editorContext.postId;
	const postType = postTypeProp || editorContext.postType || 'post';

	const {
		status: sourcesStatus,
		groups,
		sources,
	} = useDynamicTagSources({ returns });

	const [selectedSource, setSelectedSource] = useState(value?.source || '');
	const [draftArgs, setDraftArgs] = useState(value?.args || {});
	const [search, setSearch] = useState('');

	useEffect(() => {
		if (isOpen) {
			setSelectedSource(value?.source || '');
			setDraftArgs(value?.args || {});
			setSearch('');
		}
	}, [isOpen, value?.source, value?.args]);

	const activeSource = useMemo(
		() => sources.find((s) => s.slug === selectedSource) || null,
		[sources, selectedSource]
	);

	const fieldDiscovery = useDynamicTagFields({
		source: selectedSource,
		postType,
		returns: Array.isArray(returns) ? returns[0] : returns,
		supportsFieldDiscovery: activeSource?.supportsFieldDiscovery || false,
	});

	const preview = useDynamicTagPreview({
		source: selectedSource,
		args: draftArgs,
		postId,
		size: draftArgs?.size,
	});

	if (!isOpen) {
		return null;
	}

	const handleApply = () => {
		if (!selectedSource) {
			return;
		}
		onChange({ source: selectedSource, args: draftArgs });
		onClose?.();
	};

	const handleClear = () => {
		onChange(null);
		onClose?.();
	};

	const handleSelectSource = (slug) => {
		setSelectedSource(slug);
		setDraftArgs({});
	};

	return (
		<Modal
			title={title}
			onRequestClose={onClose}
			className="dsgo-dynamic-tag-picker"
			size="large"
		>
			<div className="dsgo-dynamic-tag-picker__layout">
				<SourceSidebar
					status={sourcesStatus}
					groups={groups}
					sources={sources}
					search={search}
					onSearchChange={setSearch}
					selectedSource={selectedSource}
					onSelectSource={handleSelectSource}
				/>

				<div className="dsgo-dynamic-tag-picker__detail">
					{!activeSource && (
						<p className="dsgo-dynamic-tag-picker__prompt">
							{__(
								'Pick a source on the left to continue.',
								'designsetgo'
							)}
						</p>
					)}

					{activeSource && (
						<VStack spacing={4}>
							<header>
								<h2 className="dsgo-dynamic-tag-picker__title">
									{activeSource.label}
								</h2>
								<p className="dsgo-dynamic-tag-picker__subtitle">
									<code>{activeSource.slug}</code>
								</p>
							</header>

							<SourceArgsForm
								source={activeSource}
								args={draftArgs}
								onChange={setDraftArgs}
								fieldDiscovery={fieldDiscovery}
							/>

							<PreviewPanel
								preview={preview}
								returns={activeSource.returns}
							/>
						</VStack>
					)}
				</div>
			</div>

			<HStack
				justify="flex-end"
				className="dsgo-dynamic-tag-picker__footer"
			>
				{allowClear && value && (
					<Button
						variant="tertiary"
						isDestructive
						onClick={handleClear}
					>
						{__('Remove binding', 'designsetgo')}
					</Button>
				)}
				<Button variant="tertiary" onClick={onClose}>
					{__('Cancel', 'designsetgo')}
				</Button>
				<Button
					variant="primary"
					onClick={handleApply}
					disabled={!selectedSource}
				>
					{__('Use this source', 'designsetgo')}
				</Button>
			</HStack>
		</Modal>
	);
}
