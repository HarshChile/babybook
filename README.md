# TinyTales - Digital Baby Scrapbook & Timeline 🍼

A modern digital memory scrapbook designed for parents to capture, organize, and revisit their baby's growth—from the first smile to the first step.

Photos and media files are stored securely on disk in the `uploads/` directory, while metadata, timestamps, and pointers are managed in **MongoDB** (with local JSON file fallback) to build dynamic, chronological timelines.

---

## 🌟 Key Features

1. **Photo Upload & Memory Timelines (`addmemory.html`)**
   - Upload baby photos with title, date, category, and sentimental notes.
   - Photos are saved directly to disk (`uploads/photos/`), with pointers and metadata stored in database.
   - Dynamic chronological timeline view with filtering (Milestones, Daily, Celebrations) and sorting.
   - Memory preview modal with favorite toggling and deletion.

2. **Dashboard Overview (`dashboard.html`)**
   - Real-time user-scoped statistics: Total Memories, Journal Entries, Favorites, and Upcoming Events.
   - Recent memory timeline cards loaded dynamically from the backend for the authenticated user.
   - Personalized baby greeting and quick navigation.

3. **Milestone Tracker (`milestone.html`)**
   - Record baby developmental milestones (Social, Physical, Language).
   - Saved and retrieved strictly per user account.

4. **Scrapbook Calendar (`calendar.html`)**
   - Interactive monthly calendar with month-to-month navigation.
   - Highlights dates with photos and milestone badges.
   - Click any date to view memories or quickly record a new one for that day.

5. **Notebook Scrapbook (`scrapbook.html`)**
   - Nostalgic digital notebook with Polaroid-style photo cards, tape stickers, moods, and handwritten journal notes.
   - Page navigation and user-scoped clear album options.

6. **Time Capsule (`timecap.html`)**
   - Store heartfelt messages and media (photos, videos, audio) for the baby to open in the future.
   - Locks entries until the chosen unlock date. Sensitive messages and media are stripped on the server and never returned across the API while locked.

7. **Baby Profile Management (`profile.html`)**
   - Multi-child profile support: create, view, edit, and switch active baby profiles.
   - Baby's name, birth date, parent names, bio, and avatar photo upload.

8. **Settings & Account Security (`settings.html`)**
   - Email alerts, milestone reminders, and privacy controls.
   - Account security: change password with current password verification.

9. **Notifications System (`notification.html`)**
   - Private notifications for milestone achievements, memory additions, tips, and automatic unlocked time capsule alerts.
   - Mark as read, delete, filter, and clear all.

10. **Authentication & Password Recovery (`login.html`, `register.html`, `forgot.html`, `reset.html`)**
   - Secure registration with validation and bcrypt hashing.
   - Secure session management with session ID regeneration and HttpOnly cookies.
   - Cryptographically random time-limited password reset tokens with SHA256 hashed storage.

---

## 🏗️ Backend & Database Architecture

- **Backend**: Built with native **PHP 8.3** (clean scripts in `api/` with centralized auth middleware in `api/auth_middleware.php`).
- **Database**: **MongoDB** (`tinytales` database) using the standard PHP MongoDB extension (`MongoDB\Driver\Manager`).
- **Graceful Fallback**: If MongoDB connection is awaiting your Atlas link, the backend automatically uses a local JSON store in `data/` so the app remains fully functional!
- **Data Isolation**: Strict user-level ownership enforced at the database layer. No user ID or role from the browser is trusted; all operations derive identity from verified server session state.

---

## ⚙️ Configuration (.env)

Create or edit your `.env` file in the root directory (see `.env.example`):

```env
# MongoDB Connection String:
MONGODB_URI=mongodb://localhost:27017

# MongoDB Database Name:
MONGODB_DATABASE=tinytales

# Disk Storage Directory:
UPLOAD_DIR=uploads

# Server Port:
PORT=8000
```

---

## 🚀 Running the Project

1. **Option A: Quick Batch File (Windows)**
   Double-click `start_server.bat` in the project root (automatically performs integrity checks and starts the server).

2. **Option B: Command Line (PowerShell / Terminal)**
   ```powershell
   php migrate.php
   php -S localhost:8000
   ```

3. Open your browser and navigate to:
   - **Landing Page**: [http://localhost:8000/home.html](http://localhost:8000/home.html)
   - **Login**: [http://localhost:8000/login.html](http://localhost:8000/login.html)
   - **Dashboard**: [http://localhost:8000/dashboard.html](http://localhost:8000/dashboard.html)

---

## 📡 Backend API Endpoints

- `GET / POST /api/auth.php` - Register, login, session check (`action=me`), logout, forgot password, validate reset token, reset password, change password.
- `GET /api/stats.php` - Scoped dashboard counters and recent memories.
- `GET / POST /api/profile.php` - Multi-baby profile management (list, select active baby, create, update, delete).
- `GET / POST / DELETE /api/memories.php` - Scoped timeline, upload photo, toggle favorite, safe deletion.
- `GET / POST / DELETE /api/milestones.php` - Scoped baby developmental milestones.
- `GET / POST /api/calendar.php` - Scoped calendar events and entries by date.
- `GET / POST /api/scrapbook.php` - Scoped notebook Polaroid cards and clear scrapbook.
- `GET / POST / DELETE /api/capsules.php` - Scoped time capsules with server-enforced lock date protection.
- `GET / POST /api/notifications.php` - Scoped notifications, read status, and automatic unlocked capsule alerts.
- `GET / POST /api/settings.php` - Scoped preferences and privacy controls.

---

## 🧪 Automated Testing

Run the automated test suite covering authentication, two-user data isolation, and module regressions:

```powershell
php tests/run_tests.php
```

All 75 test cases pass successfully.
