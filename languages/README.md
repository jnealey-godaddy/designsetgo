# DesignSetGo Translation Guide

This directory contains translation files for the DesignSetGo WordPress plugin.

## Available Translation Files

- **designsetgo.pot** - Translation template (do not edit)
- **designsetgo-es_ES.po** - Spanish (Spain)
- **designsetgo-fr_FR.po** - French (France)
- **designsetgo-de_DE.po** - German (Germany)
- **designsetgo-it_IT.po** - Italian (Italy)
- **designsetgo-pt_BR.po** - Portuguese (Brazil)
- **designsetgo-ja.po** - Japanese
- **designsetgo-zh_CN.po** - Chinese (Simplified)
- **designsetgo-nl_NL.po** - Dutch (Netherlands)
- **designsetgo-ru_RU.po** - Russian (Russia)

## Translation Workflow

### Option 1: Using Poedit (Recommended for Translators)

**Poedit** is a user-friendly translation editor with a visual interface.

1. **Install Poedit**
   - Download from [https://poedit.net/](https://poedit.net/)
   - Free version is sufficient for our needs

2. **Open Translation File**
   - Launch Poedit
   - Click "Open" and select your language file (e.g., `designsetgo-es_ES.po`)

3. **Translate Strings**
   - Browse through the source strings on the left
   - Enter translations in the bottom panel
   - Poedit shows context and locations where strings are used

4. **Save**
   - Poedit automatically generates both `.po` and `.mo` files
   - The `.mo` file is the compiled binary used by WordPress

### Option 2: Using Loco Translate Plugin

**Loco Translate** provides an in-WordPress translation interface.

1. **Install Plugin**
   ```bash
   # Via WordPress admin
   Plugins → Add New → Search "Loco Translate" → Install & Activate
   ```

2. **Translate**
   - Navigate to `Loco Translate → Plugins → DesignSetGo`
   - Choose your language or create a new one
   - Translate strings directly in WordPress admin
   - Click "Save" to generate `.mo` file automatically

### Option 3: Using WP-CLI

**For developers who prefer command-line tools:**

```bash
# Generate .mo file from .po file
wp i18n make-mo languages/designsetgo-es_ES.po languages/

# Update .pot template when code changes
wp i18n make-pot . languages/designsetgo.pot --domain=designsetgo
```

### Option 4: Manual Translation Services

**For professional translations using services like DeepL, Google Translate, etc:**

1. **Extract msgid strings** from `.po` file
2. **Translate** using your preferred service
3. **Add translations** to `msgstr` fields in `.po` file
4. **Compile** to `.mo` using Poedit or WP-CLI

## File Format Explained

Translation files use the **gettext** format:

```po
# Comment explaining context
#: path/to/file.php:123
msgid "Original English text"
msgstr "Translated text"
```

### Example Entry

```po
# Inspector control label
#: src/blocks/flex/edit.js:45
msgid "Direction"
msgstr "Dirección"
```

### Plural Forms

Some languages have complex plural rules:

```po
msgid "%d item"
msgid_plural "%d items"
msgstr[0] "%d elemento"
msgstr[1] "%d elementos"
```

## Testing Translations

### 1. Install Translations

After creating `.mo` files, they're automatically loaded by WordPress if placed in the correct directory:

```
/wp-content/languages/plugins/designsetgo-{locale}.mo
```

### 2. Change WordPress Language

```bash
# Via WordPress admin
Settings → General → Site Language → Select your language → Save

# Via wp-config.php
define('WPLANG', 'es_ES');
```

### 3. Verify in Browser

- Navigate to WordPress admin
- Open block editor
- Insert a DesignSetGo block
- Verify strings are translated

## Updating Translations

When plugin code changes and new strings are added:

### Step 1: Update Template

```bash
wp i18n make-pot . languages/designsetgo.pot --domain=designsetgo
```

### Step 2: Merge with Existing Translations

Using Poedit:
1. Open your `.po` file
2. Click `Catalog → Update from POT file`
3. Select `designsetgo.pot`
4. Translate new strings
5. Save (generates `.mo` automatically)

Using WP-CLI:
```bash
msgmerge --update languages/designsetgo-es_ES.po languages/designsetgo.pot
```

## Translation Quality Guidelines

### 1. Context Matters

Review where the string appears in the code:

```po
#: src/blocks/flex/edit.js:45
#: src/blocks/grid/edit.js:32
msgid "Justify"
```

This appears in layout controls, so translate as "Justificar" (Spanish) not "Justicia" (justice).

### 2. Preserve Placeholders

```po
msgid "Select %s option"
msgstr "Seleccionar opción %s"
```

Keep `%s`, `%d`, `%1$s` placeholders in the same order and format.

### 3. Preserve HTML Tags

```po
msgid "Learn more about <a>block patterns</a>"
msgstr "Más información sobre <a>patrones de bloques</a>"
```

### 4. Match Tone

DesignSetGo uses casual, friendly language. Match this tone in translations.

### 5. Test in Context

Always test translations in the actual WordPress editor, not just in the `.po` file.

## Plural Forms by Language

Each language has specific plural rules configured in the `.po` header:

```po
"Plural-Forms: nplurals=2; plural=(n != 1);\n"
```

### Common Plural Forms

- **Spanish, German, Dutch, Italian**: `nplurals=2; plural=(n != 1);`
- **French, Portuguese**: `nplurals=2; plural=(n > 1);`
- **Japanese, Chinese**: `nplurals=1; plural=0;`
- **Russian**: `nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);`

## Contributing Translations

### For Contributors

1. **Fork Repository**
2. **Translate** your language file using Poedit
3. **Commit** both `.po` and `.mo` files:
   ```bash
   git add languages/designsetgo-{locale}.po
   git add languages/designsetgo-{locale}.mo
   git commit -m "feat: Add {Language} translation"
   ```
4. **Submit Pull Request**

### Translation Completeness

Check translation progress:

```bash
# Using msgfmt
msgfmt --statistics languages/designsetgo-es_ES.po

# Example output:
# 450 translated messages, 23 untranslated messages.
```

## Automated Translation Tools

### DeepL API (Recommended)

```bash
# Install deepl-cli
npm install -g deepl-cli

# Translate .po file
deepl translate --from EN --to ES languages/designsetgo.pot > translations.txt
```

### Google Translate API

```bash
# Requires Google Cloud account
# See: https://cloud.google.com/translate
```

### Important: Always Review Automated Translations

Machine translations often miss context. **Always review and refine** before committing.

## File Maintenance

### Regenerate All .mo Files

```bash
# Regenerate all compiled translations
for file in languages/*.po; do
    wp i18n make-mo "$file" languages/
done
```

### Validate .po Files

```bash
# Check for errors
msgfmt -c -v -o /dev/null languages/designsetgo-es_ES.po
```

## Resources

### Documentation

- [WordPress i18n Documentation](https://developer.wordpress.org/apis/internationalization/)
- [Gettext Manual](https://www.gnu.org/software/gettext/manual/)
- [Poedit Documentation](https://poedit.net/trac/wiki/Doc)

### Tools

- [Poedit](https://poedit.net/) - Translation editor
- [Loco Translate](https://wordpress.org/plugins/loco-translate/) - WordPress plugin
- [WP-CLI i18n](https://developer.wordpress.org/cli/commands/i18n/) - Command-line tools

### Translation Communities

- [WordPress Polyglots](https://make.wordpress.org/polyglots/) - Official translation team
- [GlotPress](https://translate.wordpress.org/) - WordPress translation platform

## Support

Need help with translations?

- **GitHub Issues**: [Report translation bugs](https://github.com/jnealey-godaddy/designsetgo/issues)
- **Discussions**: [Ask translation questions](https://github.com/jnealey-godaddy/designsetgo/discussions)

## License

All translation files are licensed under GPL-2.0-or-later, matching the main plugin license.
