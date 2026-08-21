import {
	normaliseTextPathData,
	TEXT_PATH_PRESETS,
} from '../../utils/svg-paths';

export const clamp = (value, minimum, maximum) =>
	Math.max(minimum, Math.min(maximum, Number(value) || 0));

const getArcSize = (value) => {
	if (value === undefined || value === null || value === '') {
		return 100;
	}

	return Math.round(clamp(value, 0, 100));
};

const getArcPathData = (arcSize) => {
	const controlY = 200 - getArcSize(arcSize) * 2;

	return {
		...TEXT_PATH_PRESETS.arc,
		d: `M 0 200 Q 500 ${controlY} 1000 200`,
	};
};

export const getTextPathData = ({ pathType, customPath, arcSize }) => {
	if (pathType === 'custom') {
		return normaliseTextPathData(customPath) || TEXT_PATH_PRESETS.wave;
	}

	if (pathType === 'arc') {
		return getArcPathData(arcSize);
	}

	return TEXT_PATH_PRESETS[pathType] || TEXT_PATH_PRESETS.wave;
};

export const getTextPathId = (uniqueId) =>
	`dsgo-text-path-${uniqueId || 'path'}`;

export const getSafeTextPathUrl = (url) => {
	if (typeof url !== 'string') {
		return '';
	}

	const trimmedUrl = url.trim();
	return /^(?:https?:|mailto:|tel:|\/|#)/i.test(trimmedUrl) ? trimmedUrl : '';
};

export const getSafeTextPathColor = (color) => {
	if (typeof color !== 'string') {
		return '';
	}

	const value = color.trim();
	const isPreset = /^var:preset\|color\|[a-z0-9-]+$/i.test(value);
	const isHex = /^#[0-9a-f]{3,8}$/i.test(value);
	const isFunctionalColor = /^(?:rgb|hsl)a?\([0-9.%\s,/+-]+\)$/i.test(value);

	return isPreset || isHex || isFunctionalColor ? value : '';
};
