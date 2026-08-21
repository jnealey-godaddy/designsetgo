import apiFetch from '@wordpress/api-fetch';
import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useRef, useState } from '@wordpress/element';

const MAX_SVG_BYTES = 12288;

export default function CustomPathUpload({ onChange }) {
	const inputRef = useRef(null);
	const [error, setError] = useState('');
	const [isBusy, setIsBusy] = useState(false);

	const handleFileChange = async (event) => {
		const file = event.target.files?.[0];
		event.target.value = '';
		if (!file) {
			return;
		}

		if (file.size > MAX_SVG_BYTES) {
			setError(__('Choose an SVG smaller than 12 KB.', 'designsetgo'));
			return;
		}

		try {
			setError('');
			setIsBusy(true);
			const customPath = await apiFetch({
				path: '/designsetgo/v1/text-path/extract',
				method: 'POST',
				data: { svg: await file.text() },
			});
			onChange(customPath);
		} catch (requestError) {
			setError(
				requestError?.message ||
					__('This SVG does not contain a safe path.', 'designsetgo')
			);
		} finally {
			setIsBusy(false);
		}
	};

	return (
		<>
			<Button
				variant="secondary"
				onClick={() => inputRef.current?.click()}
				disabled={isBusy}
				__next40pxDefaultSize
			>
				{isBusy
					? __('Extracting path…', 'designsetgo')
					: __('Choose SVG path', 'designsetgo')}
			</Button>
			<input
				ref={inputRef}
				type="file"
				accept="image/svg+xml,.svg"
				style={{ display: 'none' }}
				onChange={handleFileChange}
			/>
			{error && (
				<Notice status="error" isDismissible={false}>
					{error}
				</Notice>
			)}
		</>
	);
}
