---
name: block-supports-audit
description: Audit blocks for WordPress Block Supports optimization opportunities
context: fork
agent: Explore
allowed-tools: Read, Glob, Grep, Bash(git *), Bash(npm run *)
---


Analyze all blocks to identify opportunities to replace custom controls with WordPress Block Supports.

## What This Checks

Scans all blocks in `src/blocks/` for:

1. **Custom color controls** → Should use `supports.color`
2. **Custom typography controls** → Should use `supports.typography`
3. **Custom spacing controls** → Should use `supports.spacing`
4. **Custom border controls** → Should use `supports.__experimentalBorder`
5. **Custom dimension controls** → Should use `supports.dimensions`

## Expected Benefits

- **Reduced code**: 50-95 lines per block
- **Better UX**: Familiar WordPress controls
- **Theme integration**: Automatic access to theme.json
- **Future-proof**: WordPress improvements benefit blocks automatically

## Audit Process

For each block in `src/blocks/`:

1. Read `edit.js` to identify custom controls
2. Read `block.json` to check current `supports`
3. Identify gaps where Block Supports could replace custom code
4. Calculate potential lines of code reduction
5. Assess migration difficulty (easy/medium/hard)

## Example Findings

**Before (Custom Control):**

```javascript
// edit.js - 25 lines
<PanelBody title="Typography">
    <FontSizePicker
        value={fontSize}
        onChange={(value) => setAttributes({ fontSize: value })}
    />
</PanelBody>
```

**After (Block Supports):**

```json
// block.json - 3 lines
{
  "supports": {
    "typography": {
      "fontSize": true
    }
  }
}
```

**Savings: 22 lines of code**

## Output

Creates `BLOCK-SUPPORTS-OPPORTUNITIES.md` with:

- List of blocks using custom controls
- Specific controls that could use Block Supports
- Estimated lines of code reduction per block
- Migration difficulty rating
- Priority recommendations
- Total potential code reduction

## When to Run

- After creating new blocks
- Quarterly optimization reviews
- Before major releases
- When WordPress adds new Block Supports features
