---
name: performance-audit
description: Deep performance and optimization audit by senior WordPress developer
context: fork
agent: Explore
allowed-tools: Read, Glob, Grep, Bash(npm run *), Bash(find *), Bash(grep *), Bash(ls *)
---


Act as a senior WordPress plugin developer with expertise in web performance optimization, Core Web Vitals, and React performance patterns. Conduct a comprehensive performance audit of the DesignSetGo WordPress plugin, identifying bottlenecks, optimization opportunities, and providing actionable fixes.

## Performance Review Scope

This is a **DEEP PERFORMANCE AUDIT** focusing exclusively on performance optimization across all layers: frontend, editor, database, asset loading, bundle size, rendering, and runtime performance.

## Core Performance Review Areas

### 1. **Bundle Size Analysis** (Critical)

**JavaScript Bundles:**
- [ ] Total plugin JS size < 100KB gzipped (all blocks combined)
- [ ] Individual block JS < 10KB gzipped
- [ ] Shared dependencies properly extracted
- [ ] Code splitting implemented where appropriate
- [ ] Tree shaking working correctly
- [ ] No duplicate dependencies across bundles
- [ ] Development code removed from production builds

**CSS Bundles:**
- [ ] Total plugin CSS < 50KB gzipped
- [ ] Individual block CSS < 5KB gzipped
- [ ] Shared styles properly extracted
- [ ] No unused CSS rules
- [ ] Critical CSS inlined (if applicable)
- [ ] CSS variables used for theme values

**Analysis Commands:**
```bash
# Check build output sizes
ls -lh build/ | grep -E "\.js$|\.css$"

# Detailed bundle analysis
du -sh build/*.js build/*.css | sort -rh

# Gzipped sizes (production simulation)
find build/ -name "*.js" -o -name "*.css" | xargs -I {} sh -c 'echo "{}: $(gzip -c {} | wc -c) bytes gzipped"'

# Check for duplicate dependencies
grep -r "import.*from" src/ | grep -E "react|lodash|wp-" | sort | uniq -c | sort -rn
```

**Optimization Targets:**
- Blocks: Each < 10KB JS + 5KB CSS
- Extensions: Each < 5KB JS + 2KB CSS
- Shared utilities: < 15KB total
- Total plugin assets: < 100KB gzipped

### 2. **Asset Loading Strategy** (Critical)

**Conditional Loading:**
- [ ] Assets only load when blocks are actually used on page
- [ ] No global asset loading unless necessary
- [ ] `has_block()` or `has_blocks()` used for conditional enqueuing
- [ ] Block-specific assets registered per-block
- [ ] Shared dependencies loaded once (not duplicated)

**Asset Dependencies:**
- [ ] Minimal dependencies declared
- [ ] WordPress core dependencies used (not duplicated)
- [ ] No unnecessary polyfills
- [ ] Dependencies properly versioned
- [ ] Asset handles unique and prefixed

**Check Files:**
- `includes/core/class-assets.php` - Main asset loader
- `includes/blocks/class-loader.php` - Block registration
- All `block.json` files - Asset declarations

**Current Implementation Analysis:**
```php
// Check current loading strategy
grep -A 10 "wp_enqueue" includes/core/class-assets.php

// Check block asset registration
find src/blocks -name "block.json" -exec grep -H "script\|style" {} \;

// Find unconditional loading
grep -r "wp_enqueue_script\|wp_enqueue_style" includes/ | grep -v "is_admin\|has_block"
```

**Best Practice Pattern:**
```php
// ✅ GOOD - Conditional loading
public function enqueue_block_assets() {
    if (!has_block('designsetgo/block-name')) {
        return;
    }
    wp_enqueue_script('designsetgo-block-name');
}

// ❌ BAD - Always loads
public function enqueue_assets() {
    wp_enqueue_script('designsetgo-all-blocks');
}
```

### 3. **Frontend JavaScript Performance** (Critical)

**Runtime Performance:**
- [ ] No JavaScript on frontend unless absolutely necessary
- [ ] Event listeners use delegation (not individual handlers)
- [ ] No memory leaks (intervals/timeouts cleaned up)
- [ ] Passive event listeners for scroll/touch
- [ ] requestAnimationFrame for animations
- [ ] Intersection Observer for lazy loading
- [ ] Debouncing/throttling for frequent events

**DOM Manipulation:**
- [ ] Minimal DOM queries (cache selectors)
- [ ] Batch DOM updates (avoid layout thrashing)
- [ ] Use DocumentFragment for multiple insertions
- [ ] Avoid forced synchronous layouts
- [ ] CSS transitions over JavaScript animations

**Check Frontend Scripts:**
```bash
# Find all frontend JavaScript
find src/ -name "view.js" -o -name "frontend.js"

# Check for performance anti-patterns
grep -r "setInterval\|setTimeout\|addEventListener" src/*/view.js src/*/frontend.js

# Check for DOM queries in loops
grep -A 5 "forEach\|for\|while" src/*/view.js | grep "querySelector\|getElementById"
```

**Performance Patterns:**
```javascript
// ✅ GOOD - Event delegation, cached selectors
const container = document.querySelector('[data-dsgo-tabs]');
container?.addEventListener('click', (e) => {
    const tab = e.target.closest('[data-dsgo-tab]');
    if (!tab) return;
    handleTabClick(tab);
});

// ❌ BAD - Individual listeners, repeated queries
document.querySelectorAll('[data-dsgo-tab]').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelector('[data-dsgo-tabs]').classList.add('active');
    });
});

// ✅ GOOD - Passive listeners, cleanup
const handleScroll = throttle(() => { /* ... */ }, 100);
window.addEventListener('scroll', handleScroll, { passive: true });

// Cleanup on unload
window.addEventListener('unload', () => {
    window.removeEventListener('scroll', handleScroll);
});
```

### 4. **React Editor Performance** (High Priority)

**Component Optimization:**
- [ ] No `useEffect` for simple calculations (use `useMemo` instead)
- [ ] Expensive calculations wrapped in `useMemo`
- [ ] Event handlers wrapped in `useCallback`
- [ ] No unnecessary re-renders (use React DevTools Profiler)
- [ ] Large lists use windowing (react-window/react-virtualized)
- [ ] Conditional rendering optimized
- [ ] Lazy loading for heavy components

**Attribute Updates:**
- [ ] Batch attribute updates when possible
- [ ] No `setAttributes` in loops
- [ ] Avoid setting attributes that haven't changed
- [ ] Use functional updates for derived state

**Check Editor Performance:**
```bash
# Find useEffect usage (often unnecessary)
grep -r "useEffect" src/blocks/*/edit.js -A 3

# Find missing memoization
grep -r "const.*=" src/blocks/*/edit.js | grep -v "useMemo\|useCallback\|useState\|useSelect"

# Check for expensive operations
grep -r "map\|filter\|reduce\|sort" src/blocks/*/edit.js
```

**Performance Patterns:**
```javascript
// ❌ BAD - useEffect for simple calculation
useEffect(() => {
    setComputedValue(width * height);
}, [width, height]);

// ✅ GOOD - useMemo for calculation
const computedValue = useMemo(() => width * height, [width, height]);

// ❌ BAD - Inline function causes re-renders
<Button onClick={() => setAttributes({ value: 'new' })}>

// ✅ GOOD - Memoized callback
const handleClick = useCallback(() => {
    setAttributes({ value: 'new' });
}, []);
<Button onClick={handleClick}>

// ❌ BAD - Setting attributes in loop
items.forEach(item => {
    setAttributes({ [item.key]: item.value });
});

// ✅ GOOD - Batch updates
const updates = items.reduce((acc, item) => ({
    ...acc,
    [item.key]: item.value
}), {});
setAttributes(updates);
```

### 5. **CSS Performance** (High Priority)

**Selector Performance:**
- [ ] Low specificity selectors (use `:where()` for plugin styles)
- [ ] Scoped to block classes (`.wp-block-designsetgo-*`)
- [ ] Avoid universal selectors (`*`)
- [ ] Avoid deep descendant selectors (`.a .b .c .d`)
- [ ] No `!important` (except accessibility overrides)
- [ ] Class-based (not ID or tag selectors)

**CSS Optimization:**
- [ ] CSS variables for theme values
- [ ] No duplicate rules
- [ ] Mobile-first media queries
- [ ] Contain property for isolated blocks
- [ ] content-visibility for off-screen content
- [ ] will-change used sparingly

**Check CSS Quality:**
```bash
# Find high-specificity selectors
grep -r "\..*\..*\..*\." src/blocks/*/style.scss

# Find !important usage
grep -r "!important" src/ --include="*.scss" --include="*.css"

# Check for universal selectors
grep -r "\* {" src/ --include="*.scss"

# Find duplicate rules
cat build/style-index.css | grep -E "^\.[a-z-]+ \{" | sort | uniq -d
```

**Performance Patterns:**
```scss
// ✅ GOOD - Low specificity with :where()
:where(.wp-block-designsetgo-stack) {
    display: flex;
}

// ❌ BAD - High specificity
.wp-block-designsetgo-stack.custom-class.is-variant {
    display: flex;
}

// ✅ GOOD - Scoped to block
.wp-block-designsetgo-stack {
    .stack__item {
        flex: 1;
    }
}

// ❌ BAD - Global scope
.stack__item {
    flex: 1;
}

// ✅ GOOD - Performance optimization
.wp-block-designsetgo-gallery {
    contain: layout style paint;
    content-visibility: auto;
}
```

### 6. **Database & PHP Performance** (Medium Priority)

**Database Queries:**
- [ ] No database queries on every page load
- [ ] Transients used for expensive operations
- [ ] Query results cached
- [ ] Options not autoloaded unless necessary
- [ ] Batch operations instead of loops
- [ ] Indexes on custom tables (if applicable)

**PHP Optimization:**
- [ ] Minimal PHP execution on frontend
- [ ] No unused code paths
- [ ] Efficient loops (avoid nested queries)
- [ ] Early returns to skip unnecessary work
- [ ] Block registration optimized
- [ ] Pattern registration lazy-loaded

**Check PHP Performance:**
```bash
# Find database queries
grep -r "wpdb\|get_option\|update_option\|get_transient" includes/ --include="*.php"

# Check for queries in loops
grep -A 10 "foreach\|for\|while" includes/ | grep "wpdb\|get_"

# Find autoloaded options
grep -r "add_option\|update_option" includes/ | grep -v "false"
```

**Performance Patterns:**
```php
// ❌ BAD - Query in loop
foreach ($blocks as $block) {
    $meta = get_post_meta($block->ID);
}

// ✅ GOOD - Batch query
update_meta_cache('post', array_column($blocks, 'ID'));
foreach ($blocks as $block) {
    $meta = get_post_meta($block->ID); // Cached
}

// ❌ BAD - No caching
$patterns = $this->get_all_patterns(); // Expensive

// ✅ GOOD - Transient caching
$patterns = get_transient('dsg_patterns');
if (false === $patterns) {
    $patterns = $this->get_all_patterns();
    set_transient('dsg_patterns', $patterns, HOUR_IN_SECONDS);
}
```

### 7. **Image & Media Optimization** (Medium Priority)

**Image Handling:**
- [ ] Lazy loading attribute on images (`loading="lazy"`)
- [ ] Responsive images (srcset/sizes)
- [ ] Modern formats supported (WebP, AVIF)
- [ ] Appropriate image sizes used
- [ ] No Base64 images in CSS (except tiny icons)
- [ ] SVG optimization

**Video Handling:**
- [ ] Lazy loading on videos
- [ ] Poster images for videos
- [ ] No autoplay (except muted)
- [ ] Appropriate video formats

**Check Media Usage:**
```bash
# Find image tags
grep -r "<img" src/blocks/*/save.js

# Check for lazy loading
grep -r "loading=" src/blocks/*/save.js

# Find video usage
grep -r "<video" src/blocks/*/save.js
```

### 8. **Webpack & Build Optimization** (Medium Priority)

**Build Configuration:**
- [ ] Production mode enabled
- [ ] Minification enabled
- [ ] Tree shaking working
- [ ] Code splitting configured
- [ ] Source maps disabled in production
- [ ] Bundle analyzer used to identify bloat
- [ ] Dependencies externalized (WordPress packages)

**Check Build Config:**
```javascript
// Review webpack.config.js
- mode: 'production'
- optimization.minimize: true
- optimization.splitChunks: configured
- externals: WordPress dependencies
- devtool: false (production)
```

**Optimization Opportunities:**
```javascript
// Check for large dependencies
npm list --depth=0 --long

// Analyze bundle
npm run build -- --analyze

// Check for unused dependencies
npm install -g depcheck
depcheck
```

### 9. **Core Web Vitals Impact** (High Priority)

**Largest Contentful Paint (LCP):**
- [ ] Block assets load quickly (< 2.5s)
- [ ] No render-blocking resources
- [ ] Images optimized and lazy-loaded
- [ ] Fonts preloaded (if custom fonts)
- [ ] Critical CSS inlined (if applicable)

**Cumulative Layout Shift (CLS):**
- [ ] Dimensions set on images/videos
- [ ] No dynamic content insertion above fold
- [ ] Skeleton screens for loading states
- [ ] Reserved space for ads/embeds
- [ ] Fonts use font-display: swap

**First Input Delay (FID) / INP:**
- [ ] Minimal JavaScript execution time
- [ ] Long tasks broken up
- [ ] Main thread not blocked
- [ ] Input handlers optimized
- [ ] No synchronous XHR

**Total Blocking Time (TBT):**
- [ ] JavaScript bundles small
- [ ] Code split for route-based loading
- [ ] Third-party scripts deferred
- [ ] Web Workers for heavy computation

**Testing Commands:**
```bash
# Lighthouse CI (if configured)
npm run test:performance

# Measure bundle impact
ls -lh build/*.js | awk '{sum+=$5} END {print "Total JS:", sum/1024, "KB"}'

# Check for render-blocking resources
grep -r "enqueue_script\|enqueue_style" includes/ | grep -v "defer\|async"
```

### 10. **Memory & Resource Management** (Medium Priority)

**Memory Leaks:**
- [ ] Event listeners removed on cleanup
- [ ] Timers (setTimeout/setInterval) cleared
- [ ] No circular references
- [ ] Large objects released after use
- [ ] React components properly unmounted

**Resource Cleanup:**
- [ ] Observers disconnected (IntersectionObserver, ResizeObserver)
- [ ] Fetch requests aborted on cleanup
- [ ] Web Workers terminated
- [ ] Cache cleared when appropriate

**Check for Leaks:**
```bash
# Find event listeners without cleanup
grep -A 10 "addEventListener" src/ | grep -B 10 -v "removeEventListener"

# Find timers without cleanup
grep -A 10 "setInterval\|setTimeout" src/ | grep -B 10 -v "clearInterval\|clearTimeout"

# Find observers without cleanup
grep -A 10 "Observer" src/ | grep -B 10 -v "disconnect\|unobserve"
```

## Performance Testing Methodology

### 1. **Baseline Measurements**
```bash
# Build the plugin
npm run build

# Check build output
ls -lh build/

# Measure total bundle size
find build/ -type f \( -name "*.js" -o -name "*.css" \) -exec ls -lh {} \; | awk '{total+=$5} END {print "Total:", total/1024/1024, "MB"}'
```

### 2. **Editor Performance Testing**
- [ ] Open post with 10 plugin blocks
- [ ] Measure time to interactive
- [ ] Check for lag when typing
- [ ] Test block insertion speed
- [ ] Monitor React DevTools Profiler
- [ ] Check browser DevTools Performance tab

### 3. **Frontend Performance Testing**
- [ ] Test page with 10 plugin blocks
- [ ] Run Lighthouse audit
- [ ] Check Core Web Vitals
- [ ] Monitor Network waterfall
- [ ] Test on slow 3G connection
- [ ] Test on mobile device

### 4. **Load Testing**
- [ ] Page with 50+ blocks
- [ ] Rapid block insertion
- [ ] Multiple nested containers
- [ ] Large galleries/lists
- [ ] Memory profiling over time

## Output Requirements

Generate a comprehensive **PERFORMANCE-AUDIT.md** file with this structure:

```markdown
# DesignSetGo WordPress Plugin - Performance & Optimization Audit

**Audit Date:** YYYY-MM-DD
**Plugin Version:** X.X.X
**Auditor Role:** Senior WordPress Performance Engineer
**Environment:** WordPress X.X, PHP X.X


## Executive Summary

### Overall Performance Grade
[A+, A, B+, B, C+, C, D, F]

### Performance Impact Score
[Excellent | Good | Fair | Poor | Critical]

### Key Metrics
- **Total Bundle Size:** XXX KB (gzipped: XXX KB)
- **Largest Block Bundle:** XXX KB
- **Frontend JS:** XXX KB (Target: < 50KB)
- **Critical Issues:** XX
- **Optimization Opportunities:** XX
- **Estimated Performance Gain:** XX%

### Quick Wins (High Impact, Low Effort)
1. [Optimization that provides biggest benefit for least work]
2. [Optimization that provides biggest benefit for least work]
3. [Optimization that provides biggest benefit for least work]


## 🔴 CRITICAL PERFORMANCE ISSUES

### 1. [Issue Title]

**Impact:** [Blocks LCP | Increases TBT | Causes Memory Leak | etc.]
**Files Affected:** `path/to/file.js:123`, `path/to/file.php:456`

**Problem:**
[Clear description of performance issue and measurable impact]

**Current Measurement:**
- Metric before: XXX ms / XX KB
- Target: XXX ms / XX KB
- Impact on users: [Description]

**Current Code:**
```javascript
// Problematic code
```

**Optimized Code:**
```javascript
// Performance-optimized code with explanation
```

**Performance Gain:**
- Bundle size reduction: XX KB → XX KB (-XX%)
- Runtime improvement: XX ms → XX ms (-XX%)
- Memory reduction: XX MB → XX MB (-XX%)

**Implementation Time:** [15 min | 1 hour | 4 hours | 1 day]
**Priority:** Critical - Fix immediately


## 🟡 HIGH PRIORITY OPTIMIZATIONS

[Same detailed format as critical issues]


## 🟢 MEDIUM PRIORITY OPTIMIZATIONS

### Bundle Size Optimizations
[List opportunities with size savings]

### CSS Optimizations
[List opportunities with specificity/render improvements]

### React Performance
[List unnecessary re-renders, missing memoization]


## 🔵 LOW PRIORITY IMPROVEMENTS

### Code Quality
[Refactoring for better maintainability]

### Future Optimizations
[Ideas for future consideration]


## 📊 Performance Metrics Dashboard

### Bundle Size Analysis
```
Block Name          | JS (raw) | JS (gzip) | CSS (raw) | CSS (gzip) | Status
--------------------|----------|-----------|-----------|------------|--------
flex                | 15.2 KB  | 4.8 KB    | 3.1 KB    | 1.2 KB     | ✅ Good
grid                | 32.5 KB  | 12.3 KB   | 8.2 KB    | 3.1 KB     | ⚠️ Large
accordion           | 8.1 KB   | 2.9 KB    | 2.4 KB    | 0.9 KB     | ✅ Excellent
--------------------|----------|-----------|-----------|------------|--------
TOTAL               | 180 KB   | 65 KB     | 42 KB     | 18 KB      | ⚠️ Review
```

### Asset Loading Analysis
```
Scenario                          | Assets Loaded | Total Size | Status
----------------------------------|---------------|------------|--------
Empty page (no blocks)            | 0             | 0 KB       | ✅ Perfect
Page with 1 block                 | 2 files       | 15 KB      | ✅ Good
Page with 5 different blocks      | 8 files       | 85 KB      | ⚠️ Review
Page with 10+ blocks              | 12 files      | 150 KB     | 🔴 High
```

### Core Web Vitals Impact
```
Metric | Without Plugin | With Plugin | Impact | Target | Status
-------|----------------|-------------|--------|--------|--------
LCP    | 1.2s          | 1.8s        | +0.6s  | <2.5s  | ✅ Pass
FID    | 45ms          | 120ms       | +75ms  | <100ms | ⚠️ Warn
CLS    | 0.02          | 0.05        | +0.03  | <0.1   | ✅ Pass
TBT    | 150ms         | 380ms       | +230ms | <300ms | 🔴 Fail
```

### Memory Usage Analysis
```
Test Scenario               | Memory Used | Memory Leaks | Status
----------------------------|-------------|--------------|--------
10 blocks in editor         | 85 MB       | None         | ✅ Good
50 blocks in editor         | 420 MB      | None         | ⚠️ High
Editor after 10min use      | 180 MB      | 15 MB growth | 🔴 Leak
Frontend with 20 blocks     | 25 MB       | None         | ✅ Good
```


## 🎯 OPTIMIZATION ROADMAP

### Phase 1: Critical Fixes (Week 1)
**Goal:** Fix performance blockers preventing production deployment
**Estimated Impact:** 40% performance improvement

- [ ] [Critical Issue #1] - File.js:123 (4 hours)
- [ ] [Critical Issue #2] - File.php:456 (2 hours)
- [ ] [Critical Issue #3] - File.scss:789 (1 hour)

**Success Metrics:**
- Total bundle size < 100KB gzipped
- TBT < 300ms
- No memory leaks

### Phase 2: High-Impact Optimizations (Week 2)
**Goal:** Major performance improvements with reasonable effort
**Estimated Impact:** 25% additional improvement

- [ ] Implement code splitting (1 day)
- [ ] Optimize large blocks (4 hours)
- [ ] Add React memoization (4 hours)
- [ ] Optimize CSS specificity (2 hours)

**Success Metrics:**
- Individual blocks < 10KB JS
- LCP impact < 0.3s
- FID < 100ms

### Phase 3: Polish & Optimization (Week 3)
**Goal:** Fine-tune for optimal performance
**Estimated Impact:** 10% additional improvement

- [ ] CSS optimization (4 hours)
- [ ] Image lazy loading (2 hours)
- [ ] Frontend JS optimization (4 hours)
- [ ] Cache implementation (2 hours)

**Success Metrics:**
- All Core Web Vitals in "Good" range
- Bundle size < 80KB gzipped
- Zero console warnings

### Phase 4: Monitoring & Maintenance (Ongoing)
**Goal:** Maintain performance over time

- [ ] Set up bundle size monitoring
- [ ] Add performance budgets
- [ ] Regular Lighthouse audits
- [ ] Memory leak testing


## ✅ PERFORMANCE BEST PRACTICES (What You're Doing Well)

### Asset Loading
- [Specific good practices found]

### Bundle Management
- [Specific good practices found]

### React Patterns
- [Specific good practices found]

### CSS Performance
- [Specific good practices found]


## 🛠️ PERFORMANCE TOOLING RECOMMENDATIONS

### Development Tools
```bash
# Add to package.json
"scripts": {
  "analyze": "webpack-bundle-analyzer build/stats.json",
  "performance": "lighthouse https://example.com",
  "size-limit": "size-limit"
}
```

### Performance Budgets
```json
// .size-limit.json
[
  {
    "path": "build/index.js",
    "limit": "100 KB"
  },
  {
    "path": "build/style-index.css",
    "limit": "50 KB"
  }
]
```

### Monitoring
- [ ] Bundle size tracking in CI
- [ ] Lighthouse CI for Core Web Vitals
- [ ] Performance regression testing
- [ ] Memory profiling in tests


## 🔄 CONTINUOUS PERFORMANCE OPTIMIZATION

### Pre-Commit Checklist
- [ ] Run `npm run build` successfully
- [ ] Check bundle sizes didn't increase unexpectedly
- [ ] Test editor performance with 10+ blocks
- [ ] Test frontend performance
- [ ] No console errors or warnings
- [ ] Memory profiler shows no leaks

### Monthly Review
- [ ] Run full performance audit
- [ ] Review bundle sizes
- [ ] Update performance baselines
- [ ] Identify new optimization opportunities

### Quarterly Goals
- [ ] Reduce bundle size by X%
- [ ] Improve Core Web Vitals scores
- [ ] Optimize slowest blocks
- [ ] Implement new performance patterns


## 📚 PERFORMANCE RESOURCES

### WordPress Performance
- [WordPress Performance Best Practices](https://make.wordpress.org/core/handbook/testing/reporting-bugs/performance/)
- [Block Editor Performance](https://developer.wordpress.org/block-editor/reference-guides/performance/)
- Core Web Vitals for WordPress

### React Performance
- [React Performance Optimization](https://react.dev/learn/render-and-commit)
- useMemo and useCallback patterns
- React DevTools Profiler guide

### Web Performance
- [web.dev Core Web Vitals](https://web.dev/vitals/)
- [Lighthouse Performance Scoring](https://developer.chrome.com/docs/lighthouse/performance/performance-scoring/)
- Bundle size optimization techniques


**End of Performance Audit**
```

## Execution Strategy

### 1. **Initial Analysis (15 minutes)**
- Build the plugin and analyze output
- Review webpack configuration
- Check bundle sizes
- Identify largest files

### 2. **Bundle Analysis (30 minutes)**
- Analyze each block bundle
- Check for duplicate dependencies
- Identify optimization opportunities
- Test tree shaking effectiveness

### 3. **Asset Loading Review (20 minutes)**
- Review asset loading strategy
- Check conditional loading
- Identify unnecessary global assets
- Test asset dependencies

### 4. **Frontend Performance (30 minutes)**
- Review all frontend JavaScript
- Check for memory leaks
- Analyze event listener patterns
- Test runtime performance

### 5. **Editor Performance (30 minutes)**
- Review React components
- Check for unnecessary re-renders
- Identify missing memoization
- Test with multiple blocks

### 6. **CSS Performance (20 minutes)**
- Analyze CSS specificity
- Check for duplicate rules
- Review selector patterns
- Identify optimization opportunities

### 7. **Database & PHP (20 minutes)**
- Review database queries
- Check caching implementation
- Analyze PHP performance
- Identify bottlenecks

### 8. **Core Web Vitals (30 minutes)**
- Run Lighthouse audit
- Test on slow connection
- Measure impact on LCP, FID, CLS
- Identify render-blocking resources

### 9. **Generate Report (45 minutes)**
- Compile all findings
- Prioritize by impact and effort
- Write detailed fixes with code examples
- Create optimization roadmap

## Analysis Commands Reference

```bash
# Bundle size analysis
npm run build
ls -lh build/ | grep -E "\.js$|\.css$"
du -h build/ | sort -rh | head -20

# Gzipped sizes
find build/ -name "*.js" -exec sh -c 'echo "{}: $(gzip -c {} | wc -c) bytes"' \;

# Dependency analysis
npm list --depth=0
grep -r "import.*from" src/ | grep -oP "from ['\"].*?['\"]" | sort | uniq -c | sort -rn

# Frontend JavaScript
find src/ -name "view.js" -o -name "frontend.js"
grep -r "addEventListener\|querySelector" src/*/view.js

# React performance
grep -r "useEffect\|useMemo\|useCallback" src/blocks/*/edit.js

# CSS analysis
grep -r "!important" src/ --include="*.scss"
cat build/style-index.css | wc -l

# Database queries
grep -r "wpdb\|get_option\|get_transient" includes/ --include="*.php"

# Memory leaks
grep -A 10 "addEventListener" src/ | grep -v "removeEventListener"
grep -A 10 "setInterval\|setTimeout" src/ | grep -v "clear"
```

## Performance Targets

### Bundle Sizes
- **Individual Block JS:** < 10KB gzipped ✅
- **Individual Block CSS:** < 5KB gzipped ✅
- **Total Plugin Assets:** < 100KB gzipped ✅
- **Shared Dependencies:** < 20KB gzipped ✅

### Core Web Vitals
- **LCP:** < 2.5s (Impact < 0.5s) ✅
- **FID/INP:** < 100ms (Impact < 50ms) ✅
- **CLS:** < 0.1 (Impact < 0.05) ✅
- **TBT:** < 300ms ✅

### Runtime Performance
- **Block Insertion:** < 200ms ✅
- **Attribute Update:** < 50ms ✅
- **Frontend Interaction:** < 100ms ✅
- **Memory Growth:** < 10MB/hour ✅

## Success Criteria

A successful performance audit should:
- ✅ Identify ALL critical performance bottlenecks
- ✅ Provide measurable before/after metrics
- ✅ Give specific, actionable fixes with code examples
- ✅ Estimate performance gains for each optimization
- ✅ Prioritize by impact vs. effort
- ✅ Include time estimates for implementation
- ✅ Provide performance testing methodology
- ✅ Create clear optimization roadmap
- ✅ Set up monitoring for future performance

## Important Notes

- **Be Data-Driven:** Every issue must have measurable impact
- **Prioritize Impact:** Focus on optimizations with biggest user benefit
- **Consider Trade-offs:** Sometimes functionality > performance
- **Think Mobile:** Test on slow devices and networks
- **Monitor Trends:** Performance should improve over time, not degrade
- **Automate Testing:** Set up performance budgets and CI checks

**DELIVER VALUE:** The audit should make the plugin measurably faster with clear steps to achieve it.
