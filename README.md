# Hostel Maintenance and Complaint Tracker

NCSM301 Web Development Laboratory, Mini Project **P4**
(24JE0134, 24JE0312, 24JE0369)

> Create a helpdesk for complaints such as electrical, plumbing, network or housekeeping issues.
> Users submit and filter complaints, view their status, and an update interface moves them
> through Submitted, In Progress and Resolved with asynchronous status refresh.

**Stack:** HTML, CSS, JavaScript, jQuery 3.7.1, PHP (PDO) and MariaDB on Apache (standalone XAMPP).
Nothing else is used.

## Setup (same XAMPP configuration as Lab 9 and the Extra Lab)

1. Configure XAMPP as in the lab manuals: Apache on port **8080** (Lab 9 §3.2) and MariaDB on
   port **3306** (Extra Lab §4.1–4.3). Start **Apache** and **MySQL** from `xampp-control.exe`.
2. Open `http://localhost:8080/phpmyadmin/`, click the server name (so no database is selected),
   open the **SQL** tab, paste the contents of `setup.sql` and click **Go**.
3. Copy the whole `hostel_complaints` folder into `xampp\htdocs\`.
4. Open **http://localhost:8080/hostel_complaints/**.

If you change the port or database name, edit only `db.php`.
To reset the demo data, run `DROP DATABASE hostel_complaints;` and then `setup.sql` again.

### Demo accounts (password for all: `hostel123`)

| Login ID | Name | Role |
|---|---|---|
| `24JE9101` | Rahul Sharma (Amber Hostel, A-112) | student |
| `24JE9102` | Priya Nair (Rosaline Hostel, R-205) | student |
| `24JE9103` | Karan Mehta (Amber Hostel, A-318) | student |
| `24JE0134`, `24JE0312`, `24JE0369` | Team Member 1–3 (placeholder names, Jasper Hostel) | student |
| `warden` | Hostel Warden | staff (updates complaints) |
| `maintenance` | Maintenance Office | staff (updates complaints) |

## How the requirements are met

| Requirement | Where |
|---|---|
| Complaint categories (electrical, plumbing, network, housekeeping, ...) | `categories` table (`setup.sql`): Electrical, Plumbing, Network, Housekeeping, Furniture, Other. Colour badges in `styles.css`. |
| Users submit complaints | `new_complaint.php` form → `submit_complaint.php` (checked in `js/new_complaint.js`, checked again in PHP, then redirect). The location is filled in from the student's room. A second open complaint with the same title and category is refused. |
| Users filter complaints | GET form of drop-down filters on `index.php` (students: category, status) and `manage.php` (staff: category, status, priority). Choosing an option submits the form automatically (`js/filters.js`). |
| Users view their status | Every complaint shows a status pill, a three-step progress bar (Submitted → In Progress → Resolved), the latest staff remark and a full history on `complaint.php`. |
| Update interface moves complaints through Submitted, In Progress and Resolved | `manage.php` (and `complaint.php` for staff): **Start work** / **Mark resolved** buttons send **fetch + JSON** to `api/update_status.php`. Only one step forward is allowed, a remark is required when resolving, and every change is stored in `complaint_updates`. |
| Asynchronous status refresh | `js/refresh.js` asks `api/statuses.php` (GET, JSON) every 15 seconds, or when **Refresh status now** is clicked, and updates the pills, progress bars, remarks and summary counts without reloading. Staff also see a notice when new complaints arrive. |
| Two staff members updating the same complaint | `UPDATE ... WHERE status = :fromStatus` changes the row only if nobody has changed it first. The second request gets HTTP 409 and the page shows the real status. |

## Which lab concepts each part uses

| Lab | Concept | Used in |
|---|---|---|
| 2–3 | Headings, paragraphs, `div`, ordered/unordered lists, links, tables, forms, radio buttons, `fieldset`/`legend` | All pages |
| 4–5 | External stylesheet, class/id/attribute selectors, box model, `inline-block` layout, centred `.page-container`, media query | `styles.css` |
| 6 | External script before `</body>`, `let`/`const`, functions, `getElementById`, `querySelector`, `textContent`, `classList`, `addEventListener` (no `onclick` attributes) | `js/*.js` |
| 7 | Form validation on `submit` with `preventDefault`, `input` and `change` events, `trim`, specific messages | `js/login.js`, `js/new_complaint.js` (character counters) |
| 8 | `fetch` GET and POST, `.then`, `response.ok`, `response.json()`, `JSON.stringify`, `Content-Type: application/json`, `catch` / `finally`, buttons disabled while a request is pending, `aria-live="polite"`; jQuery `.on`, `.text`, `.val`, `.attr`, `.find`, `.show` / `.hide`, `.each` | `js/common.js`, `js/refresh.js`, `js/update.js` |
| 9 | PHP pages served by Apache, mixing HTML and PHP, `echo`, `if`/`else`, `foreach` | All `.php` files |
| 10 | GET for filters, POST for changes, `isset`/`trim` validation, `htmlspecialchars`, hidden inputs, `session_start`, `$_SESSION`, logout with `session_unset` + `session_destroy`, redirect after POST | `index.php`, `manage.php`, `process_login.php`, `submit_complaint.php`, `logout.php` |
| Extra | MariaDB tables with primary/foreign keys, `CHECK`, `JOIN`, PDO in `db.php`, prepared statements with named placeholders, `try` / `catch` | `setup.sql`, every PHP file that queries |

A few standard functions beyond the manuals are used:

- **For security:** `password_hash` / `password_verify` (passwords are never stored in plain text), `session_regenerate_id` (new session ID at login), and a random per-session token checked on every change (`random_bytes`, `hash_equals`).
- **For JSON:** `json_decode` / `json_encode` with `php://input`, to read and answer the fetch requests.
- **For saving related rows together:** a database transaction (`beginTransaction` / `commit` / `rollBack`), so a complaint and its history row are always stored as a pair.
- **For the repeating refresh:** JavaScript's built-in `setInterval` (and `setTimeout` for the short highlight).

## Files

```
hostel_complaints/
├── EXPLANATION.md         detailed explanation of how everything works
├── setup.sql              database, tables and demo data
├── db.php                 PDO connection (port 3306, as in the Extra Lab)
├── auth.php               session start, login/role checks, request token, flash messages
├── functions.php          status order, summary counts, progress bar, staff buttons, JSON helpers
├── header.php / footer.php     shared page top and bottom
├── login.php              login form
├── process_login.php      checks the login (POST) and stores the user in the session
├── logout.php             ends the session
├── index.php              student: my complaints with filters (GET)
├── new_complaint.php      student: complaint form
├── submit_complaint.php   validates and saves a new complaint (POST)
├── complaint.php          details and full history of one complaint
├── manage.php             staff: all complaints, filters and the update interface
├── api/
│   ├── statuses.php       JSON (GET): current status of the complaints on a page
│   └── update_status.php  JSON (POST): move a complaint to its next status (staff only)
├── js/
│   ├── common.js          getJson() / postJson() fetch helpers, applyComplaintState()
│   ├── refresh.js         asynchronous status refresh every 15 seconds
│   ├── update.js          staff status buttons
│   ├── filters.js         filter drop-downs submit the form
│   ├── new_complaint.js   complaint form validation and counters
│   └── login.js           login form validation
└── styles.css
```

## Database design

```
users (user_id PK, login_id UNIQUE, full_name, password_hash, role, hostel, room_no)
categories (category_id PK, name UNIQUE)
complaints (complaint_id PK, user_id FK -> users, category_id FK -> categories, title,
            description, location, priority, status, created_at, updated_at, resolved_at)
complaint_updates (update_id PK, complaint_id FK -> complaints, old_status, new_status,
                   remark, updated_by FK -> users, updated_at)
```

`CHECK` constraints limit `status` to Submitted / In Progress / Resolved and `priority` to
Low / Medium / High. `complaints` holds the current state; `complaint_updates` keeps every step,
which is what the history on `complaint.php` shows.

## Demo walkthrough

1. Log in as `24JE9101` in one browser window. The summary shows how many complaints are
   Submitted, In Progress and Resolved. Click **+ New Complaint**, try to submit the empty form to
   see validation, then submit a real complaint.
2. Log in as `maintenance` in a **private window** (a second session). The new complaint is at the top
   of **All Complaints**. Type a remark and click **Start work**. The row changes without a reload
   (DevTools → Network shows the JSON request and response).
3. Go back to the student window and wait up to 15 seconds (or click **Refresh status now**).
   The card turns blue, the progress bar moves and the staff remark appears, all without reloading.
4. In the staff window, click **Mark resolved** without a remark (refused), then add one and resolve.
   Open the complaint to see its history.
5. Try the filters (status, category, priority). They are ordinary GET requests, so the
   filtered URL can be bookmarked.
