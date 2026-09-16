# GitHub Copilot Instructions for DesignSetGo

This repository is a WordPress Gutenberg block plugin. When reviewing code or providing suggestions, follow these guidelines:

## WordPress Block Development Standards

### Critical Patterns
1. **ALWAYS use `useBlockProps()`** for block wrapper elements
2. **ALWAYS use `useInnerBlocksProps()`** for inner blocks (NOT plain `<InnerBlocks />`)
3. **NEVER use deprecated patterns** like `PanelColorSettings`
4. **USE modern color controls**: `ColorGradientSettingsDropdown`
5. **Prefer Block Supports** over custom controls

### Security Requirements (HIGH PRIORITY)
- Input sanitization: `sanitize_text_field()`, `wp_kses_post()`
- Output escaping: `esc_html()`, `esc_attr()`, `esc_url()`
- AJAX/Forms: Verify nonces with `wp_verify_nonce()`
- SQL queries: Use `$wpdb->prepare()`
- Check capabilities: `current_user_can()`

### Accessibility (WCAG 2.1 AA)
- Proper ARIA labels and roles
- Keyboard navigation support
- Screen reader announcements
- Color contrast compliance
- Semantic HTML

### Code Quality Standards
- File size limit: **300 lines maximum**
  - Suggest refactoring if exceeded
  - Extract components to `components/`
  - Extract utilities to `utils/`
- Internationalization: ALL user-facing strings must use `__()`
- JSDoc comments for all functions
- No direct DOM manipulation (use React patterns)
- Proper error handling

### Performance
- Avoid unnecessary re-renders
- Proper dependency arrays in hooks (`useEffect`, `useCallback`, `useMemo`)
- Check bundle size concerns
- Verify code splitting

### Project-Specific Patterns
Reference `.claude/CLAUDE.md` for detailed patterns:
- Use `dsgo-` prefix for ALL custom identifiers
- Color controls: Must include `clientId` in function signature
- Container blocks: Use two-div pattern (outer + inner)
- Width patterns: Use theme.json variables

### Testing Requirements
- New features should include tests
- Mention both editor AND frontend testing
- Check responsive behavior (375px, 768px, 1200px)

## Review Style

When reviewing code:
- Be constructive and educational
- Provide specific code examples
- Link to WordPress documentation
- Mark security issues as **HIGH PRIORITY**
- Acknowledge good patterns
- Reference project documentation when applicable

## Files to Ignore in Reviews

- `build/**` - Auto-generated
- `node_modules/**` - Dependencies
- `vendor/**` - PHP dependencies
- `*.asset.php` - Auto-generated
- `*.min.js`, `*.min.css` - Minified
- `package-lock.json`, `composer.lock` - Lock files

## Additional Context

This plugin provides 57 custom Gutenberg blocks including:
- Layout containers (Row, Section, Grid)
- Interactive blocks (Tabs, Accordion, Slider, etc.)
- Form builder system
- Visual elements (Icons, Counters, Progress bars)

Prioritize WordPress core compatibility, security, and accessibility in all suggestions.
