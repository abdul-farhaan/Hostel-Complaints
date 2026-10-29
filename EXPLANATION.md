# How the Hostel Maintenance and Complaint Tracker Works

A step-by-step explanation in simple language.

NCSM301 Web Development Laboratory, Mini Project P4 (24JE0134, 24JE0312, 24JE0369)

---

## Contents

1. [What the project does](#1-what-the-project-does)
2. [The big picture: four parts working together](#2-the-big-picture-four-parts-working-together)
3. [The files and what each one is for](#3-the-files-and-what-each-one-is-for)
4. [The database](#4-the-database)
5. [Connecting PHP to the database (`db.php`)](#5-connecting-php-to-the-database-dbphp)
6. [Logging in, sessions and roles](#6-logging-in-sessions-and-roles)
7. [My Complaints page (`index.php`)](#7-my-complaints-page-indexphp)
8. [Submitting a complaint (normal form + POST)](#8-submitting-a-complaint-normal-form--post)
9. [The staff update interface (`manage.php`)](#9-the-staff-update-interface-managephp)
10. [Moving a complaint to the next status (fetch POST + JSON)](#10-moving-a-complaint-to-the-next-status-fetch-post--json)
11. [Asynchronous status refresh (fetch GET + JSON)](#11-asynchronous-status-refresh-fetch-get--json)
12. [The complaint details and history page (`complaint.php`)](#12-the-complaint-details-and-history-page-complaintphp)
13. [Where jQuery is used](#13-where-jquery-is-used)
14. [How the page is styled (`styles.css`)](#14-how-the-page-is-styled-stylescss)
15. [Security: how the project protects itself](#15-security-how-the-project-protects-itself)
16. [Complete journey of one complaint](#16-complete-journey-of-one-complaint)
17. [Likely viva questions and answers](#17-likely-viva-questions-and-answers)
18. [Glossary](#18-glossary)

---

## 1. What the project does

When something breaks in a hostel (a fan, a tap, the Wi-Fi), students usually tell the warden in
person or write it in a register. The complaint is easy to forget, and the student never knows
whether anyone is working on it.

This project is an online helpdesk that solves that problem.

**Students can:**

- log in with their admission number;
- submit a complaint with a category (Electrical, Plumbing, Network, Housekeeping, Furniture,
  Other), a short title, the location, a priority and the details;
- see all their complaints, each with its current status and a progress bar;
- filter their complaints by category and status;
- see the latest remark from the staff (for example "Electrician will come at 4 PM");
- open a complaint to see its full history;
- **watch the status change by itself**: the page checks every 15 seconds and updates without
  reloading.

**Staff (warden, maintenance office) can:**

- see every complaint from every student, with the most urgent work first;
- filter by status, category and priority;
- move a complaint one step forward, **Submitted → In Progress → Resolved**, with a remark. The
  change happens without reloading the page;
- see when new complaints arrive while their page is open.

---

## 2. The big picture: four parts working together

```
   YOUR COMPUTER                              THE SERVER (XAMPP)
 ┌──────────────────────┐   HTTP request   ┌────────────┐      ┌────────┐      ┌──────────┐
 │       Browser        │ ───────────────> │   Apache   │ ───> │  PHP   │ ───> │ MariaDB  │
 │ HTML + CSS + JS +    │                  │ web server │      │ program│ SQL  │ database │
 │ jQuery               │ <─────────────── │  port 8080 │ <─── │        │ <─── │ port 3307│
 └──────────────────────┘  HTML or JSON    └────────────┘      └────────┘      └──────────┘
```

| Part | Where it runs | Its job in this project |
|---|---|---|
| **HTML** | Browser | The structure: forms, complaint cards, the staff table, the history list |
| **CSS** | Browser | The look: status colours, progress bar, badges, layout on phones |
| **JavaScript + jQuery** | Browser | The behaviour: checking forms, fetch requests, refreshing statuses every 15 seconds |
| **Apache** | Server | Receives requests from the browser and hands `.php` files to PHP |
| **PHP** | Server | Checks who is logged in, validates data, talks to the database, builds HTML or JSON |
| **MariaDB** | Server | Stores everything permanently: users, complaints, every status change |

**Important idea (from Lab 9):** the browser never sees PHP code. PHP runs on the server and sends
back only the result: an HTML page, or JSON data for fetch requests.

**Three kinds of requests are used:**

1. **Normal page requests.** Opening a page, logging in, submitting a complaint or using the
   filters. The **whole page reloads**.
2. **fetch POST with JSON.** A staff member clicks *Start work* or *Mark resolved*. JavaScript
   sends a small JSON message, gets JSON back and **changes only that row**.
3. **fetch GET with JSON, repeated every 15 seconds.** The page quietly asks the server "what is
   the status of these complaints now?" and updates whatever has changed. This is the
   **asynchronous status refresh** that the problem statement asks for.

---

## 3. The files and what each one is for

```
hostel_complaints/
├── setup.sql              creates the database, tables and demo data
├── db.php                 connects PHP to MariaDB
├── auth.php               sessions, "is this user logged in / staff?", token, flash messages
├── functions.php          small helpers used by many files
├── header.php             top of every page (side menu)
├── footer.php             bottom of every page
├── login.php              the login form
├── process_login.php      checks the ID and password
├── logout.php             ends the session
├── index.php              student: My Complaints (with filters)
├── new_complaint.php      student: the complaint form
├── submit_complaint.php   checks and saves a new complaint
├── complaint.php          one complaint with its full history
├── manage.php             staff: all complaints + update buttons
├── api/statuses.php       answers the 15-second refresh with JSON
├── api/update_status.php  moves a complaint to its next status, answers with JSON
├── js/common.js           code shared by the other scripts
├── js/refresh.js          the 15-second refresh
├── js/update.js           the staff Start work / Mark resolved buttons
├── js/filters.js          drop-downs submit the filter form straight away
├── js/new_complaint.js    checks the complaint form, shows character counters
├── js/login.js            checks the login form
└── styles.css             all styling
```

**Pages vs. API files.** Files in the main folder produce **HTML pages**. Files in `api/` produce
only **JSON**. They are never opened directly; JavaScript calls them with fetch.

**Why `header.php`, `footer.php`, `auth.php`, `functions.php`?** Every page needs the same menu,
the same login checks and the same helpers. Writing them once and including them with `require`
means a change is made in one place only.

---

## 4. The database

The database `hostel_complaints` has four tables.

### 4.1 `users`: who can log in

| Column | Meaning |
|---|---|
| `user_id` | Number that identifies the user (primary key, `AUTO_INCREMENT`) |
| `login_id` | Admission number or staff ID, e.g. `24JE9101` or `warden` (`UNIQUE`) |
| `full_name` | Name shown on the pages |
| `password_hash` | The password, scrambled with `password_hash()` (never the real password) |
| `role` | `student` or `staff` (a `CHECK` rule allows nothing else) |
| `hostel`, `room_no` | Where a student lives (empty for staff). Used to fill in the location |

### 4.2 `categories`: the kinds of problems

Electrical, Plumbing, Network, Housekeeping, Furniture, Other. Keeping them in a table (instead of
writing them in the HTML) means a new category can be added with one `INSERT`, and every drop-down
list shows it straight away. This is the same idea as the `topics` table in the Extra Lab.

### 4.3 `complaints`: the current state of each complaint

| Column | Meaning |
|---|---|
| `complaint_id` | The complaint number shown as `#5` (primary key) |
| `user_id` | Which student submitted it (foreign key → `users`) |
| `category_id` | Which category (foreign key → `categories`) |
| `title`, `description`, `location` | What the student typed |
| `priority` | `Low`, `Medium` or `High` (`CHECK` rule) |
| `status` | `Submitted`, `In Progress` or `Resolved` (`CHECK` rule), starts as `Submitted` |
| `created_at` | When it was submitted |
| `updated_at` | When the status last changed |
| `resolved_at` | When it was resolved (empty until then) |

### 4.4 `complaint_updates`: the history

One row for every step a complaint has taken.

| Column | Meaning |
|---|---|
| `update_id` | Primary key |
| `complaint_id` | Which complaint (foreign key → `complaints`) |
| `old_status`, `new_status` | For example `Submitted` → `In Progress`. `old_status` is empty for the very first row, written when the student submits |
| `remark` | What the staff member wrote, e.g. "Plumber will visit in the afternoon" |
| `updated_by` | Who made the change (foreign key → `users`) |
| `updated_at` | When |

**Why two tables for one complaint?** `complaints` answers "what is the status **now**?" quickly.
`complaint_updates` answers "what happened, and when?". The "latest staff remark" shown on each card
is simply the newest `remark` in the history.

### 4.5 How the tables are linked

```
users ──< complaints >── categories
  │           │
  │           └──< complaint_updates
  └─────────────────< (updated_by)
```

`──<` means "one to many": one user has many complaints, one complaint has many history rows.
Because these are **foreign keys**, MariaDB refuses a complaint with a category that does not exist
(just like the Extra Lab refuses a registration for topic 99).

### 4.6 Demo data that stays fresh

`setup.sql` uses `NOW() - INTERVAL 2 DAY` instead of fixed dates, so whenever the script is run
the demo complaints look recent ("submitted today", "3 days ago").

---

## 5. Connecting PHP to the database (`db.php`)

This file is the same as the Extra Lab's `db.php`, with only the database name changed:

```php
$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,      // errors become exceptions (try/catch)
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // rows look like $row["title"]
    PDO::ATTR_EMULATE_PREPARES => false,              // real prepared statements
]);
```

Two extra lines make PHP and MariaDB agree on the time zone (India), so "submitted at 4:10 PM"
is the same everywhere:

```php
date_default_timezone_set("Asia/Kolkata");
$pdo->exec("SET time_zone = '+05:30'");
```

**Prepared statements everywhere.** Whenever a value comes from the user, the SQL has a
placeholder such as `:complaintId` and the value is given separately to `execute()`. The user can
never change the SQL itself, which stops SQL injection (Extra Lab §6.2).

---

## 6. Logging in, sessions and roles

### 6.1 Why sessions are needed

HTTP forgets everything between requests (Lab 10 §6). After logging in, the server must remember
*who you are* on every following page. PHP's session does this: the browser keeps a small cookie
with the session ID, and the server keeps `$_SESSION["userId"]`, `$_SESSION["role"]` and so on.

### 6.2 `auth.php`: included at the top of every page

| Function | What it does |
|---|---|
| `session_start()` | Starts or resumes the session (runs as soon as the file is included) |
| `is_logged_in()` | `true` if `$_SESSION["userId"]` exists |
| `is_staff()` | `true` if the logged-in user's role is `staff` |
| `require_login()` | Not logged in → redirect to `login.php` |
| `require_student()` | Staff → redirect to `manage.php` (student pages are for students) |
| `require_staff()` | Not staff → "Only hostel staff can open this page" (HTTP 403) |
| `token_is_valid($token)` | Checks the secret token sent with every change (see §15) |
| `set_flash()` / `take_flash()` | A one-time message for the next page, e.g. "Complaint #8 has been submitted" |

### 6.3 The login flow step by step

1. `login.php` shows a form with `method="post"` and `action="process_login.php"`.
2. `js/login.js` stops the form if a field is empty (Lab 7).
3. `process_login.php` reads `$_POST["loginId"]` and `$_POST["password"]`, trims them and checks
   they are not empty (Lab 10, validate again on the server).
4. It finds the user with a prepared `SELECT ... WHERE login_id = :loginId`.
5. `password_verify()` compares the typed password with the stored hash.
6. If it is wrong: flash message and back to `login.php` (the typed ID is kept).
7. If it is right: `session_regenerate_id(true)` gives a fresh session ID, then the user's
   details, a random token and the room text ("Amber Hostel, Room A-112") are stored in
   `$_SESSION`.
8. Students are sent to `index.php`, staff to `manage.php`.

### 6.4 Logging out (`logout.php`)

Exactly the Lab 10 pattern: `session_start()`, `session_unset()`, `session_destroy()`, redirect.

---

## 7. My Complaints page (`index.php`)

### 7.1 Reading the filters from the URL (GET)

The filter form uses `method="get"`, so a filtered page has a URL such as:

```
index.php?category=1&status=In+Progress
```

PHP reads the values from `$_GET` with the Lab 10 pattern
(`isset($_GET["category"]) ? (int) $_GET["category"] : 0`). `(int)` turns the category into a number.
`status` must be one of the three real statuses, otherwise it is ignored.

### 7.2 Building the query from the filters

The page starts with one condition, "only my complaints", and adds one condition for each filter
that was used:

```php
$conditions = ["c.user_id = :userId"];
$params = [":userId" => $userId];

if ($categoryId > 0) {
    $conditions[] = "c.category_id = :categoryId";
    $params[":categoryId"] = $categoryId;
}
```

Then the conditions are joined with `AND`. Only fixed SQL text is joined; every value that comes
from the browser still goes through a placeholder.

### 7.3 The main query

```sql
SELECT c.complaint_id, c.title, ..., cat.name AS category,
       (SELECT h.remark FROM complaint_updates h
        WHERE h.complaint_id = c.complaint_id AND h.remark IS NOT NULL
        ORDER BY h.update_id DESC LIMIT 1) AS last_remark
FROM complaints c
JOIN categories cat ON c.category_id = cat.category_id
WHERE c.user_id = :userId AND ...
ORDER BY c.created_at DESC
```

- The `JOIN` replaces the category number with its name (Extra Lab §3.1).
- The small query in brackets (a *subquery*) fetches the newest remark from the history.

### 7.4 The summary tiles

`get_summary()` in `functions.php` counts the complaints in one query:

```sql
SELECT COUNT(*) AS total,
       SUM(status = 'Submitted') AS submitted,
       SUM(status = 'In Progress') AS inProgress,
       SUM(status = 'Resolved') AS resolved
FROM complaints WHERE user_id = :userId
```

`status = 'Submitted'` is 1 when true and 0 when false, so `SUM` counts the matching rows. Each tile
is a link that filters the list, e.g. `index.php?status=Resolved`.

### 7.5 Each complaint card

```html
<article class="complaint-card tracked-complaint" id="complaint-5"
         data-complaint-id="5" data-status="In Progress">
    #5  [Furniture]  Low priority                    (In Progress)
    Broken study chair
    Rosaline Hostel, Room R-205
    One leg of the study chair is cracked ...
    ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  progress bar
    Submitted        In Progress        Resolved
    Latest update from staff: A replacement chair has been requested ...
    Submitted ... · Last updated ... · View history
</article>
```

- `class="tracked-complaint"` and `data-complaint-id` tell `refresh.js` which complaints to check.
- `data-status` remembers the status the page is showing, so `refresh.js` can notice a change.
- The progress bar comes from `print_progress()` in `functions.php`: steps before the current one
  get the class `step-done` (green), the current one `step-current` (blue), and the rest stay grey.
  When a complaint is resolved, all three steps are green.
- Every value from the database is printed with `htmlspecialchars()`.

---

## 8. Submitting a complaint (normal form + POST)

### 8.1 The form (`new_complaint.php`)

An ordinary HTML form with `method="post"` and `action="submit_complaint.php"`:

- a `<select>` of categories (read from the database);
- a title (`maxlength="100"`), a location (already filled in from the student's room) and the
  details (`<textarea>`);
- three **radio buttons** for priority inside a `<fieldset>` with a `<legend>`;
- a **hidden input** with the security token (the Lab 10 hidden-input idea).

### 8.2 Step 1: checks in the browser (`js/new_complaint.js`)

- On every `input` event, counters show "(85 characters left)".
- On `submit`, the script checks: category chosen, title not empty, location not empty, a
  priority chosen, details at least 10 characters. If something is wrong it calls
  `event.preventDefault()` and shows a specific message (Lab 7). If everything is fine, the form
  is sent normally.

The chosen radio button is read with the jQuery selector `$("input[name='priority']:checked").val()`.

### 8.3 Step 2: checks on the server (`submit_complaint.php`)

JavaScript checks can be skipped, so PHP checks everything again (Lab 10 §5):

1. the token is correct;
2. category, title, location and details are not empty;
3. the priority is exactly `Low`, `Medium` or `High`;
4. lengths are within the limits;
5. **no duplicate:** the student does not already have an *open* (not resolved) complaint with the
   same title and category. If they do, the message says which one ("#1").

If any check fails, `reject()` stores the message and the typed values in the session and
redirects back to the form, so nothing typed is lost.

### 8.4 Saving the complaint and its first history row together

```php
$pdo->beginTransaction();
// INSERT INTO complaints (...)            -> the complaint, status 'Submitted'
$complaintId = (int) $pdo->lastInsertId();  // the new complaint's number
// INSERT INTO complaint_updates (...)     -> history row: NULL -> 'Submitted'
$pdo->commit();
```

A **transaction** means "do all of these or none of them". If the second `INSERT` failed, a
complaint without a history row would be left behind; `rollBack()` in the `catch` block prevents
that. A wrong `category_id` is refused by the foreign key and also ends up in the `catch`.

### 8.5 Redirect after POST

On success, a flash message "Complaint #8 has been submitted." is stored and the browser is
redirected to `index.php`. Refreshing the page now reloads the list instead of sending the form
again (Lab 10, "Refreshing process.php after submission").

---

## 9. The staff update interface (`manage.php`)

`require_staff()` at the top means only staff can open it.

### 9.1 Filters

Same idea as `index.php`, with one more drop-down: **priority**. There is no `user_id`
condition, because staff see everyone's complaints, so the list starts from the condition `1 = 1`
(always true) and each chosen filter is added with `AND`. The query joins the `users` table to show
the student's name and admission number.

### 9.2 The order of the rows

```sql
ORDER BY CASE c.status WHEN 'Submitted' THEN 1 WHEN 'In Progress' THEN 2 ELSE 3 END,
         CASE c.priority WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 ELSE 3 END,
         c.created_at
```

`CASE` turns each status and priority into a number, so the work that most needs attention comes
first: new complaints before ones in progress, high priority before low, older before newer.
Resolved complaints are at the bottom.

### 9.3 The update column

`print_staff_actions()` in `functions.php` prints, for each complaint:

| Current status | What is shown |
|---|---|
| Submitted | Remark box (optional) + **Start work** button |
| In Progress | Remark box ("What was done? (required)") + **Mark resolved** button |
| Resolved | "Closed" (nothing more can be done) |

The button carries `data-complaint-id` and `data-to` (the next status). The same function is used
on `complaint.php`, so staff can also update a complaint from its details page.

---

## 10. Moving a complaint to the next status (fetch POST + JSON)

### 10.1 The whole journey in one picture

```
 BROWSER (update.js)                               SERVER (api/update_status.php)
 ───────────────────                               ──────────────────────────────
 1. Staff types a remark and clicks
    "Start work" on complaint #4
 2. Button and remark box disabled,
    message "Updating complaint #4..."
 3. fetch POST, body is JSON:
    {"complaintId":4,
     "fromStatus":"Submitted",
     "toStatus":"In Progress",
     "remark":"Sweeper assigned",
     "token":"9f3c..."}                ─────────►  4. Checks: POST? logged in? JSON? token?
                                                      staff? one step forward? remark rules?
                                                   5. UPDATE complaints SET status = 'In Progress'
                                                      WHERE complaint_id = 4
                                                        AND status = 'Submitted'
                                                   6. INSERT the history row
                                                   7. Reads the new state and the counts
                                     ◄─────────    8. Replies with JSON:
                                                      {"ok":true,
                                                       "message":"Complaint #4 moved to In Progress.",
                                                       "complaint":{"status":"In Progress",
                                                                    "nextStatus":"Resolved", ...},
                                                       "summary":{...}, "historyEntry":{...}}
 9. JavaScript updates the row: pill,
    button now says "Mark resolved",
    remark shown, summary counts changed,
    row briefly highlighted yellow
10. finally: button enabled again
```

### 10.2 The browser side: `js/common.js` → `postJson()`

```js
function postJson(url, payload) {
  payload.token = csrfToken;

  return fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload)
  }).then(readJsonReply);
}
```

This is the Lab 8 §5 POST pattern. `readJsonReply()` does the `response.json()` step and the
`response.ok` check from Lab 8 §4. One extra detail: our server **always** answers with JSON, even
for errors (for example `{"ok":false,"message":"Only hostel staff can ..."}`), so the reply is read
first and its `message` becomes the error text. That way the user sees *why* something failed, not
just "HTTP 403". The reply is also attached to the error as `error.data`.

### 10.3 The browser side: `js/update.js` → `moveComplaint()`

1. Reads the complaint number from the button, the current status from the row's `data-status`
   and the next status from the button's `data-to`.
2. If the next status is Resolved and the remark is empty, it shows a message and stops (no
   request is sent).
3. Disables the button and the remark box, so a double click cannot send two requests
   (Lab 8 §8.1).
4. Calls `postJson(...)`, then:
   - `.then`: `applyComplaintState()` updates the row, `updateSummary()` updates the tiles,
     `addHistoryEntry()` adds a line to the history (on the details page), and the success message
     is shown;
   - `.catch`: shows the error. If the server sent the complaint's real state (see §10.5), the row
     is corrected too;
   - `.finally`: enables the button again.

### 10.4 The server side: `api/update_status.php`

| Check | If it fails |
|---|---|
| Request is POST, user logged in, body is JSON, token correct (`read_api_request()`) | 405 / 401 / 400 / 403 |
| User is staff | 403 "Only hostel staff can change the status of a complaint." |
| `toStatus` is exactly the step after `fromStatus` (`next_status()`) | 400 "A complaint can only move from Submitted to In Progress to Resolved." |
| Remark at most 255 characters | 400 |
| Remark present when resolving | 400 "Describe the work done before marking the complaint resolved." |

So a complaint cannot skip a step (Submitted → Resolved), cannot go backwards, and a student
cannot change statuses even by sending a request by hand.

### 10.5 When two staff members click at the same time

Imagine the warden and the maintenance office both have the page open, and both click *Start work*
on complaint #4. The `UPDATE` contains `AND status = :fromStatus`:

```sql
UPDATE complaints SET status = 'In Progress', updated_at = NOW()
WHERE complaint_id = 4 AND status = 'Submitted'
```

The first request changes the row. When the second request runs, the status is no longer
`Submitted`, so **no row matches** and `rowCount()` is 0. The server then:

- cancels the transaction (`rollBack()`);
- answers **HTTP 409 Conflict**: "It was already moved to In Progress by someone else. The page
  now shows its current status." together with the complaint's real state.

The browser shows the message and corrects the row. No duplicate history row is written.

---

## 11. Asynchronous status refresh (fetch GET + JSON)

This is the feature the problem statement names directly: "asynchronous status refresh".

### 11.1 What the user sees

On `index.php`, `manage.php` and `complaint.php` there is a line:

```
Last checked at 07:15:07 PM. Checked again every 15 seconds.   [Refresh status now]
```

When a staff member changes a complaint, the student's page shows the new status **within 15
seconds**: the pill changes colour, the progress bar moves, the staff remark appears, the summary
numbers change, the card flashes yellow and a message says "Status changed: complaint #8 is now
In Progress." **The page is never reloaded.**

### 11.2 How it works (`js/refresh.js`)

```js
setInterval(refreshStatuses, refreshSeconds * 1000);   // every 15 seconds
refreshButton.addEventListener("click", refreshStatuses); // or on demand
```

`setInterval` is a built-in JavaScript function that calls a function again and again after a fixed
time. Each time, `refreshStatuses()`:

1. **Skips** if the previous request has not finished yet (`refreshPending`), so requests never
   pile up on a slow connection. The button is disabled while waiting.
2. Collects the numbers of all complaints on the page with jQuery `.each()`, e.g. `"3,5,9"`.
3. Sends `fetch("api/statuses.php?ids=3,5,9")`, a **GET** request, because it only reads
   information (Lab 10 §4.3).
4. For each complaint in the reply, compares the new status with the card's `data-status`. If it
   differs, it remembers the change and highlights the card. Then `applyComplaintState()` updates
   the card (also the time and remark, which can change too).
5. Updates the summary tiles.
6. Shows "Status changed: ..." if anything changed, and the time of the check.
7. `.catch`: if the network is down or the session has ended, shows
   "Could not refresh the status: ... Trying again in 15 seconds." in red. The page does not get
   stuck: the next check happens anyway, and when it succeeds the red message disappears.
8. `.finally`: allows the next check and enables the button.

### 11.3 The server side: `api/statuses.php`

1. Must be a GET request (405 otherwise) from a logged-in user (401 otherwise).
2. Turns `ids=3,5,9` into a list of whole numbers. Anything that is not a positive number is
   ignored; more than 200 ids is refused.
3. **Students only ever get their own complaints.** For a student, the query adds
   `AND c.user_id = :userId`. If a student asks for someone else's complaint number, it is simply
   not in the reply.
4. Replies with:

```json
{
  "ok": true,
  "complaints": [
    { "complaintId": 1, "status": "In Progress", "statusClass": "status-progress",
      "nextStatus": "Resolved", "updatedText": "27 Oct 2026, 07:10 PM",
      "lastRemark": "Electrician assigned. ..." }
  ],
  "summary": { "total": 3, "submitted": 1, "inProgress": 1, "resolved": 1 },
  "latestId": 3,
  "checkedAt": "07:11:03 PM"
}
```

**Building `IN (...)` safely.** The number of ids changes, so `get_complaint_states()` creates one
placeholder per id (`:id0, :id1, :id2`) and passes the values separately. The SQL still never
contains a value typed by the user.

### 11.4 "New complaints have been submitted"

When the page is built, the highest complaint number is stored in `data-latest-id` on a hidden
notice. Every refresh returns `latestId`. If it is bigger, a complaint was submitted after the page
was loaded, so the notice "New complaints have been submitted since this page was loaded. Reload
the list" appears. New rows are not added automatically because the list is sorted and filtered on
the server.

### 11.5 One function for every page: `applyComplaintState()`

The status refresh and the staff buttons both receive the same JSON shape for a complaint, so one
function in `common.js` updates a card (student page), a table row (staff page) or the details
panel:

- sets `data-status`;
- changes the pill's text and class (`status-submitted` / `status-progress` / `status-resolved`);
- recolours the three progress steps;
- updates "Last updated" and the latest remark (using `.text()`, so a remark can never inject
  HTML);
- on staff pages, `showStaffActions()` changes the button to *Mark resolved* or shows *Closed*.

---

## 12. The complaint details and history page (`complaint.php`)

- The complaint number comes from the URL: `complaint.php?id=5` (GET, converted with `(int)`).
- A student can open **only their own** complaints. For somebody else's complaint, the page
  says "Complaint not found", the same as for a number that does not exist, so it does not even
  reveal that the complaint exists.
- The history is a numbered list (`<ol>`) built from `complaint_updates` joined with `users`, so each
  step shows who made it and when:

```
1. Submitted                      by Rahul Sharma, 26 Oct 2026, 07:14 PM
2. Submitted → In Progress        by Maintenance Office, 27 Oct 2026, 07:14 PM
   "Electrician assigned. The fan capacitor will be replaced tomorrow morning."
3. In Progress → Resolved         by Maintenance Office, 28 Oct 2026, 07:15 PM
   "Capacitor replaced, fan working."
```

- Staff see the update controls here too. After an update, `addHistoryEntry()` adds the new line
  to the list using the `historyEntry` part of the JSON reply.
- The status refresh runs here too. If the status changes while a student is looking, the message
  suggests reloading to see the new history line.

---

## 13. Where jQuery is used

jQuery 3.7.1 is loaded from the CDN before our scripts, exactly as in Lab 8 §6.1.

| jQuery code | Where | What it does |
|---|---|---|
| `$("#csrf-token").val()` | `common.js` | Reads the hidden token |
| `$("#summary-total").text(...)` | `common.js` | Writes the new counts |
| `$(element).find(".updated-text").text(...)` | `common.js` | Updates part of a card |
| `.prop("hidden", false)` | `common.js` | Shows the staff remark |
| `.attr("data-to", ...)`, `.show()`, `.hide()` | `common.js` | Changes the staff button |
| `$(".tracked-complaint").each(...)` | `refresh.js` | Collects the complaint numbers |
| `$(newComplaintsNotice).show()` | `refresh.js` | Shows the "new complaints" notice |
| `$(".move-button").on("click", ...)` | `update.js` | Attaches the staff button handler |
| `$("<li>")`, `.append(...)` | `update.js` | Builds a new history line |
| `$("#filter-form select").on("change", ...)` | `filters.js` | Drop-downs submit the form |
| `$("input[name='priority']:checked").val()` | `new_complaint.js` | Reads the chosen radio button |
| `$("#login-id").val().trim()` | `login.js` | Reads the login field |

Plain JavaScript is used side by side (`getElementById`, `addEventListener`, `textContent`,
`classList`), because jQuery is just a JavaScript library (Lab 8 §6).

---

## 14. How the page is styled (`styles.css`)

- **One external stylesheet** for all pages (Lab 4). The look is a warm "ticket desk": cream
  background, a dark plum side menu, orange for actions, serif headings (Georgia, as in the Lab 4
  examples) and a sans-serif body font.
- **Side menu** (`header.php`): the menu is `position: fixed` on the left, 230px wide, and the page
  content (`.main-area`) has `margin-left: 230px` so it starts beside it. `footer.php` closes
  `.main-area`.
- **Layout with `inline-block` and the box model** (Lab 5): the summary strip is one white box split
  into four `inline-block` parts of 25% each, the filter fields sit side by side, the login card's
  two halves sit side by side, and `box-sizing: border-box` keeps widths predictable.
- **Status colours:** amber for Submitted, violet for In Progress, green for Resolved. They are used
  in the pills, the coloured strip at the top of each ticket and the dots in the summary strip. The
  strip colour comes from an **attribute selector**: `.complaint-card[data-status="Resolved"]`. When
  JavaScript changes `data-status`, the colour changes automatically.
- **Step tracker:** an `<ol>` whose three `<li>` are `inline-block`, each a third of the width. A CSS
  **counter** numbers them; `::before` draws the numbered circle and `::after` draws the line to the
  previous step. `.step-done` turns the circle green with a tick, and `.step-current` gives it a
  coloured ring.
- **History timeline:** a list with a line on its left border; `::before` puts an orange dot on each
  entry.
- **Staff table:** dark header row and alternate rows shaded with `tr:nth-child(even)`.
- **Highlight:** `.just-changed` gives a yellow background; `transition: background-color 0.6s`
  makes it fade in and out smoothly. JavaScript removes the class after 3 seconds with `setTimeout`.
- **Phones:** a `@media (max-width: 800px)` rule turns the side menu into a bar at the top, puts the
  summary two per row, makes filter fields full width and stacks the login card. The staff table
  scrolls sideways inside its own box (`overflow-x: auto`), so the page itself never scrolls sideways.

---

## 15. Security: how the project protects itself

| Danger | Protection | Where |
|---|---|---|
| SQL injection | Prepared statements with placeholders for every user value | All PHP that queries |
| HTML/script injection (XSS) | `htmlspecialchars()` on every value printed by PHP; `.text()` / `textContent` in JavaScript | All pages, `common.js`, `update.js` |
| Stolen passwords from the database | `password_hash()` / `password_verify()` | `setup.sql`, `process_login.php` |
| Session fixation | `session_regenerate_id(true)` after login | `process_login.php` |
| Another website sending requests for you (CSRF) | Random token per session, sent in a hidden input or the JSON body, checked with `hash_equals()` | `auth.php`, forms, `common.js` |
| Students changing statuses | `is_staff()` check in the API, not just a hidden button | `api/update_status.php` |
| Students reading other students' complaints | `user_id` condition in every student query; "not found" for others | `index.php`, `complaint.php`, `api/statuses.php` |
| Skipping or reversing steps | `next_status()` check; `CHECK` constraint on the column | `api/update_status.php`, `setup.sql` |
| Two staff members overwriting each other | `WHERE status = :fromStatus` + HTTP 409 | `api/update_status.php` |
| Half-saved data | Transactions with `rollBack()` | `submit_complaint.php`, `api/update_status.php` |
| Bypassing the JavaScript checks | Every check repeated in PHP | `submit_complaint.php`, `api/*.php` |

---

## 16. Complete journey of one complaint

1. Rahul (`24JE9101`) logs in. `process_login.php` checks his password and stores him in the session.
2. He opens **New Complaint**. The location already says "Amber Hostel, Room A-112".
3. He chooses Electrical, types "Tube light flickering", picks High and writes the details.
4. `new_complaint.js` checks the form; it is fine, so the browser POSTs it to `submit_complaint.php`.
5. PHP checks everything again, saves the complaint (#8, status Submitted) and its first history
   row in one transaction, and redirects to `index.php` with "Complaint #8 has been submitted."
6. His card shows **Submitted**, with the first step of the progress bar blue.
7. The maintenance office has `manage.php` open. Within 15 seconds `refresh.js` notices
   `latestId` went up and shows "New complaints have been submitted ...". They reload: #8 is at the
   top (Submitted, High).
8. They type "Electrician will check it at 4 PM." and click **Start work**. `update.js` sends
   fetch POST JSON to `api/update_status.php`.
9. The server checks the token, the role and the step, updates the row
   `WHERE status = 'Submitted'`, writes the history row, and replies with JSON.
10. The staff row turns blue and its button changes to **Mark resolved**, with no reload.
11. On Rahul's page, the next 15-second check (fetch GET `api/statuses.php?ids=8,2,1,3`) returns
    "In Progress". His card flashes yellow, turns blue, the progress bar moves to step two, the
    remark appears, and the message says "Status changed: complaint #8 is now In Progress."
12. Later, staff write "Starter replaced, light works now." and click **Mark resolved**.
13. Rahul's page refreshes again: the card turns green, all three steps are green, and the
    *Resolved* count goes up. In **View history** he sees all three steps, with who did what and when.

---

## 17. Likely viva questions and answers

**Q: Which tech stack did you use, and why nothing else?**
HTML, CSS, JavaScript, jQuery, PHP and MariaDB on Apache (XAMPP). The problem statement allows only
this stack.

**Q: What does "asynchronous status refresh" mean in your project?**
Every 15 seconds, JavaScript sends a fetch GET request to `api/statuses.php` with the numbers of the
complaints on the page. The server replies with their current status as JSON, and JavaScript updates
only the parts that changed. The page is never reloaded and the user can keep using it during the
request.

**Q: Why GET for the refresh but POST for changing a status?**
The refresh only reads information, so GET is right (Lab 10 §4.3). Changing a status changes data,
so it uses POST, with the token in the JSON body.

**Q: Why are there two `then` steps in a fetch?**
The first runs when the HTTP response arrives and starts reading the body with `response.json()`.
The second runs when the body has been converted into a JavaScript object.

**Q: Why check `response.ok`?**
fetch only fails on network problems; a 403 or 409 still counts as a "successful" fetch. We check
`response.ok` (and our own `ok` field) and throw an Error so that `catch` shows the message.

**Q: How does PHP read the JSON body? Why not `$_POST`?**
`$_POST` only understands normal form data. For JSON we read the raw body with
`file_get_contents("php://input")` and convert it with `json_decode(..., true)`.

**Q: What stops a complaint from going straight from Submitted to Resolved?**
`next_status()` on the server: `toStatus` must be exactly the next status after `fromStatus`.
The button only ever offers the next step, but the server checks anyway, because a request can be
sent without our page.

**Q: What happens if two staff members update the same complaint at the same moment?**
The `UPDATE` has `AND status = :fromStatus`. Only the first matches; the second changes no rows, so
the server answers 409 with the current status and the second person's page corrects itself.

**Q: Why store the history in a separate table?**
`complaints` stores only the current state. To show who changed what and when, every step needs its
own row. That is `complaint_updates`, one row per step, linked by a foreign key.

**Q: Why use a transaction?**
Saving a complaint means two `INSERT`s (the complaint and its first history row). A transaction makes
sure both are saved or neither is. Changing a status is also an `UPDATE` plus an `INSERT`.

**Q: Can a student see other students' complaints?**
No. Every student query contains `user_id = :userId` (the ID comes from the session, not from the
browser). `complaint.php` answers "not found" and `api/statuses.php` leaves them out.

**Q: How does the "latest staff remark" work?**
A subquery takes the newest non-empty `remark` from `complaint_updates` for that complaint
(`ORDER BY update_id DESC LIMIT 1`).

**Q: What is `setInterval`, and why don't requests pile up?**
A built-in JavaScript function that runs a function repeatedly. `refreshPending` is set while a
request is running, and a new check is skipped until `finally` clears it.

**Q: Why GET for the filters?**
Filtering only views data; the values appear in the URL, so a filtered list can be
bookmarked or shared (Lab 10 §3).

**Q: What does `htmlspecialchars` protect against, and what do prepared statements protect against?**
`htmlspecialchars` stops text from being treated as HTML when it is printed (XSS). Prepared
statements stop text from changing an SQL statement (SQL injection). Both are needed (Extra Lab §6.2).

**Q: Why is the password not stored directly?**
`password_hash()` stores a one-way scrambled version. Even someone who reads the database cannot
see the passwords; `password_verify()` checks a typed password against it.

**Q: What is the token in every request?**
A random value created at login and stored in the session. Forms send it in a hidden input; fetch
sends it in the JSON body. A different website cannot know it, so it cannot make changes for you.

**Q: What happens if the network goes down while the page is open?**
The fetch fails, `catch` shows "Could not refresh the status ... Trying again in 15 seconds." in red,
and the next check runs anyway. When the network is back, the message disappears.

---

## 18. Glossary

| Word | Meaning |
|---|---|
| **Asynchronous** | The result arrives later; the page keeps working while waiting (Lab 8 §1.1) |
| **fetch** | JavaScript function that sends an HTTP request without reloading the page |
| **Promise** | An object for a result that will arrive later; `.then`, `.catch`, `.finally` react to it |
| **JSON** | Text format for data, e.g. `{"status":"Resolved"}` |
| **API endpoint** | A PHP file that returns JSON instead of a web page (the `api/` folder) |
| **setInterval** | Runs a function again and again with a fixed gap (15 seconds here) |
| **GET / POST** | GET asks for information (values in the URL); POST sends changes (values in the body) |
| **Session** | Server-side storage that remembers the logged-in user between requests |
| **Flash message** | A message stored in the session and shown once on the next page |
| **PDO** | PHP's way of talking to databases |
| **Prepared statement** | SQL with placeholders; the values are sent separately and cannot change the SQL |
| **Primary key** | Column that uniquely identifies each row |
| **Foreign key** | Column that must match a primary key in another table |
| **CHECK constraint** | A rule the database enforces, e.g. status must be one of three values |
| **JOIN** | Combines rows of two tables that belong together |
| **Subquery** | A small `SELECT` inside another query |
| **Transaction** | A group of SQL statements that are saved all together or not at all |
| **HTTP 409 Conflict** | "Your request conflicts with the current state", used when someone else changed the complaint first |
| **CSRF token** | Secret per-session value that proves a request came from our own page |
| **XSS** | Injecting HTML or script into a page; prevented with `htmlspecialchars` and `.text()` |
