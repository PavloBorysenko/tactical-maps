# Menu Refactoring Documentation

## Problem

The navigation menu was embedded directly in `base.html.twig`, which is a bad practice. This approach makes it difficult to maintain and customize different menus for different user roles.

## Solution

Extracted navigation menus into separate template files with role-based inclusion.

## New Structure

### 1. Admin Menu (`templates/partials/admin_menu.html.twig`)

**For users with ROLE_ADMIN:**

-   Dashboard link
-   Maps management link
-   Sides management link
-   User identification with admin badge
-   Logout link
-   Dark theme (navbar-dark bg-dark)
-   Responsive with Bootstrap collapse

### 2. User Menu (`templates/partials/user_menu.html.twig`)

**For guests and regular users:**

-   Login link only
-   Light theme (navbar-light bg-light)
-   Simple layout

### 3. Updated Base Template (`templates/base.html.twig`)

**Smart menu inclusion:**

```twig
{% if app.user and is_granted('ROLE_ADMIN') %}
    {% include 'partials/admin_menu.html.twig' %}
{% else %}
    {% include 'partials/user_menu.html.twig' %}
{% endif %}
```

## Benefits

### ✅ Better Architecture

-   **Separation of concerns**: Each menu has its own template
-   **Role-based UI**: Different menus for different user types
-   **Maintainability**: Easy to modify specific menus without affecting others
-   **Reusability**: Menu templates can be included in other templates if needed

### ✅ Admin Menu Features

-   **Complete navigation**: Dashboard, Maps, Sides
-   **Admin identification**: Clear admin badge with email
-   **Professional appearance**: Dark theme for admin interface
-   **Responsive design**: Works on mobile devices

### ✅ User Menu Features

-   **Simple and clean**: Only login option for guests
-   **Light theme**: Friendly appearance for public users
-   **Consistent branding**: Same brand logo and styling

## File Structure

```
templates/
├── partials/
│   ├── admin_menu.html.twig    # Admin navigation
│   └── user_menu.html.twig     # Guest/user navigation
├── base.html.twig              # Updated base template
└── ...
```

## Usage Examples

### Admin Experience

1. Login as admin → Dark navigation with Dashboard, Maps, Sides
2. Full management capabilities through top navigation
3. Clear admin identification in menu

### Guest Experience

1. Visit site → Light navigation with Login option only
2. Simple, non-intrusive interface
3. Clear path to authentication

## Testing

1. **Guest user**: Visit http://localhost:8000 → should see light menu with Login
2. **Admin user**: Login and visit any page → should see dark menu with full navigation
3. **Responsive**: Test on mobile devices → menus should collapse properly

## Future Enhancements

-   Add user menu for regular authenticated users (ROLE_USER)
-   Implement active menu highlighting
-   Add breadcrumb navigation
-   Consider adding user profile dropdown

## Migration Notes

-   Old navigation code removed from `base.html.twig`
-   All existing functionality preserved
-   No breaking changes to existing templates
-   Cache clear required after implementation
