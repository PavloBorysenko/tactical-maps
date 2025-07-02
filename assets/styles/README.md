# Tactical Maps - CSS Architecture Guide

## 🎯 **Overview**

This project uses a **7-1 Pattern** SCSS architecture for maintainable, scalable, and professional CSS development. The architecture follows industry best practices and is production-ready.

**Status:** ✅ **Complete** - All styles are functional, background images work correctly, and the project is ready for production.

## 📁 **Directory Structure**

```
assets/styles/
├── app.scss                # Main entry point - imports all modules
├── abstracts/              # Variables, mixins, functions
│   ├── _variables.scss     # CSS custom properties (60+ variables)
│   └── _mixins.scss        # SCSS mixins for reusability
├── base/                   # Foundation styles
│   └── _base.scss          # HTML/body, typography, utilities
├── components/             # Reusable UI components
│   ├── _buttons.scss       # Button styles and variants
│   ├── _cards.scss         # Card components (military themed)
│   ├── _forms.scss         # Form elements and inputs
│   ├── _geo-objects.scss   # Geographic objects and icons
│   └── _maps.scss          # Leaflet maps, toolbar, popups
├── layout/                 # Structural elements
│   └── _containers.scss    # Containers, navigation, tables
└── pages/                  # Page-specific styles (empty, for future use)
```

## 🚀 **Quick Start**

### **Development Commands**

```bash
# Development build
yarn dev

# Production build
yarn build

# Watch mode (auto-rebuild on changes)
yarn watch
```

### **Adding New Styles**

1. **Components**: Add new `_component-name.scss` files in `components/`
2. **Import**: Add `@import 'components/component-name';` to `app.scss`
3. **Variables**: Define new variables in `abstracts/_variables.scss`
4. **Mixins**: Create reusable mixins in `abstracts/_mixins.scss`

## 🖼️ **Background Images - Professional Solution**

### **Implementation**

Background images are handled via **ES6 module imports** in JavaScript to avoid webpack resolve-url-loader conflicts:

**1. Import in `assets/app.js`:**

```javascript
import backgroundImage from './images/vector-topographic.avif';

document.addEventListener('DOMContentLoaded', function () {
    document.body.style.backgroundImage = `url('${backgroundImage}')`;
});
```

**2. CSS setup in `assets/styles/base/_base.scss`:**

```scss
body {
    background-color: #b7ad84; /* fallback color */
    background-repeat: repeat;
    background-size: auto;
    background-attachment: fixed;
    // background-image set via JavaScript
}
```

### **Benefits**

-   ✅ **Webpack optimized** - Automatic image processing and hashing
-   ✅ **No loader conflicts** - Bypasses resolve-url-loader issues
-   ✅ **Production ready** - Proper caching and optimization
-   ✅ **Scalable** - Easy to add more dynamic images

## 🎨 **CSS Architecture Details**

### **Variables System**

The project uses **CSS Custom Properties** (60+ variables) for consistency:

```scss
// Color system
:root {
    --military-olive-bg: rgba(183, 173, 132, 0.95);
    --primary-color: #007bff;
    --military-text: #3a3a2e;

    // Spacing scale
    --spacing-xs: 0.25rem;
    --spacing-sm: 0.5rem;
    --spacing-md: 1rem;

    // Component specific
    --border-radius-md: 6px;
    --transition-normal: 0.2s ease;
}
```

### **Component Development**

When creating new components:

1. **Use existing variables** from `abstracts/_variables.scss`
2. **Follow BEM methodology** for class naming
3. **Include responsive breakpoints** using mixins
4. **Add hover/focus states** for accessibility

**Example component structure:**

```scss
// _new-component.scss
.component-name {
    padding: var(--spacing-md);
    border-radius: var(--border-radius-md);
    transition: var(--transition-normal);

    &__element {
        // Element styles
    }

    &--modifier {
        // Modifier styles
    }

    @include media-breakpoint-down(md) {
        // Mobile styles
    }
}
```

## 🔧 **Technical Stack**

-   **SCSS** - CSS preprocessing
-   **7-1 Pattern** - Modular architecture
-   **Webpack Encore** - Build system
-   **Bootstrap 5** - UI framework integration
-   **Leaflet** - Map library styles
-   **Font Awesome** - Icon system

## 📱 **Responsive Design**

The architecture includes responsive utilities and breakpoints:

```scss
// Available breakpoints
$breakpoints: (
    xs: 0,
    sm: 576px,
    md: 768px,
    lg: 992px,
    xl: 1200px,
    xxl: 1400px,
);

// Usage
@include media-breakpoint-down(md) {
    // Mobile styles
}
```

## 🛠️ **Customization Guide**

### **Colors**

Update military theme colors in `abstracts/_variables.scss`:

```scss
:root {
    --military-olive-bg: rgba(183, 173, 132, 0.95);
    --military-text: #3a3a2e;
    --primary-color: #007bff;
}
```

### **Typography**

Font families and sizes are centralized:

```scss
:root {
    --font-family-base: 'Roboto', sans-serif;
    --font-family-monospace: 'Monaco', 'Menlo', monospace;
}
```

### **Spacing**

Consistent spacing system:

```scss
:root {
    --spacing-xs: 0.25rem; // 4px
    --spacing-sm: 0.5rem; // 8px
    --spacing-md: 1rem; // 16px
    --spacing-lg: 1.5rem; // 24px
    --spacing-xl: 2rem; // 32px
}
```

## 📋 **Development Guidelines**

### **Best Practices**

1. **Use semantic class names** - `.map-toolbar` instead of `.blue-box`
2. **Prefer CSS custom properties** over hardcoded values
3. **Follow mobile-first approach** - Design for mobile, enhance for desktop
4. **Use existing mixins** before creating new ones
5. **Test across breakpoints** - Ensure responsive behavior

### **Performance Considerations**

-   **Critical CSS** - Essential styles are loaded first
-   **Lazy loading** - Non-critical styles loaded as needed
-   **Webpack optimization** - Automatic minification and compression
-   **Image optimization** - AVIF format for better compression

## 🔍 **Troubleshooting**

### **Common Issues**

**Build Errors:**

```bash
# Clear cache and rebuild
rm -rf node_modules/.cache
yarn dev
```

**Image Not Loading:**

-   Check file exists in `assets/images/`
-   Verify import path in JavaScript
-   Ensure webpack.config.js includes image copying

**Style Not Applying:**

-   Check import order in `app.scss`
-   Verify CSS specificity
-   Use browser DevTools to debug

### **Debugging Tips**

1. **Check console** for JavaScript errors
2. **Use DevTools** to inspect CSS cascade
3. **Verify imports** in browser Network tab
4. **Test responsive** using device emulation

## 📚 **Resources**

-   [Sass 7-1 Pattern](https://sass-guidelin.es/#the-7-1-pattern)
-   [CSS Custom Properties](https://developer.mozilla.org/en-US/docs/Web/CSS/Using_CSS_custom_properties)
-   [Webpack Encore](https://symfony.com/doc/current/frontend.html)
-   [Bootstrap 5 Documentation](https://getbootstrap.com/docs/5.3/)
-   [Leaflet CSS API](https://leafletjs.com/reference.html#control)

## 🚀 **Production Deployment**

### **Build Process**

```bash
# Production build
yarn build

# Generated files
public/build/
├── app.css         # Compiled and minified CSS
├── app.js          # JavaScript bundle
├── images/         # Optimized images
└── manifest.json   # Asset mapping
```

### **Performance Optimizations**

-   **CSS minification** - Automatic in production
-   **Image compression** - AVIF format reduces size by 50%
-   **Cache busting** - Filenames include hash for proper caching
-   **Gzip compression** - Server-level compression recommended

---

## 📝 **Changelog**

**v1.0.0** - December 2024

-   ✅ Complete CSS architecture implementation
-   ✅ Background image solution
-   ✅ Responsive design system
-   ✅ Production-ready build process

---

**Maintainer:** Development Team  
**Last Updated:** December 2024  
**Architecture:** 7-1 Pattern SCSS
