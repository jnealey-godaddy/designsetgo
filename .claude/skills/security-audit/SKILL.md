---
name: security-audit
description: Run comprehensive security audit for the WordPress plugin
context: fork
agent: Explore
allowed-tools: Read, Glob, Grep, Bash(npm run *), Bash(find *), Bash(grep *), Bash(ls *)
---


Act as a senior WordPress plugin developer. Review the DesignSetGo WordPress plugin for best practices, security vulnerabilities, and performance optimization opportunities.

## Comprehensive Review Areas

### 1. **Security Audit** (Critical)

Analyze all PHP and JavaScript files for security vulnerabilities:

**PHP Security Issues:**
- [ ] SQL Injection vulnerabilities (improper use of $wpdb, missing prepare())
- [ ] XSS vulnerabilities (missing esc_html, esc_attr, esc_url, wp_kses)
- [ ] CSRF vulnerabilities (missing nonce verification)
- [ ] Arbitrary file inclusion (improper use of require/include)
- [ ] Path traversal vulnerabilities (missing realpath() checks)
- [ ] Capability checks on privileged operations
- [ ] REST API input validation and sanitization
- [ ] Direct file access checks (ABSPATH checks)

**JavaScript Security Issues:**
- [ ] XSS via innerHTML with unsanitized data
- [ ] URL validation (preventing javascript:, data: URLs)
- [ ] Data attribute sanitization before DOM manipulation
- [ ] Color/numeric value validation before use

**Check these critical files:**
- `includes/admin/class-global-styles.php` - REST API endpoints
- `includes/patterns/class-loader.php` - File inclusion
- `includes/core/class-assets.php` - Asset loading
- `src/extensions/*/frontend.js` - Frontend JavaScript
- Any file with `$_GET`, `$_POST`, `$_REQUEST`, `get_json_params()`

### 2. **Performance Analysis**

**Asset Loading:**
- [ ] Are assets loading from `build/` instead of `src/`?
- [ ] Conditional loading (only load when blocks present)?
- [ ] Asset file size optimization opportunities
- [ ] Unnecessary dependencies being loaded

**Database Performance:**
- [ ] Transient caching for expensive operations
- [ ] Block/pattern registration optimization
- [ ] Unnecessary queries on every page load

**Frontend Performance:**
- [ ] JavaScript bundle size and code splitting
- [ ] CSS optimization (remove unused rules, use CSS variables)
- [ ] Lazy loading for videos/images
- [ ] React performance (missing memoization, unnecessary re-renders)

### 3. **WordPress Coding Standards**

**PHP Standards:**
- [ ] Proper DocBlocks for all functions/methods
- [ ] Consistent namespacing
- [ ] Text domains on all translation functions
- [ ] Proper sanitization and escaping
- [ ] Error handling and logging

**JavaScript Standards:**
- [ ] ESLint compliance
- [ ] Proper JSDoc comments
- [ ] Modern ES6+ patterns
- [ ] WordPress-specific best practices

### 4. **Architecture Review**

**Code Quality:**
- [ ] Singleton pattern properly implemented
- [ ] Separation of concerns
- [ ] DRY principle (no code duplication)
- [ ] File organization and naming conventions
- [ ] No unused/commented code

**WordPress Integration:**
- [ ] Proper use of hooks and filters
- [ ] Block registration best practices
- [ ] Asset enqueuing best practices
- [ ] Admin page security

## Output Requirements

Generate a comprehensive **SECURITY-REVIEW.md** file containing:

### 1. Executive Summary
- Overall security status (🔴 Critical, 🟡 High, 🟢 Medium, 🔵 Low)
- Number of issues found by severity
- Quick assessment of production readiness

### 2. Critical Security Issues (🔴)
For each issue:
- File location with line numbers
- Clear description of the vulnerability
- Attack vector explanation
- Complete code fix with before/after
- Estimated fix time

### 3. High Priority Issues (🟡)
Same format as critical issues

### 4. Medium Priority - Performance (🟢)
- Optimization opportunities
- Expected performance gains
- Implementation recommendations

### 5. Low Priority - Code Quality (🔵)
- Coding standards violations
- Documentation improvements
- Refactoring suggestions

### 6. Action Plan
Organize issues by priority with time estimates:
- **Week 1:** Critical security fixes (must do before production)
- **Week 2:** High priority security & performance
- **Week 3:** Performance optimization
- **Week 4:** Code quality & standards

### 7. Security Checklist for Production
Clear checklist of security requirements before deployment

### 8. Things Done Well
Positive feedback on good practices to maintain

## Execution Steps

1. **Review Plugin Structure**
   - Read main plugin file
   - Identify all PHP classes in `includes/`
   - Identify all JavaScript files in `src/`

2. **Security Scan**
   - Search for vulnerable patterns:
     ```bash
     # PHP vulnerabilities
     grep -r "get_json_params\|require\|include\|\$_GET\|\$_POST" includes/ --include="*.php"
     grep -r "sanitize\|escape\|esc_html\|esc_attr\|wp_kses" includes/ --include="*.php"

     # JavaScript vulnerabilities
     grep -r "innerHTML\|getAttribute\|setAttribute" src/ --include="*.js"
     ```

3. **Analyze Each Vulnerability**
   - Read the full context of flagged files
   - Determine if vulnerability exists
   - Provide specific fix

4. **Performance Review**
   - Check asset loading strategy in `includes/core/class-assets.php`
   - Review build output sizes
   - Identify optimization opportunities

5. **Generate Report**
   - Create comprehensive markdown document
   - Include code examples for all fixes
   - Provide actionable recommendations

## Additional Checks

### Dependency Security
- [ ] Run `npm audit` for JavaScript vulnerabilities
- [ ] Run `npm run check-licenses` for GPL compatibility
- [ ] Check for outdated packages
- [ ] Run `composer analyse` (PHPStan) — a separate script from `npm run lint:php` (which only runs phpcs); CI runs both

### Build Configuration
- [ ] Verify webpack config is optimized
- [ ] Check that source maps are disabled in production
- [ ] Verify minification is enabled

### WordPress.org Readiness
- [ ] No hardcoded credentials or API keys
- [ ] Proper licensing headers
- [ ] No "phone home" functionality
- [ ] Follows WordPress.org guidelines

## Final Output Format

```markdown
# DesignSetGo Plugin - Security, Performance & Best Practices Review

**Review Date:** YYYY-MM-DD
**Plugin Version:** X.X.X
**Reviewer:** Senior WordPress Plugin Developer

## Executive Summary
[Status overview]

## 🔴 CRITICAL SECURITY ISSUES
[Numbered issues with fixes]

## 🟡 HIGH PRIORITY ISSUES
[Numbered issues with fixes]

## 🟢 MEDIUM PRIORITY - Performance
[Optimization opportunities]

## 🔵 LOW PRIORITY - Code Quality
[Standards and documentation]

## 📋 ACTION PLAN
[Week-by-week priority order]

## 🔒 Security Checklist for Production
[Pre-deployment checklist]

## ✅ THINGS YOU'RE DOING WELL
[Positive reinforcement]
```

**IMPORTANT:**
- Be thorough but constructive
- Provide complete, working code fixes
- Explain WHY each issue matters
- Prioritize by severity and impact
- Include time estimates for fixes
- End with clear next steps
