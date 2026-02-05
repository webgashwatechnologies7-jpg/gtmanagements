# Hostinger pe GTmanagement Deploy – Sabse Simple Tarika

**Ek hi website, ek hi domain** – API bhi wahi, Vue app bhi wahi. Bas ye steps follow karo.

---

## Overview (Kya hoga)

1. Hostinger pe **ek** "Custom PHP/HTML" website add karoge.
2. GitHub se code clone karoge (ya upload).
3. MySQL database banaoge.
4. Backend (Laravel) setup karoge – .env, composer, migrate.
5. Vue ki build ki files **backend/public** me daaloge – isi se site chalegi.
6. Document root **backend/public** set karoge.

Uske baad **yourdomain.com** pe app chalegi: homepage Vue, `/api/*` Laravel.

---

## Step 1: Hostinger pe Website Add karo

1. **hPanel** kholo → left side **Websites** → **Websites list**.
2. Top right **"+ Add website"** (purple button) pe click karo.
3. Dropdown me **"Custom PHP/HTML website"** choose karo  
   (_Upload your code or install other application_).
4. Domain select karo:
   - Agar pehle se domain connect hai to woh choose karo, **ya**
   - **Temporary domain** use karo (e.g. `something.hostingersite.com`).
5. Create/Continue karo – website add ho jayegi.

---

## Step 2: MySQL Database Banao

1. hPanel me **Databases** (left sidebar) → **MySQL Databases**.
2. **Create new database** – name: `gtmanagement` (ya koi bhi).
3. **Create user** – username + strong password set karo, dono **note kar lo**.
4. User ko is database pe **All privileges** assign karo (Add user to database).
5. Baad me ye **DB_DATABASE**, **DB_USERNAME**, **DB_PASSWORD** backend `.env` me daalenge.

---

## Step 3: Code Hostinger pe Lana

### Option A: Git se (recommended)

1. hPanel → **Advanced** → **Git** (agar option hai).
2. **Add repository** / **Create**:
   - **URL**: `https://github.com/webgashwatechnologies7-jpg/gtmanagements.git`
   - **Branch**: `main`
   - **Deploy path**: jahan project rahega, e.g. `public_html/gtmanagement`
3. **Deploy** / **Pull** karo – code aa jayega.

### Option B: File Manager se upload

1. **Websites** → apni site → **File Manager**.
2. `public_html` ke andar folder banao: `gtmanagement`.
3. Apne PC se **saari** project files (backend, frontend, .gitignore, README) `gtmanagement` me upload karo.  
   **Mat upload karo**: `backend/vendor`, `frontend/node_modules`, `frontend/dist`.

---

## Step 4: Backend (Laravel) Setup

Hostinger pe **Terminal** (SSH ya in-panel Terminal) kholo. Path apna adjust karo (jaise `public_html/gtmanagement`).

```bash
cd public_html/gtmanagement/backend
# ya: cd domains/yourdomain.com/gtmanagement/backend

# 1. .env banao
cp .env.example .env

# 2. .env edit karo (nano/vi ya File Manager se)
#    Set: APP_ENV=production, APP_DEBUG=false, APP_URL=https://yourdomain.com
#         DB_DATABASE=gtmanagement, DB_USERNAME=..., DB_PASSWORD=...
#         DB_HOST=localhost

# 3. Composer (PHP 8.1+ chahiye)
composer install --no-dev --optimize-autoloader

# 4. Laravel key
php artisan key:generate

# 5. Storage link
php artisan storage:link

# 6. Database tables
php artisan migrate --force

# 7. Cache
php artisan config:cache
php artisan route:cache
```

**.env** me ye zaroor daalo:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://yourdomain.com` (apna domain)
- `DB_CONNECTION=mysql`
- `DB_HOST=localhost`
- `DB_DATABASE=gtmanagement` (jo banaya)
- `DB_USERNAME=` (jo user banaya)
- `DB_PASSWORD=` (jo password diya)

---

## Step 5: Vue Build – Apne PC pe

Vue app ko **production API URL** ke sath build karke **backend/public** me daalna hai.

**Apne computer pe:**

1. **API URL set karo** (jahan site chalegi):
   - `frontend` folder me file banao: **`.env.production`**
   - Andar ek line:  
     `VITE_API_URL=https://yourdomain.com/api`  
     (apna domain daalo – temporary domain ho to `https://xxxx.hostingersite.com/api`)

2. **Build karo:**
   ```bash
   cd frontend
   npm install
   npm run build
   ```

3. **Build ki files copy karo:**
   - `frontend/dist` ke andar **saari** cheezein (jaise `index.html` aur `assets` folder) **copy** karo.
   - Inhe **Hostinger** pe `gtmanagement/backend/public/` me **upload** karo.  
     Matlab: `backend/public/index.html` aur `backend/public/assets/` (puri folder) wahan honi chahiye.

**Important:** Laravel ka `index.php` `backend/public` me rehna chahiye – Vue ka `index.html` bhi wahi pe hoga. Dono saath rahenge; .htaccess pehle se set hai – `/api` Laravel ko jayega, baaki Vue ko.

---

## Step 6: Document Root Set karo

1. hPanel → **Websites** → apni website → **Manage** / **Advanced**.
2. **Document root** dhundho (kuch plans me "Document root" ya "Public directory").
3. Isko change karo:
   - **Set to:** `public_html/gtmanagement/backend/public`  
     (agar code `public_html/gtmanagement` me hai; path apna structure dekh ke adjust karo.)
4. Save karo.

Ab **yourdomain.com** open karoge to Vue app dikhegi, aur **yourdomain.com/api/...** Laravel API hoga.

---

## Step 7: Permissions

Terminal me:

```bash
cd public_html/gtmanagement/backend
chmod -R 755 storage bootstrap/cache
```

---

## Step 8: SSL (HTTPS)

1. hPanel → **SSL**.
2. Apne domain ke liye **Free SSL** (Let’s Encrypt) enable karo.
3. `.env` me `APP_URL` ko `https://yourdomain.com` karo.

---

## Short Checklist

| # | Kaam |
|---|------|
| 1 | "+ Add website" → **Custom PHP/HTML website** |
| 2 | MySQL database + user banao, credentials note karo |
| 3 | Git se clone (ya File Manager se upload) → `gtmanagement` folder |
| 4 | Backend: .env, composer install, key:generate, storage:link, migrate, config:cache |
| 5 | PC pe: Vue build (VITE_API_URL=https://yourdomain.com/api), dist ki files backend/public me upload |
| 6 | Document root = .../gtmanagement/backend/public |
| 7 | storage + bootstrap/cache = 755 |
| 8 | SSL on, APP_URL = https://... |

---

## Agar Error Aaye

- **500 / white screen**: `backend/storage/logs/laravel.log` dekho; `storage` aur `bootstrap/cache` permissions 755 karo.
- **Login/API kaam nahi kar raha**: Browser console + Network tab me dekho; `.env` me `APP_URL` aur frontend me `VITE_API_URL` dono **https** aur sahi domain hona chahiye.
- **Page refresh pe 404**: Document root **backend/public** hai na? Aur Vue ki **index.html** aur **assets** folder `backend/public` me honi chahiye.

---

Ye **best way** hai: ek hi Hostinger website, ek hi domain, bina subdomain ke. API aur frontend dono same domain pe chalenge.
