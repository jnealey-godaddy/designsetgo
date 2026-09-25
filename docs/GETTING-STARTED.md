# Getting Started with DesignSetGo Development

This guide walks you through setting up your development environment and making your first contribution to DesignSetGo. Perfect for newcomers to WordPress block development or the project.

## 📋 Table of Contents

- [Prerequisites](#prerequisites)
- [Understanding the Stack](#understanding-the-stack)
- [Step-by-Step Setup](#step-by-step-setup)
- [Your First Change](#your-first-change)
- [Development Tools](#development-tools)
- [Common Workflows](#common-workflows)
- [Troubleshooting](#troubleshooting)
- [Next Steps](#next-steps)

## Prerequisites

### Required Software

Before you begin, install these tools:

#### 1. Node.js (JavaScript Runtime)

**What it does:** Runs JavaScript outside the browser, powers our build tools

**Installation:**
- Download from https://nodejs.org/
- Choose the LTS version — CI (`.github/workflows/ci.yml`) runs on Node 24.x, so match that locally
- Verify: `node --version` (should show v24.x)

#### 2. npm (Package Manager)

**What it does:** Installs JavaScript dependencies

**Installation:**
- Comes automatically with Node.js
- Verify: `npm --version` (should show 8.x.x or higher)

#### 3. Git (Version Control)

**What it does:** Tracks code changes and collaborates with others

**Installation:**
- Download from https://git-scm.com/
- Verify: `git --version`
- Configure:
  ```bash
  git config --global user.name "Your Name"
  git config --global user.email "your.email@example.com"
  ```

#### 4. Docker Desktop (WordPress Environment)

**What it does:** Runs WordPress locally without installing PHP/MySQL

**Installation:**
- Download from https://www.docker.com/products/docker-desktop/
- Install and start Docker Desktop
- Verify: `docker --version`

**Note:** On Mac, you may need to grant Docker permissions in System Preferences → Privacy & Security.

### Optional but Recommended

#### Code Editor

We recommend **Visual Studio Code** (free):
- Download from https://code.visualstudio.com/
- Install these extensions:
  - "ESLint" - JavaScript linting
  - "Prettier" - Code formatting
  - "PHP Intelephense" - PHP support

#### Terminal Application

- **Mac:** Built-in Terminal app or [iTerm2](https://iterm2.com/)
- **Windows:** [Windows Terminal](https://aka.ms/terminal) or Git Bash
- **Linux:** Your distribution's terminal

## Understanding the Stack

Before diving in, here's what powers DesignSetGo:

### Frontend (What Users See)

```
React (UI Components)
    ↓
WordPress Block Editor (Gutenberg)
    ↓
SCSS → CSS (Styles)
```

**Technologies:**
- **React** - JavaScript library for building user interfaces
- **WordPress Block API** - Framework for creating blocks
- **SCSS** - Enhanced CSS with variables and nesting

### Build Process (How Code Gets Compiled)

```
Your Code (src/)
    ↓
Webpack (Bundler)
    ↓
Compiled Code (build/)
    ↓
WordPress (Runtime)
```

**Tools:**
- **Webpack** - Bundles JavaScript and CSS
- **Babel** - Converts modern JavaScript to compatible version
- **@wordpress/scripts** - Official WordPress build tools

### Backend (Server-Side)

```
PHP (WordPress Plugin)
    ↓
Block Registration
    ↓
Database (Block Content)
```

**Technologies:**
- **PHP 8.0+** - Server-side language
- **WordPress 6.7+** - Content management system

### Local Development

```
Docker (Container)
    ↓
WordPress + PHP + MySQL
    ↓
http://localhost:9451
```

**Tools:**
- **@wordpress/env (wp-env)** - Local WordPress environment
- **Docker** - Containerization platform

## Step-by-Step Setup

### Step 1: Fork the Repository

**What is forking?** Creating your own copy of the project on GitHub.

1. Go to https://github.com/jnealey-godaddy/designsetgo
2. Click the "Fork" button in the top-right
3. Select your GitHub account as the destination
4. Wait for the fork to complete

**Result:** You now have `https://github.com/YOUR-USERNAME/designsetgo`

### Step 2: Clone Your Fork

**What is cloning?** Downloading the code to your computer.

```bash
# Navigate to where you want the project
cd ~/Documents/GitHub  # or your preferred location

# Clone your fork (replace YOUR-USERNAME)
git clone https://github.com/YOUR-USERNAME/designsetgo.git

# Enter the project directory
cd designsetgo

# Verify you're in the right place
ls -la  # Should show designsetgo.php, package.json, etc.
```

### Step 3: Add Upstream Remote

**What is upstream?** The original repository you forked from.

```bash
# Add the original repo as "upstream"
git remote add upstream https://github.com/jnealey-godaddy/designsetgo.git

# Verify remotes
git remote -v

# Should show:
# origin    https://github.com/YOUR-USERNAME/designsetgo.git (fetch)
# origin    https://github.com/YOUR-USERNAME/designsetgo.git (push)
# upstream  https://github.com/jnealey-godaddy/designsetgo.git (fetch)
# upstream  https://github.com/jnealey-godaddy/designsetgo.git (push)
```

### Step 4: Install Node Dependencies

**What this does:** Downloads all JavaScript libraries and tools.

```bash
npm install

# This takes 1-2 minutes
# Downloads ~200MB of dependencies into node_modules/
```

**What gets installed:**
- React and WordPress packages
- Webpack and build tools
- Linters (code quality checkers)
- Testing frameworks

### Step 5: Start WordPress Environment

**What this does:** Creates a local WordPress site in Docker.

```bash
# Start WordPress (first run takes 2-3 minutes)
npx wp-env start

# You'll see output like:
# ✔ Building WordPress...
# ✔ Starting WordPress...
# WordPress development site started at http://localhost:9451
# WordPress test site started at http://localhost:8889
# MySQL is listening on port 52000
```

**What's happening:**
1. Downloads WordPress and MySQL Docker images
2. Creates WordPress site with default content
3. Installs DesignSetGo plugin automatically
4. Creates admin user (admin/password)

**Access points:**
- **Frontend:** http://localhost:9451
- **Admin:** http://localhost:9451/wp-admin
- **Credentials:** `admin` / `password`

### Step 6: Start Development Build

**What this does:** Watches for file changes and rebuilds automatically.

Open a **new terminal window** (keep wp-env running!):

```bash
# Navigate to project (if not already there)
cd ~/Documents/GitHub/designsetgo

# Start development mode
npm start

# You'll see:
# assets by path build/blocks/**/*.js
# webpack 5.x.x compiled successfully
#
# Now editing files in src/ will auto-rebuild!
```

**Keep this running while you develop.** Press `Ctrl+C` to stop.

### Step 7: Verify Everything Works

1. **Go to WordPress admin:** http://localhost:9451/wp-admin
2. **Log in:** Username: `admin`, Password: `password`
3. **Create a test page:**
   - Click "Pages" → "Add New"
   - Click the **+** button
   - Search for "DesignSetGo"
   - You should see all blocks (Accordion, Tabs, Icon, etc.)
4. **Insert a block:**
   - Click "Icon" block
   - It should insert and show controls
5. **Preview:**
   - Click "Preview" → "Preview in new tab"
   - Icon should display on frontend

**✅ If you see blocks, setup is complete!**

## Your First Change

Let's make a simple change to understand the workflow.

### Example: Change Icon Block Default Size

#### 1. Create a Feature Branch

```bash
# Make sure you're on main
git checkout main

# Create a new branch
git checkout -b feature/my-first-change

# Verify you're on the new branch
git branch  # Should show * feature/my-first-change
```

#### 2. Find the File to Edit

```bash
# Icon block files are in:
ls src/blocks/icon/

# You should see:
# - block.json (configuration)
# - edit.js (editor component)
# - save.js (frontend output)
# - style.scss (styles)
```

#### 3. Make a Change

Open `src/blocks/icon/block.json` in your editor:

```json
{
  "attributes": {
    "iconSize": {
      "type": "number",
      "default": 24  // Change this to 32
    }
  }
}
```

**Change `24` to `32` and save the file.**

#### 4. See Your Change

The build should automatically reload (watch the `npm start` terminal).

**Test it:**
1. Go to http://localhost:9451/wp-admin
2. Refresh the page
3. Insert a new Icon block
4. The default icon should now be larger!

#### 5. Commit Your Change

```bash
# Check what changed
git status

# Should show:
# modified:   src/blocks/icon/block.json

# Stage the change
git add src/blocks/icon/block.json

# Commit with a descriptive message
git commit -m "feat: increase default icon size to 32px"
```

#### 6. Push Your Branch

```bash
# Push to your fork
git push origin feature/my-first-change
```

#### 7. Create a Pull Request

1. Go to https://github.com/YOUR-USERNAME/designsetgo
2. GitHub will show a banner: "feature/my-first-change had recent pushes"
3. Click "Compare & pull request"
4. Fill out the description
5. Click "Create pull request"

**🎉 Congratulations! You've made your first contribution!**

## Development Tools

### npm Scripts

Common commands you'll use:

```bash
# Development
npm start              # Watch mode (auto-rebuild on changes)
npm run build          # Production build (one-time)

# Code Quality
npm run lint:js        # Check JavaScript for errors
npm run lint:css       # Check CSS/SCSS for errors
npm run format         # Auto-format all code (Prettier)

# Testing
npm run test:unit      # Run Jest unit tests
npm run test:e2e       # Run Playwright E2E tests
npm run test:e2e:ui    # E2E tests with visual interface

# WordPress Environment
npx wp-env start       # Start WordPress
npx wp-env stop        # Stop WordPress
npx wp-env clean all   # Reset WordPress (fresh install)
npx wp-env logs        # View PHP error logs

# Package Management
npm run plugin-zip     # Create distributable ZIP file
```

### VS Code Integration

If using VS Code, create `.vscode/settings.json`:

```json
{
  "editor.formatOnSave": true,
  "editor.defaultFormatter": "esbenp.prettier-vscode",
  "[php]": {
    "editor.defaultFormatter": "bmewburn.vscode-intelephense-client"
  },
  "eslint.validate": [
    "javascript",
    "javascriptreact"
  ]
}
```

This enables auto-formatting on save.

### Browser DevTools

**Testing in the editor:**
1. Open http://localhost:9451/wp-admin in Chrome/Firefox
2. Open DevTools (F12)
3. Check **Console** tab for JavaScript errors
4. Use **Elements** tab to inspect block markup
5. Use **Network** tab to see asset loading

**Testing on frontend:**
1. Preview a page with your block
2. Open DevTools
3. Same checks as above

## Common Workflows

### Daily Development

```bash
# 1. Start of day - Get latest changes
git checkout main
git pull upstream main
npm install  # If dependencies changed

# 2. Start services
npx wp-env start  # Terminal 1
npm start         # Terminal 2

# 3. Create feature branch
git checkout -b feature/my-feature

# 4. Make changes to src/
# 5. Test in browser (http://localhost:9451)
# 6. Commit and push

# 7. End of day - Stop services
# Terminal 2: Ctrl+C (stop npm start)
npx wp-env stop
```

### Updating Your Fork

```bash
# Get latest changes from main repository
git checkout main
git pull upstream main

# Push to your fork
git push origin main

# Update your feature branch
git checkout feature/my-feature
git rebase main
```

### Fixing Build Errors

```bash
# 1. Clean node_modules
rm -rf node_modules package-lock.json

# 2. Reinstall
npm install

# 3. Rebuild
npm run build

# 4. Restart dev mode
npm start
```

### Resetting WordPress

```bash
# If WordPress gets corrupted
npx wp-env clean all
npx wp-env start

# This creates a fresh WordPress install
```

## Troubleshooting

### Docker Issues

**Error: "Cannot connect to Docker daemon"**
```bash
# Make sure Docker Desktop is running
# You should see Docker icon in system tray/menu bar
```

**Error: "Port 9451 is already in use"**
```bash
# Option 1: Stop the conflicting service
# Option 2: Change wp-env port in .wp-env.json
```

### Build Issues

**Error: "Module not found"**
```bash
# Reinstall dependencies
rm -rf node_modules
npm install
```

**Error: "SCSS compilation failed"**
```bash
# Check for syntax errors in .scss files
# Look for missing semicolons, braces, etc.
```

### WordPress Issues

**White screen after code change**
```bash
# Check PHP errors
npx wp-env logs

# Look for syntax errors or fatal errors
```

**Block won't insert**
```bash
# Check browser console for JavaScript errors
# Make sure build completed successfully
# Refresh the editor page
```

### Git Issues

**Error: "Permission denied (publickey)"**
```bash
# You need to set up SSH keys
# See: https://docs.github.com/en/authentication/connecting-to-github-with-ssh
```

**Merge conflicts**
```bash
# Update from upstream first
git checkout main
git pull upstream main

# Rebase your branch
git checkout feature/my-feature
git rebase main

# Fix conflicts in your editor
# Then:
git add .
git rebase --continue
```

### More Help

See [troubleshooting/TROUBLESHOOTING.md](troubleshooting/TROUBLESHOOTING.md) for additional issues.

## Next Steps

### Learn the Codebase

1. **[ARCHITECTURE.md](ARCHITECTURE.md)** - Understand project structure
2. **[../.claude/CLAUDE.md](../.claude/CLAUDE.md)** - Development patterns
3. **[BEST-PRACTICES-SUMMARY.md](guides/BEST-PRACTICES-SUMMARY.md)** - Quick reference

### Learn Block Development

**Official Resources:**
- [Block Editor Handbook](https://developer.wordpress.org/block-editor/)
- [Block API Reference](https://developer.wordpress.org/block-editor/reference-guides/block-api/)
- [React Tutorial](https://react.dev/learn)

**DesignSetGo Examples:**
- Study `src/blocks/icon/` - Simple block
- Study `src/blocks/accordion/` - Parent-child blocks
- Study `src/blocks/tabs/` - Complex interactive block

### Find Issues to Work On

1. Go to [GitHub Issues](https://github.com/jnealey-godaddy/designsetgo/issues)
2. Filter by `good-first-issue` label
3. Comment on an issue to claim it
4. Follow the workflow above

### Join the Community

- **Ask Questions:** [GitHub Discussions](https://github.com/jnealey-godaddy/designsetgo/discussions)
- **Report Bugs:** [GitHub Issues](https://github.com/jnealey-godaddy/designsetgo/issues/new)
- **Share Ideas:** [Feature Requests](https://github.com/jnealey-godaddy/designsetgo/issues/new?labels=enhancement)

## Cheat Sheet

### Quick Command Reference

```bash
# Setup (one-time)
git clone https://github.com/YOUR-USERNAME/designsetgo.git
cd designsetgo
npm install

# Daily workflow
npx wp-env start       # Start WordPress
npm start              # Start development build
# ... make changes ...
git add .
git commit -m "feat: description"
git push origin branch-name

# Testing
npm run lint:js        # Check code quality
npm run test:unit      # Run tests
http://localhost:9451/wp-admin  # Manual testing

# Cleanup
npx wp-env stop        # Stop WordPress
```

### File Locations

```
src/blocks/my-block/
├── block.json        # Configuration (attributes, supports)
├── edit.js          # Editor component (React)
├── save.js          # Frontend output
├── style.scss       # Styles (both editor & frontend)
└── editor.scss      # Editor-only styles

includes/blocks/
└── class-my-block.php  # PHP registration (if needed)
```

### Getting Help

1. Check [troubleshooting/TROUBLESHOOTING.md](troubleshooting/TROUBLESHOOTING.md)
2. Search [existing issues](https://github.com/jnealey-godaddy/designsetgo/issues)
3. Ask in [Discussions](https://github.com/jnealey-godaddy/designsetgo/discussions)
4. Open a [new issue](https://github.com/jnealey-godaddy/designsetgo/issues/new)

---

## Welcome to DesignSetGo! 🎉

You're now ready to contribute. Don't hesitate to ask questions - we're here to help!

**Happy coding!** 🚀

---

**License**: GPL-2.0-or-later | **WordPress**: 6.7+ (tested to 6.9 via `.wp-env.json`)
