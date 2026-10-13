# TinyTales - Digital Baby Scrapbook & Timeline 🍼

A modern digital memory scrapbook designed for parents to capture, organize, and revisit their baby's growth—from the first smile to the first step.

Photos and media files are stored securely on disk in the `uploads/` directory, while metadata, timestamps, and pointers are managed in **MongoDB** to build dynamic, chronological timelines.

---

## 🌟 Key Features

1. **Photo Upload & Memory Timelines (`addmemory.html`)**
   - Upload baby photos with title, date, category, and sentimental notes.
   - Photos are saved directly to disk (`uploads/photos/`), with pointers and metadata stored in MongoDB.
   - Dynamic chronological timeline view with filtering (Milestones, Daily, Celebrations) and sorting (Newest first / Timeline order).
   - Memory preview modal with favorite toggling and deletion.

2. **Dashboard Overview (`dashboard.html`)**
   - Real-time statistics: Total Memories, Journal Entries, Favorites, and Upcoming Events.
   - Recent memory timeline cards loaded dynamically from the backend.
   - Personalized baby greeting and quick navigation.

3. **Milestone Tracker (`milestone.html`)**
   - Record baby developmental milestones (Social, Physical, Language).
   - Saved and retrieved via MongoDB API.

4. **Scrapbook Calendar (`calendar.html`)**
   - Interactive monthly calendar with month-to-month navigation.
   - Highlights dates with photos and milestone badges.
   - Click any date to view memories or quickly record a new one for that day.

5. **Notebook Scrapbook (`scrapbook.html`)**
   - Nostalgic digital notebook with Polaroid-style photo cards, tape stickers, moods, and handwritten journal notes.
   - Page navigation and clear album options.

6. **Time Capsule (`timecap.html`)**
   - Store heartfelt messages and media (photos, videos, audio) for the baby to open in the future.
   - Locks entries until the chosen unlock date.

7. **Baby Profile (`profile.html`)**
   - Baby's name, birth date, parent names, bio, and avatar photo upload.

8. **Settings & Notifications (`settings.html`, `notification.html`)**
   - Email alerts, milestone reminders, and privacy controls.

---

## 🏗️ Backend & Database Architecture

- **Backend**: Built with native **PHP 8.3** (clean, lightweight scripts in `api/` without unnecessary framework overhead).
- **Database**: **MongoDB** (`tinytales` database) using the standard PHP MongoDB extension (`MongoDB\Driver\Manager`).
- **Disk Storage**: Photos and media are uploaded to the `uploads/` folder on disk, and MongoDB stores the file path pointers.
- **Graceful Fallback**: If MongoDB connection is awaiting your Atlas link, the backend automatically uses a local JSON store in `data/` so the app remains fully functional while you configure your database connection string!

---

## ⚙️ Configuration (.env)

Create or edit your `.env` file in the root directory:

```env
# MongoDB Connection String:
# For local MongoDB:
MONGODB_URI=mongodb://localhost:27017

# For MongoDB Atlas (Cluster):
# MONGODB_URI=mongodb+srv://<username>:<password>@cluster0.abcde.mongodb.net/?retryWrites=true&w=majority

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
   Double-click `start_server.bat` in the project root.

2. **Option B: Command Line (PowerShell / Terminal)**
   ```powershell
   php -S localhost:8000
   ```

3. Open your browser and navigate to:
   - **Landing Page**: [http://localhost:8000/home.html](http://localhost:8000/home.html)
   - **Dashboard**: [http://localhost:8000/dashboard.html](http://localhost:8000/dashboard.html)
   - **Timeline / Memories**: [http://localhost:8000/addmemory.html](http://localhost:8000/addmemory.html)

---

## 📡 Backend API Endpoints

- `GET /api/stats.php` - Dashboard counters and recent memory items.
- `GET / POST /api/memories.php` - Fetch timeline, upload photo to disk & save to MongoDB, toggle favorite, delete.
- `GET / POST /api/milestones.php` - List and save baby milestones.
- `GET / POST /api/calendar.php` - Fetch month events, add calendar memory.
- `GET / POST /api/scrapbook.php` - Retrieve scrapbook pages, add Polaroid photo notes.
- `GET / POST /api/capsules.php` - Retrieve time capsules, save future locked messages.
- `GET / POST /api/profile.php` - Get and update baby profile.
- `GET / POST /api/settings.php` - Get and save user preferences.
- `GET / POST /api/auth.php` - Register, login, check session, logout.
