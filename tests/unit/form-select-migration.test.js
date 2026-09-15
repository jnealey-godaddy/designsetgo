/**
 * Static select fields must keep authored text and empty-placeholder opt-outs
 * when the editor migrates them to the dynamic block format.
 */
import {
	parse,
	serialize,
	unregisterBlockType,
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { registerDesignSetGoBlock } from '../../tools/regenerate-patterns';

const name = 'designsetgo/form-select-field';

// Handwritten historical markup, including a required field and help text.
const legacy = `<!-- wp:designsetgo/form-select-field {"fieldName":"topic","label":"Votre sujet","helpText":"Choisissez un sujet.","required":true,"options":[{"label":"Café","value":"cafe"}]} -->
<div class="wp-block-designsetgo-form-select-field dsgo-form-field dsgo-form-field--select" style="flex-basis:100%;max-width:100%"><label for="field-topic" class="dsgo-form-field__label">Votre sujet<span class="dsgo-form-field__required" aria-label="required">*</span></label><select id="field-topic" name="topic" class="dsgo-form-field__select" required aria-describedby="field-topic-help" aria-required="true" data-field-type="select">PLACEHOLDER<option value="cafe">Café</option></select><p id="field-topic-help" class="dsgo-form-field__help">Choisissez un sujet.</p></div>
<!-- /wp:designsetgo/form-select-field -->`;

beforeAll(() => registerDesignSetGoBlock(name));
afterAll(() => unregisterBlockType(name));

it.each(['-- Choisir un sujet --', '-- Select an option --', ''])(
	'migrates the historical placeholder %j without validation errors or data loss',
	(placeholder) => {
		const html = legacy.replace(
			'PLACEHOLDER',
			placeholder ? `<option value="">${placeholder}</option>` : ''
		);
		const [block] = parse(html);
		expect(console).toHaveInformed();
		expect(block.name).toBe(name);
		expect(block.isValid).toBe(true);
		expect(block.validationIssues).toEqual([]);
		expect(block.attributes).toMatchObject({
			placeholder,
			fieldName: 'topic',
			label: 'Votre sujet',
			helpText: 'Choisissez un sujet.',
			required: true,
			options: [{ label: 'Café', value: 'cafe' }],
		});
		const saved = serialize(block);
		expect(saved).not.toContain('<select');
		expect(saved).toMatch(/\/-->$/);
		const [reopened] = parse(saved);
		expect(reopened.isValid).toBe(true);
		expect(reopened.attributes).toEqual(block.attributes);
	}
);
