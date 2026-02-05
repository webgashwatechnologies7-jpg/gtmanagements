# GTmanagement System

Team Management System with Laravel + Vue.js

## Project Structure

```
GTmanagement/
├── backend/          # Laravel API
├── frontend/         # Vue.js SPA
└── docs/            # Documentation
```

## Setup Instructions

### Backend (Laravel)
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

### Frontend (Vue.js)
```bash
cd frontend
npm install
npm run dev
```

## Admin / HR Access

Company admin should have **admin** role to see all data (users, teams, projects, tasks, leave, EODs, attendance, approvals) and to add users/teams, manage roles, etc.

**Assign admin role to a user (e.g. Pankaj):**
```bash
cd backend
php artisan tinker
```
```php
$user = \App\Models\User::where('email', 'pankaj@yourcompany.com')->first();
$adminRole = \App\Models\Role::where('slug', 'admin')->first();
$user->roles()->sync([$adminRole->id]);
```
Or by user ID: `\App\Models\User::find(1)->roles()->sync([\App\Models\Role::where('slug','admin')->first()->id]);`

After assigning, the user must **log out and log in again** (or refresh) so the app loads their roles.

## Development Status

- ✅ Phase 0: Project Setup & Foundation (Complete)
- ⏳ Phase 1: Authentication & Authorization (Next)

## Tech Stack

- **Backend:** Laravel 12
- **Frontend:** Vue.js 3 + Vite
- **Database:** MySQL
- **State Management:** Pinia
- **Routing:** Vue Router
