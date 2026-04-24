import { __ } from '@wordpress/i18n';
import { Button, __experimentalVStack as VStack } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { parse, serialize } from '@wordpress/blocks';
import apiFetch from '@wordpress/api-fetch';
import { useRef, useState } from '@wordpress/element';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { store as noticesStore } from '@wordpress/notices';

export default function TemplateIO({ clientId, attributes }) {
	const { queryId } = attributes;
	const { replaceBlocks } = useDispatch(blockEditorStore);
	const { createSuccessNotice, createErrorNotice } =
		useDispatch(noticesStore);
	const currentBlock = useSelect(
		(select) => select(blockEditorStore).getBlock(clientId),
		[clientId]
	);
	const fileInputRef = useRef(null);
	const [isBusy, setIsBusy] = useState(false);

	async function handleExport() {
		try {
			if (!currentBlock) {
				throw new Error(
					__('Unable to read the current Query block.', 'designsetgo')
				);
			}
			setIsBusy(true);
			const payload = {
				schemaVersion: 1,
				exportedAt: new Date().toISOString(),
				blockName: 'designsetgo/query',
				attributes: currentBlock.attributes || attributes,
				innerBlocks: serialize(currentBlock.innerBlocks || []),
			};
			const blob = new Blob([JSON.stringify(payload, null, 2)], {
				type: 'application/json',
			});
			const url = URL.createObjectURL(blob);
			const a = document.createElement('a');
			a.href = url;
			a.download = `query-template-${queryId}.json`;
			document.body.appendChild(a);
			a.click();
			document.body.removeChild(a);
			URL.revokeObjectURL(url);
			createSuccessNotice(__('Query template exported.', 'designsetgo'), {
				type: 'snackbar',
			});
		} catch (err) {
			createErrorNotice(
				err?.message || __('Export failed.', 'designsetgo'),
				{ type: 'snackbar' }
			);
		} finally {
			setIsBusy(false);
		}
	}

	function handleImportClick() {
		fileInputRef.current?.click();
	}

	async function handleFileChange(event) {
		const file = event.target.files?.[0];
		// Allow re-selecting the same file on subsequent clicks.
		event.target.value = '';
		if (!file) {
			return;
		}
		try {
			setIsBusy(true);
			const text = await file.text();
			const payload = JSON.parse(text);
			const response = await apiFetch({
				path: '/designsetgo/v1/query/template',
				method: 'POST',
				data: payload,
			});
			const blocks = parse(response.blockMarkup);
			if (!blocks.length) {
				throw new Error(
					__(
						'Imported JSON did not yield a valid block.',
						'designsetgo'
					)
				);
			}
			replaceBlocks(clientId, blocks);
			createSuccessNotice(__('Query template imported.', 'designsetgo'), {
				type: 'snackbar',
			});
		} catch (err) {
			createErrorNotice(
				err?.message || __('Import failed.', 'designsetgo'),
				{ type: 'snackbar' }
			);
		} finally {
			setIsBusy(false);
		}
	}

	return (
		<VStack spacing={2}>
			<Button
				__next40pxDefaultSize
				variant="secondary"
				onClick={handleExport}
				disabled={!queryId || !currentBlock || isBusy}
			>
				{__('Export template', 'designsetgo')}
			</Button>
			<Button
				__next40pxDefaultSize
				variant="secondary"
				onClick={handleImportClick}
				disabled={isBusy}
			>
				{__('Import template', 'designsetgo')}
			</Button>
			<input
				ref={fileInputRef}
				type="file"
				accept=".json,application/json"
				style={{ display: 'none' }}
				onChange={handleFileChange}
			/>
		</VStack>
	);
}
