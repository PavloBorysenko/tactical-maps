# Tactical Maps Admin Panel

Authentication system for managing tactical maps is properly configured using existing templates!

## Created Components

### 1. Authentication

-   **User Entity** (`src/Entity/User.php`) - user entity with roles
-   **SecurityController** (`src/Controller/SecurityController.php`) - login/logout controller
-   **AppCustomAuthAuthenticator** (`src/Security/AppCustomAuthAuthenticator.php`) - authenticator
-   **Login Form** (`templates/security/login.html.twig`) - beautiful login form

### 2. Admin Panel

-   **AdminController** (`src/Controller/AdminController.php`) - dashboard only
-   **Dashboard** (`templates/admin/dashboard.html.twig`) - navigation to existing templates

### 3. Used Existing Templates

-   **templates/map/** - map management (index, new, edit, show)
-   **templates/side/** - side management (index, new, edit, show)
-   **templates/geo_object/** - geo object management

### 4. Security

-   **Security.yaml** - protect `/admin/*` only for `ROLE_ADMIN`
-   **Automatic redirect** after login to admin panel
-   **CSRF protection** in forms

## System Status

### ✅ Correct Architecture:

-   **Authentication**: Login/logout for administrators
-   **Dashboard**: Navigation to existing templates
-   **Map Management**: Uses templates/map/
-   **Side Management**: Uses templates/side/
-   **Security**: Admin routes protection
-   **No Duplication**: Removed redundant templates

### 🔧 Fixed Issues:

-   **Template Duplication**: Removed redundant templates from admin/
-   **Correct Structure**: Now uses existing templates
-   **Simplified AdminController**: Only dashboard method
-   **Correct Navigation**: Links to existing routes

## Usage

### Create Administrator

```bash
php bin/console app:create-admin
```

### System Access

1. **Login Form**: `http://localhost:8000/login`
2. **Admin Panel**: `http://localhost:8000/admin` (dashboard)
3. **Map Management**: `http://localhost:8000/admin/maps/` (existing templates)
4. **Side Management**: `http://localhost:8000/admin/sides/` (existing templates)
5. **Logout**: `http://localhost:8000/logout`

### Access Rights

-   **Public Access**: homepage, map viewing
-   **ROLE_ADMIN**: admin panel access and management

## Admin Panel Functions

### Dashboard

-   Navigation to existing management templates
-   Quick access to:
    -   Map list (map_index)
    -   Create map (map_new)
    -   Side list (side_index)
    -   Create side (side_new)

### Map Management (existing templates)

-   **templates/map/index.html.twig** - map list
-   **templates/map/new.html.twig** - create map
-   **templates/map/edit.html.twig** - edit map
-   **templates/map/show.html.twig** - view map

### Side Management (existing templates)

-   **templates/side/index.html.twig** - side list
-   **templates/side/new.html.twig** - create side
-   **templates/side/edit.html.twig** - edit side
-   **templates/side/show.html.twig** - view side

## Security Configuration

### Protected Routes

```yaml
access_control:
    - { path: ^/admin, roles: ROLE_ADMIN }
    - { path: ^/login, roles: PUBLIC_ACCESS }
    - { path: ^/, roles: PUBLIC_ACCESS }
```

### User Roles

-   `ROLE_USER` - basic role (automatic)
-   `ROLE_ADMIN` - admin panel access

## Commands

### Create Administrator

```bash
php bin/console app:create-admin
```

### Clear Cache

```bash
php bin/console cache:clear
```

### Update Database

```bash
php bin/console doctrine:schema:update --force
```

## Run Server

```bash
php -S localhost:8000 -t public
```

## Correct File Structure

```
src/
├── Controller/
│   ├── AdminController.php          # Dashboard only
│   ├── SecurityController.php       # Login/logout
│   ├── MapController.php           # Map management (existing)
│   └── SideController.php          # Side management (existing)
├── Command/
│   └── CreateAdminCommand.php       # Create admin
├── Entity/
│   └── User.php                     # User
└── Security/
    └── AppCustomAuthAuthenticator.php

templates/
├── admin/
│   └── dashboard.html.twig          # Dashboard only
├── security/
│   └── login.html.twig             # Login form
├── map/                            # Existing map templates
│   ├── index.html.twig
│   ├── new.html.twig
│   ├── edit.html.twig
│   └── show.html.twig
├── side/                           # Existing side templates
│   ├── index.html.twig
│   ├── new.html.twig
│   ├── edit.html.twig
│   └── show.html.twig
└── geo_object/                     # Existing geo object templates
    ├── _list.html.twig
    └── _form.html.twig

config/packages/
└── security.yaml                   # Security configuration
```

## Available Routes

### Admin

-   `admin_dashboard` - admin dashboard

### Maps (existing)

-   `map_index` - map list
-   `map_new` - create map
-   `map_edit` - edit map
-   `map_show` - view map

### Sides (existing)

-   `side_index` - side list
-   `side_new` - create side
-   `side_edit` - edit side
-   `side_show` - view side

### Authentication

-   `app_login` - login
-   `app_logout` - logout

## Created Administrator

-   **Email**: pavlobrysenko@gmail.com
-   **Password**: [entered during creation]
-   **Role**: ROLE_ADMIN

**🎉 System is properly structured!** Admin panel uses existing templates without functionality duplication.
