# Background Video Extension - User Guide

**Compatible With**: Section, Row, Grid, Flip Card, Accordion, Tabs, Scroll blocks
**Since**: 1.0.0

## Overview

The **Background Video Extension** adds fullscreen background video capability to container blocks. Perfect for hero sections, feature showcases, and immersive landing pages.

**Key Features:**
- Upload/select video from Media Library
- Optional poster image (shown before video loads)
- Autoplay, loop, and muted controls
- Hide on mobile (save bandwidth)
- Overlay color with opacity (improve text readability)
- Object-fit cover (fills container)

## 🚀 Quick Start

1. Select a **Section**, **Row**, or **Grid** block
2. Open **Background Video** panel in sidebar
3. Click **Upload Video** and select MP4 file
4. Optionally upload a **Poster Image** (fallback)
5. Adjust settings (autoplay, loop, mute)
6. Add **Video Overlay** color in Color panel for text contrast

## ⚙️ Settings & Configuration

### Background Video Panel
- **Upload Video**: Select video file from Media Library (MP4 recommended)
- **Poster Image**: Optional fallback image (shown while loading or on mobile)
- **Autoplay**: Start playing when page loads (requires muted)
- **Loop**: Restart video when it ends
- **Muted**: Mute audio (required for autoplay in most browsers)
- **Hide on Mobile**: Hide video on devices <768px (saves bandwidth)

### Video Overlay (Color Panel)
- **Video Overlay Color**: Semi-transparent overlay (70% opacity) to improve text contrast
- Available in **Inspector Controls > Color** when video is set

### Supported Blocks
- **designsetgo/section**, **row**, **grid**: Layout containers
- **designsetgo/flip-card**: Interactive blocks
- **designsetgo/accordion**, **tabs**: Expandable content
- **designsetgo/scroll-accordion**, **scroll-marquee**: Scroll-based
- **designsetgo/image-accordion**: Image-based accordion

## 💡 Common Use Cases

### 1. Hero Sections
Full-width video background with heading + CTA overlay.

### 2. Product Showcases
Looping video of product in use with semi-transparent dark overlay.

### 3. Event Landing Pages
Highlight reel from past events with text overlay promoting next event.

### 4. Testimonial Sections
Subtle background video of happy customers with quote overlays.

## ✅ Best Practices

**DO:**
- Use short loops (10-30 seconds) to avoid large file sizes
- Compress video (aim for <5MB if possible)
- Set muted to true (required for autoplay)
- Add overlay color for text readability (40-70% opacity)
- Enable "Hide on Mobile" to save bandwidth
- Use poster image (shown before video loads)

**DON'T:**
- Use long videos (>1 minute) - slow page load
- Rely on video for critical content (may not load)
- Use videos with motion sickness triggers (fast movement, flashing)
- Forget to test on slow connections
- Use high bitrate videos (compress first)
- Skip poster image (blank space while loading)

## 💡 Tips & Tricks

- **File Format**: MP4 (H.264) best compatibility
- **Compression**: Use tools like HandBrake or online compressors
- **Overlay Contrast**: Dark overlay (black 50-70%) for light text, light overlay for dark text
- **Autoplay Policy**: Browsers require muted for autoplay (set both)
- **Performance**: Videos are lazy-loaded on modern browsers
- **Poster Image**: Use a frame from the video for seamless transition
- **Mobile Fallback**: Poster image shown on mobile when "Hide on Mobile" enabled

## Technical Notes

- Video uses `object-fit: cover` to fill container
- Positioned absolutely at z-index 0 (behind content)
- Overlay applied at 70% opacity via CSS
- Data attributes store video settings for frontend rendering
- Respects browser autoplay policies (muted required)
