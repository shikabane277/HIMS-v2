# HIMS User Guide
## Hospital Information Management System — Performance & Development Module

Welcome to the **HIMS Performance & Development Module**! This guide describes what the system does today and how to use each screen. No technical knowledge required.

> **This guide describes only what is actually in the system.** If a capability is not described here, it is not available — please do not assume a feature exists because a similar system has it.

---

## Table of Contents

1. [Getting Started](#1-getting-started)
2. [Your Dashboard](#2-your-dashboard)
3. [My Development](#3-my-development)
4. [Performance Management](#4-performance-management)
5. [Competency Management](#5-competency-management)
6. [AI-Assisted Gap Analysis](#6-ai-assisted-gap-analysis)
7. [Learning Management](#7-learning-management)
8. [Learning Oversight Tabs](#8-learning-oversight-tabs)
9. [Training Management](#9-training-management)
10. [Succession Planning](#10-succession-planning)
11. [Social Recognition](#11-social-recognition)
12. [AI Assistant](#12-ai-assistant)
13. [Administration](#13-administration)
14. [Account & Security](#14-account--security)
15. [Frequently Asked Questions](#15-frequently-asked-questions)

---

## 1. Getting Started

### Logging In

1. Open your web browser and go to your hospital's HIMS website.
2. Enter your **email address** and **password**.
3. Click **Log In**.

If you forgot your password, click **"Forgot your password?"** on the login page. You will receive an email with a link to reset it.

There is no self-registration — accounts are created for you by an Administrator.

### Navigation

Once logged in, you will see:

- **Sidebar (left side)** — The main menu. Click any item to navigate to that module. Your name, role, and the Logout button sit at the bottom.
- **Top bar** — Notification, Help/FAQ, Search, and AI Assistant buttons. Search opens a system-wide result panel rather than a separate page.
- **Main content area** — Displays the page you selected.
- **AI Assistant** — Opens from the 🤖 button in the top bar as a panel down the right-hand side. See [§12](#12-ai-assistant).

> **About the bell:** the red number is your unread count. The panel keeps a short recent history, including notifications you have already read. Unread rows use a blue background and dot; clicking one marks only that item read and opens its related HIMS page. **Mark all as read** removes the unread emphasis and count but does not delete the recent history.
>
> Alerts include recognition activity, course completion, CPD verification, professional credentials that are expiring or have expired, competency reassessments that have fallen due, and renewal cycles where required hours are at risk. Scheduled compliance alerts are normally raised by the daily scan; recognition, completion, and verification alerts are raised by the related workflow.
>
> **Credential, reassessment, and renewal-shortfall alerts are not private to you.** Each one goes to you, to your supervisor, and to the head of your department, because a lapsed licence affects whether you can be rostered. CPD verification notices go to you alone.
>
> **If you have no HIMS login,** these alerts reach you by email at the address on your employee record instead. Your supervisor and department head are told either way. **Email is switched on per deployment and is off by default**, so on a hospital that has not enabled it the alerts appear in the bell and nowhere else — which is why an employee with no login may need telling in person.

### Global Search

Click the **magnifying-glass** button in the top bar, or press **Ctrl+K** (Command+K on macOS). Enter at least two characters. Results are grouped by module and can be opened with the mouse or with the Up/Down arrow keys and Enter.

Search covers HIMS pages and records, including employees, reviews and goals, competencies and credentials, learning and training records, recognition, succession, administration, and your own saved AI conversations. Selecting a result opens the real module page and, where supported, focuses the matching row, post, goal, or conversation.

Search never grants additional access. Staff receive only their own employee and development records plus generally available modules. Supervisors are limited to themselves, direct reports, and the succession records relevant to those direct reports. Private recognition is returned only to its sender, recipient, or HR/Admin moderators. Confidential succession ratings are searchable only by HR/Admin, user accounts are Admin-only, and AI conversations are visible only to their owner.

### Reading a Table

Most pages in HIMS end in a table. Every one of them reads the same way: **a column heading sits directly over
the column it names**, so you can run your eye straight down from the heading to the values under it. The one
page that centres anything is **My Development**, where the narrow number columns are centred together with
their headings so they still line up: Required, Current and Gap on the competency table, Progress and CPD on
courses, CPD on training sessions, and Hours on the CPD log.

### Adding Something New

Throughout HIMS, creating a record opens a **panel over the page you are already on** rather than taking you to
a separate form. New review cycle, new competency assessment, add credential, new course, new pathway — all of
them work this way, and all of them behave the same:

- **Close without saving** with **Cancel**, the **×**, the Escape key, or a click anywhere outside the panel.
- **If something is wrong** with what you entered, the panel comes back open with your answers still in it and
  the problem marked, so nothing is retyped.
- **Where you have to choose several things at once** — the competencies a course covers, the roles a pathway
  is for — they are tick boxes. Tick as many as apply; there is nothing to hold down.

Because the list is right behind the panel, what you just added appears as soon as you save it.

### Your Role

What you can reach depends on your assigned role:

| Role | What You Can Do |
|---|---|
| **Admin** | Everything — all modules, plus user accounts. Reviews are the one exception: like everyone else, an Admin may only review their own direct reports |
| **HR Manager** | All modules except user accounts. Reviews follow the same reporting-line rule as everyone else |
| **Supervisor** | Create and score reviews for their own direct reports, create competency assessments and credentials, run gap analysis, schedule training sessions, view redacted succession records for direct-report candidates, manage those candidates' milestones, and read their team's compliance figures |
| **Staff** | Dashboard, My Development, performance and competency pages, reading and responding to their own reviews, learning, CPD logging, their own renewal cycles, training registration and feedback, and recognition |

> **Note:** Menu items you do not have access to are hidden from your sidebar.
>
> **What you see within a module varies.** In **Employees**, **Gap Analysis**, **My Development**, Learning's oversight/employee-record lists, the core supervisor dashboard figures, and **Succession**, supervisor access follows the reporting line. Recognition has its own public/private audience rule. The Learning catalogue, Learning's Sessions and Venues tabs, credential alerts on the supervisor dashboard, and upcoming-session lists have their own wider scopes, so do not assume one module's list rule applies everywhere.
>
> **Performance is stricter than all of them.** A review is visible to the person it is about, the person who wrote it, and that person's supervisor — and to nobody else, whatever their role. See [§4](#4-performance-management).
>
> **"Reports to" is what matters, not "same department."** Being in somebody's department gives you no access to them; being their supervisor does. **People Manager controls who may appear in Reports To. Account role controls what they may do inside HIMS.** A new manager must be active, marked as a People Manager, and linked to a Supervisor, HR Manager, or Admin account. Marking People Manager never grants access. If an employee's **Reports To** field is blank, no supervisor can see or review them at all — only Admins and HR Managers can, and any review they write is recorded as an exception.

---

## 2. Your Dashboard

The **Dashboard** is the first page you see after logging in, and it changes depending on your role.

**Admins and HR Managers** see a hospital-wide view: active headcount, pending reviews, expiring and expired credentials, active enrolments, overdue required training, recognitions this month, critical competency gaps, upcoming training sessions, headcount by department, competency hotspots, recent reviews, at-risk critical positions, credential alerts, and recent recognitions. Admins additionally see system account totals.

> **"Overdue Required Training" counts people, not courses.** It is every person whose assignment's *required by* date has passed and who has not finished — an unfinished course enrolment, or a session they were not marked as having attended. Required training with no date set never appears here, however long ago it was assigned, because nobody was asked for it by a particular day. The figure is the same one the Required Training tab shows per assignment, added up, so the two can never disagree; click through to **Learning → Required Training** to see who.

**Supervisors** see team size, pending reviews, expiring-credential counts, critical gaps, average score, team members, competency hotspots, and recent reviews for their **direct reports**. Two supporting lists are intentionally wider: the credential-alert list is department-scoped, and upcoming training sessions are hospital-wide. If the account has no linked department, the page shows an empty supervisor state.

> **The core dashboard and review pages both follow the reporting line.** A supervisor's credential-alert panel can still include another employee from the same department, because that particular alert list is department-scoped. Upcoming sessions are not employee records and are shown hospital-wide.
>
> **"Pending" means the cycle is still running.** A review counts as pending until its cycle's end date passes, at which point it freezes and drops off the tile — being marked *Finished* does not remove it, and neither does leaving it in *Draft*. So the count answers "how much review work can still be done", not "how much is unwritten".

**Staff** see a personal view of their own records.

Every figure on the dashboard is calculated live from the database.

---

## 3. My Development

**What it does:** Gathers everything HIMS holds about one person's development onto a single page — competency gaps, credentials, reassessments falling due, courses, training, and CPD hours.

### Sidebar: 📈 My Development

Click **My Development** and the page opens on your own record. You do not need to know your employee number; the link works it out from your account.

> **If your account is not linked to an employee profile,** the page returns you to the dashboard with a message saying so. Ask HR or an Administrator to link it.

#### Whose Record You Can Open

| Your role | Whose progression you can view |
|---|---|
| **Admin / HR Manager** | Anyone |
| **Supervisor** | Yourself and the people who report to you |
| **Staff** | Yourself only |

Admins, HR Managers, and Supervisors also reach the same page from the **Employees** directory, so a supervisor can review a team member's development before a one-to-one. Trying to open someone outside your reach gives a "not authorised" page rather than an empty one.

> Being in the same department as somebody does **not** let you open their record — they have to report to you. If a colleague you expect to see is missing, check that their **Reports To** field names you.

#### What Is on the Page

Your name, position, and department sit at the top, followed by four figures:

| Figure | What it counts |
|---|---|
| **Open Gaps** | Competencies where your assessed level is below the level the competency requires |
| **Credentials** | Professional credentials on record for you, whatever their state |
| **CPD Hours (12mo)** | Verified CPD hours earned in the last twelve months |
| **Due Soon** | Competency reassessments falling due within the next 90 days |

Below the figures are six panels:

- **Open Competency Gaps** — competency, category, the required and your current proficiency, the gap between them, and when you were last assessed. Mandatory competencies are flagged. Only your most recent assessment of each competency is shown, so an old score does not linger next to a newer one.
- **Credentials** — each credential by type (PRC licence, BLS certificate, and so on) with the body that issued it, its expiry date, and its status: *Active*, *Expiring soon*, *Expired*, or *No expiry*. This is the same calculation the daily check and the dashboard use, so what you see here cannot disagree with an alert you were sent.
- **Reassessments Due** — competencies falling due in the next 90 days, with the date you were last assessed and the due date. Overdue dates are shown in red. Underneath each date the page says where it came from: *set by assessor* when your assessor picked the date for that particular assessment, or *every N mo* when it follows the standard interval for that competency.
- **Course Activity** — the courses you are enrolled in, their status, progress, and the CPD hours attached to each.
- **Training Sessions** — sessions you registered for, the date, whether you are *registered* or *attended*, and the CPD hours.
- **Verified CPD Hours (Last 12 Months)** — each verified activity with its date, its source, the hours, and a total at the bottom.

> **Unverified CPD does not appear here.** The panel and the twelve-month figure both count verified hours only. An external activity still waiting on HR approval is on your CPD list under **Learning**, but not on this page — see [§7](#7-learning-management).

> **Nothing on this page can be edited.** It reads records created elsewhere: assessments and credentials are entered by an assessor, course enrolments by whoever required the training of you, session registrations by you, CPD by you or the system.

---

## 4. Performance Management

**What it does:** Records employee performance through review cycles and structured KPI scoring.

### Sidebar: 📋 Performance

#### Review Cycles

A **review cycle** is an evaluation period (probationary, quarterly, semi-annual, or annual) with a status of *planned*, *active*, *closed*, or *archived*. The status is mostly a label: **a cycle closes itself when its end date passes** — the list shows it as *Closed* whether or not anyone edited the status, and *Archived* is the only status that means something the date does not (an archived cycle is one you do not want the ordinary rules applied to). A cycle whose end date is today is still open for the whole day; it closes the day after.

**For Admins/HR Managers:**
1. Click **Performance** in the sidebar.
2. Click **New Cycle**. A panel opens over the page — you stay on the Performance list the whole time.
3. Fill in the cycle name, type, start date, and end date. Picking a type fills the dates in for you — annual gives you the calendar year, quarterly the current quarter, probationary the next six months. Adjust them if you need to.
4. Click **Create Cycle**. The new cycle appears in the list behind the panel.

The Admin or HR account creating a cycle must be linked to an employee profile because `created_by` records an accountable author. An unlinked account is refused rather than borrowing another employee's identity.

**Editing a cycle:** click **Edit** next to it, either on the Performance page or on the cycle's own page. The
same kind of panel opens with the cycle's current details already filled in. Changing the **type** here refills
the dates the same way it does when creating; leave the type alone and the dates stay as they were. **Save
Changes** returns you to the Performance page.

> **Closing a panel without saving:** **Cancel**, the Escape key, the **×**, or a click anywhere outside the
> panel. Nothing you typed is kept. This works the same way for every panel in HIMS — new cycle, new
> assessment, add credential, new course, new pathway.

#### Performance Reviews

A **performance review** evaluates one employee within one review cycle. **One reviewer writes one review of one employee per cycle** — if you start a second one for the same employee in the same cycle, the system takes you to the review you already have rather than making a duplicate. Changing the review type does not get you a second one; the type is a label on the review, not a separate review. Two different reviewers can each hold their own review of the same person, which is what makes an exception review possible without disturbing the supervisor's.

**Your account has to be linked to an employee profile before you can write a review at all.** A review records who wrote it, and an account with nobody behind it cannot answer that. If yours is not linked, the review pages will tell you so — ask HR or an Administrator to link it.

**Who may review whom.** Reviews follow the **reporting line**, not your job title. You can review the people whose **Reports To** field names you, and nobody else. This applies to Admins and HR Managers too: holding the top role in HIMS does not let you write a review of someone who does not report to you. Sharing a department is not enough either — a ward supervisor cannot review a colleague on the same ward who reports to somebody else.

> **If nobody reports to you, the employee list on the review page will be empty.** That is the rule working, not a fault. Check the **Reports To** field on the people you expect to see.
>
> **If there are no open review cycles, the page says so instead.** A review lives inside a cycle, so one has to exist and still be running before anybody can be reviewed. Admins and HR Managers get a **Create a Cycle** button on that message; everyone else should ask them.

**You can never review yourself.** No role is exempt, including Admin.

**When HR or an Admin may step outside the line.** Somebody has to be able to act when the normal reviewer cannot, so Admins and HR Managers may open a review outside the reporting line in exactly four situations:

- the employee has **no supervisor recorded**;
- the assigned supervisor is **on leave, suspended, or has resigned**;
- the assigned supervisor is **being reviewed in the same cycle** themselves;
- the assigned supervisor has **no usable HIMS account** or only Staff access.

The employee dropdown tells you which of these applies before you choose ("reports to nobody", "reports to Maria Santos"). Picking one of them adds a **reason** box that you must fill in. The resulting review is badged **Exception** wherever it appears, carries your reason, and is written to the system's audit log with your name, the time, and the basis you used. If none of the four applies, the review is simply refused — there is no override.

**Starting a Review (the employee's supervisor, or HR/Admin on one of the bases above):**
1. Click **Start a Review** on your dashboard, or open a cycle from the **Performance** page and click **Add Review** there. Starting from the cycle page picks that cycle for you.
2. Select the **employee** — the list shows only people you may legitimately review — then the **review cycle**, the review type, and the **KPIs** to score.
3. Fill in the **reason** box if the employee you picked is an exception.
4. Click **Create & Score** — you are taken straight to the scoring form with the chosen KPIs already listed.
5. Enter a score for each KPI and save.

> **A closed cycle takes no new reviews.** Once a cycle's end date has passed, **Add Review** disappears from its page and the cycle drops out of the dropdown on the review form. If you need a review in a period that has ended, extend the cycle's end date first, or use a cycle that is still running.

**One review, one voice.** The reviewer writes the scores. There is no self-assessment and no peer rating in HIMS — the employee reads their review but does not score it, and there is no way to invite anybody else onto it. The overall score is the weighted average of the reviewer's KPI scores, using each KPI's own weight.

**Two scores, and they are not the same number.** A finished review shows both, because they answer different questions:

| On the review | What it is |
|---|---|
| **Average of KPI ratings** | The plain average — every KPI counted alike |
| **Final Score** *(weighted by KPI importance)* | The same ratings, with the more important KPIs counting for more |

The Final Score is the figure the rest of HIMS quotes. They differ whenever your higher and lower ratings do not fall evenly across the important and less important KPIs.

**Each KPI tells you how much it counts.** Under every KPI on the scoring form you will see something like *"counts 9% of the final score"*. That is the share that KPI decides — the KPIs on a review are not equally important, and this is the plain-language version of that. The percentages down the column always add up to 100.

> **A blank score is not a zero — it is left out entirely.** If you leave a KPI's box empty, that KPI does not count against the employee; it drops out of the score, and the KPIs you *have* rated share out its influence between them. The form tells you how many are still unrated and warns you while any are. So an employee scored on two of eight KPIs gets a Final Score built from those two alone. Rate every KPI you intend to count.

**Draft, Finished, Completed.** You set the first two; the calendar sets the third.

| Status | What it means | Who sets it |
|---|---|---|
| **Draft** | You are still writing it | You, on the scoring form |
| **Finished** | You consider it done — and it is signed | You, on the scoring form |
| **Completed** | The review cycle has ended; the review is frozen | Nobody — the cycle's end date |

Saving as **Finished** stamps the review with a signature and the time, recording that you attested to it. It stays editable while the cycle is still running — come back, change a score, save again, and it is re-signed so the signature always covers what the review actually says today. Moving it back to **Draft** removes the signature, because a review you have reopened is not one you are still standing behind.

**Completed is not on the form.** You cannot choose it and you cannot undo it. When the cycle's end date passes, every review in it becomes Completed the moment the date rolls over and stops accepting changes — no button to press, nothing to remember, and no overnight job that has to run first: the status is worked out from the cycle's dates each time the review is opened, so it is right whether or not anyone is logged in.

> **What a freeze looks like if you are mid-edit.** Nothing is lost that you had already saved, but the next save is refused: the **Score** button is gone from the review, the page carries a banner explaining that the cycle has ended, and going to the scoring address directly returns you to the read-only review. Anything you had typed but not saved when the date rolled over is not kept. If you know a cycle is about to end and the review is not finished, save it before the day is out.

> **Dates are Philippine time.** "The end date has passed" is judged by the hospital's clock (Asia/Manila), not the server's or your computer's. A cycle ending on the 10th is editable all day on the 10th and frozen from the first minute of the 11th, Manila time.

**Viewing a Review:**
Open **Performance** and click any review in the list. You see a review because you are **connected to it** — it is about you, you wrote it, or it is about one of your direct reports. This is true for every role: an Admin who is not involved in a review gets a "not authorised" page, the same as anyone else. There is no view that lists every review in the hospital.

**Employee response, appeal, and acknowledgement:** once the reviewer marks the review **Finished**, the employee named on it can add or revise a response or appeal. This field is separate from the KPI scores: submitting it cannot change ratings, reviewer comments, status, or the reviewer signature. After the cycle closes and the review becomes **Completed**, the employee can save a final response and acknowledge that they have reviewed the scores and comments. Acknowledgement is recorded once, with a timestamp, and locks the response against further changes. Response and acknowledgement actions are audited.

#### Goals

Goals attached to a review are shown on the review page with the goal, its target, what was achieved, and a status.

> Goals are **display only** in this version — there is no screen for adding a goal or updating its progress. Goal records must be loaded into the database directly.

#### Performance Improvement Plans (PIPs)

If a review has a linked improvement plan, it is shown on that review's page, and the count of active PIPs appears on the Performance page.

> PIPs are **display only** in this version — the system does not create a PIP automatically from a low score, and there is no screen for creating or editing one.

---

## 5. Competency Management

**What it does:** Tracks clinical and non-clinical skills and credentials, and highlights skill gaps across departments.

### Sidebar: 🎯 Competency

#### Competency Domains & Categories

Competencies are organised into:
- **Domains** — broad areas (e.g., "Clinical", "Administrative", "Technical")
- **Categories** — specific groups within a domain (e.g., "Emergency Response", "Infection Control")
- **Individual Competencies** — specific skills (e.g., "Ventilator Operation", "IV Medication Administration")

**For Admins/HR:** You can create domains from the Competency page and open any domain to see what it contains.

#### Competency Assessments

Assessments record an employee's proficiency level (1–5) for a competency. The system compares that to the level required for their role and calculates the **gap** automatically.

**Creating an Assessment (Admins/HR/Supervisors):**
1. Go to **Competency** and click **New Assessment**. A modal headed *New Competency Assessment* opens over the page.
2. Select the employee and the competency, and rate their current proficiency level.
3. Choose the assessment method (observation, self assessment, supervisor rating, practical test, or written exam).
4. Add assessor notes if needed.
5. Click **Save Assessment**.

You do not enter the gap — the system works it out from the proficiency you recorded and the level the role
requires.

#### Credentials (Licenses & Certifications)

The system records clinical licences and certifications (e.g., PRC License, Board Certifications, BLS, ACLS). Each credential's status is worked out automatically from its expiry date:

| Colour | Status | Meaning |
|---|---|---|
| 🟢 Green | **Active** | Valid and current |
| 🟡 Yellow | **Expiring Soon** | Expires within 30 days |
| 🔴 Red | **Expired** | Past the expiry date |
| ⚪ Grey | **No expiry** | No expiry date recorded — nothing to fall due |

**Adding a Credential (Admins/HR/Supervisors):**
1. Click **Add Credential** — it is on the **Competency** page and on the credentials register itself, so you can add one without leaving the list you are reading.
2. Choose the **employee**, then the **credential type** from the fixed list — PRC License, BLS Certification, ACLS Certification, IV Therapy, Board Certificate, JCI Training, or Other. There is no free-text credential name: the type *is* the name you will see on every screen afterwards.
3. Optionally record the licence/certificate number, the issuing body, the issue date and the expiry date. Leave the expiry blank for something that never falls due.
4. Click **Add Credential**.

> **These statuses raise alerts; they are not just colours on a screen.** A daily sweep at 06:30 hospital time
> notifies the employee, their supervisor, and their department head — where one is recorded; see [§13](#13-administration) — about every credential that has expired or
> has fallen inside the 30-day window, and logs that it did so. The Credential Alerts panel on the Competency
> page and on the Dashboard is the same calculation, so the screen and the alert cannot disagree.
>
> Two things your administrator controls: the sweep only runs if the server's scheduled task is set up (the
> notifications can also be raised by running the check by hand), and **email is off unless it has been switched
> on for your deployment** — until then the alerts are in-app only. See [§1](#1-getting-started) for who receives
> them.

#### Department Skills Gap Matrix

The Competency page shows a department-level matrix of where proficiency falls short of the required level, so managers can see at a glance if a ward is missing a critical skill.

**Narrowing it to one department:** use the dropdown in the card's header. It applies the moment you pick — there is no Filter button — and the page reloads showing only that department's numbers, with your choice still selected. The web address changes too, so you can bookmark a department's matrix or paste the link to a colleague. Pick **All Departments** to go back to the whole hospital.

> The matrix only lists competencies that somebody has actually been assessed on. A skill nobody has been assessed against has no average and no gap, so it is left out rather than shown as met. That is why a single department usually shows a shorter list than the hospital-wide view, and why a department with no assessments recorded yet shows an empty matrix.

---

## 6. AI-Assisted Gap Analysis

**What it does:** Combines competency assessments, performance results, and training records to show where skills fall short of what a role requires, with AI-written commentary.

### Sidebar: 🤖 AI Gap Analysis

> **Who can use this:** Admins, HR Managers, and Supervisors.

#### Using It

1. Click **AI Gap Analysis** in the sidebar.
2. Optionally pick a **department** from the dropdown and click **Filter**. Leaving it blank analyses the whole organisation.
3. The page lists the **weakest competencies** for that scope, with average proficiency and average gap.
4. Click **View Department Analysis** for the full departmental breakdown.
5. To analyse one person, find them in the **Analyse an Individual** list and click through to their report.

The analysis runs when you open the page — there is no separate "Run Analysis" button.

#### An individual's report also reads what their supervisor wrote

The scores tell you *that* something needs work. The sentences a supervisor typed into a review are usually the only place that says *why* — that the problem with Medication Administration is the new infusion pump rather than dosing knowledge, for instance. So an individual report reads the written half of the last three review cycles as well as the numbers:

*   the **Strengths** and **Areas to Improve** boxes from each review, and
*   **every** comment left on an individual KPI — including comments on KPIs the person scored *well* on, because praise is evidence too.

Two places on the page show this:

*   **Summary of Supervisor Feedback**, inside the AI Analysis card. The AI's plain-language summary of everything on record, with **recurring themes** tagged *Improving*, *Persistent*, *New* or *Resolved* so you can see whether a point raised last year was ever acted on, plus short lists of what the person is praised for and what concerns have been raised.
*   **Written Feedback on Record**, the card below the measured gaps. The comments themselves, word for word, grouped by review cycle. This is what the summary above was made from, so you can check it — and it is written by the system, not the AI, so it is on the page whether or not the AI is working.

A review cycle that carries no written comment is still listed, saying exactly that. A cycle where somebody was scored but nobody wrote anything down is worth knowing about.

> **Two things to be aware of.** These comments are sent to the AI provider to be summarised — the same external service the assistant uses — **unless your administrator has turned that off**. Two switches on the user-administration screen govern it: one stops written comments leaving the hospital at all (the AI is then told the text was withheld, so it cannot quietly guess), and one blanks the employee's own name and any patient identifier out of the text before it is sent. Name redaction is the safe default: if the setting cannot be read for any reason, HIMS redacts. Bear it in mind anyway when writing a review comment — avoid patient names and identifying details, as you should in any personnel record. And the summary is a summary; where a decision turns on exactly what was said, read the comment itself in **Written Feedback on Record** rather than the paraphrase above it.

> **If the AI service is unavailable**, the numbers and tables still work; only the AI-written commentary is replaced with a ⚠️ message. The **Written Feedback on Record** card is unaffected — the comments are read straight from the reviews.

---

## 7. Learning Management

**What it does:** One place for the course catalogue, required training, renewal standing, CPD, learning pathways, and accreditation reports. Course enrolment is assigned by somebody with authority; employees can still register themselves for training sessions.

### Sidebar: 📚 Learning

#### Course Catalog

Browse available courses organised by category:
- **Compliance** — mandatory training (Infection Control, Fire Safety, etc.)
- **Clinical** — clinical skills development
- **Soft Skills** — communication, leadership, teamwork

Each course record carries CPD hours, difficulty, duration, passing score, and retake limit.

#### Getting Assigned to a Course

There is no **Enroll** button in the Course Catalogue and no self-service course request or waiting list. A Supervisor, HR Manager, or Admin puts someone on a course from **Learning → Required Training → Assign Training**. For a single-employee target, a Supervisor can pick only themselves or a direct report. Department, role, and all-active targets expand to the full selected group, so use those broader choices carefully.

The catalogue is for browsing and administration. Click the linked course title for details. Admins and HR Managers see an **Edit** button that opens the course editor in a modal; the catalogue no longer has separate View or Enroll action buttons.

> **Course content is not delivered inside HIMS.** There is no lesson player and no quiz engine. HIMS records the requirement and completion evidence; the learning itself happens elsewhere. Completion is recorded by a person, not measured by the software.

#### Marking Enrolments Complete (Supervisors and above)

Open a course by clicking its title. The **Enrolled Employees** table gains a **Mark complete** button on every unfinished row for authorised users. Clicking it records the completion, credits the course's CPD hours to that person as verified, and notifies them.

Supervisors can do this for **the people who report to them**; HR Managers and Admins for anyone. **Staff do not see the column at all** — nobody certifies their own completion. Admins and HR Managers additionally see **Reopen** on completed rows, which undoes both the completion and the CPD credit after a confirmation prompt.

The same buttons appear in the assignment roster modal, which is the better place to work when you are chasing one requirement across a department rather than one course. See [§8](#8-learning-oversight-tabs).

#### My Renewal Cycles (all roles)

Click **My Renewal Cycles** at the top of the Learning page to see what you personally owe: each renewal requirement that applies to you, the hours required, the hours you have earned so far, what is left, and the date the cycle closes.

Each cycle is marked **On track**, **At risk**, or **Short**. *At risk* does not mean you have failed — it means that, at the rate you have been going, you would not reach the total before the deadline. It is a nudge with time still on the clock.

> **This page is open to everyone,** including Staff. The oversight pages elsewhere answer the hospital's questions; this one answers yours, and hiding it would mean the only people who could see a shortfall are the ones who cannot fix it.

> **Hours come from verified CPD only.** An external activity still waiting on HR approval does not count towards a cycle until it is verified.

#### Creating a Course (Admins/HR)

1. Go to **Learning** and click **New Course**. A panel opens over the catalogue.
2. Fill in the course title, description, category, CPD hours, and the other course settings.
3. **Tick the competencies the course remediates.** This is what makes the course show up as a recommendation on the gap analysis screens, so a course left untagged is still usable but will never be suggested to anyone. There is a filter box above the list if the list is long — typing in it only hides rows, so anything you have already ticked stays ticked.
4. Click **Create Course**.

You can edit the course and its tags directly from the catalogue's **Edit** modal. The linked course title also opens the detail page, where the **Competency Tagging** panel can change tags without changing other course fields. Ticking nothing clears every tag.

#### Learning Pathways

Pathways group several courses into an ordered curriculum (e.g., "Critical Care Nurse Pathway"). Admins and HR
Managers create them from **New Pathway**, which is on the Learning page and on the pathways list itself.

Give the pathway a name and description, optionally a total CPD target, and **tick the roles it is aimed at**.
Target roles are what let the pathway be recommended to the right people; leave them all unticked and the
pathway simply is not aimed at anybody in particular.

#### CPD (Continuing Professional Development) Records

The **CPD** page lists recorded CPD hours per employee, and your yearly total appears on the dashboard.

Hours reach that list two ways: the system credits them when someone marks one of your course enrolments complete, or you log them yourself.

##### Hours Credited When a Course Is Completed

When an Admin, HR Manager, or your Supervisor marks one of your enrolments complete, the course's CPD hours are added to your record automatically and **already verified** — no form to fill in, and they count toward your renewal cycle immediately. You get a notification telling you how many hours were credited.

You cannot mark your own enrolment complete; see [§8](#8-learning-oversight-tabs) for who can and how. If a completion is later reopened, those hours are removed again.

##### Logging CPD Hours (all roles)

For everything the system did not witness — a conference, a webinar, private study — log it yourself.

1. Go to **Learning** → **My CPD**.
2. Click **Record CPD**. A modal headed *Record CPD Hours* opens on the page.
3. Choose the **activity type**, then give the activity a title, the date you did it, and the number of hours.
4. Click **Save**.

> The **Log CPD** button on **My Cycles** does the same thing — it is a link back to My CPD with the same modal already open.

**Whether it counts straight away depends on where the activity came from.** An activity that HIMS can already see — an in-house course or a training session you attended here — is accepted as verified immediately. Anything external (a conference, a webinar, private study) is saved as **unverified** and waits for HR to approve it. Unverified hours are listed with your other records but are marked as pending.

> **Only HR Managers and Admins can verify.** If your external activity is still pending, the person to ask is HR. As ordinary staff you cannot approve your own entry — you do not have the permission at all. Note for HR Managers and Admins: the system does **not** stop you verifying your own CPD record, so treat that as a matter of policy rather than something HIMS enforces.

> **Do not log a course's hours yourself if someone has already marked you complete** — the system credited them at that moment, and logging them again would double-count.

#### Certificates

The Learning Overview carries a **Certificates Issued** stat card, which counts the rows in the `certificates` table.

> **The system does not issue certificates.** Nothing writes to that table — no code path anywhere in HIMS creates a certificate when you finish a course — so in practice the card reads **0** and stays there. There is no download and no QR verification. Read the card as "certificates on record", not as evidence that HIMS produced any.

---

## 8. Learning Oversight Tabs

**What it does:** The institutional tabs inside Learning: what is required, who is behind, and what evidence an accreditation survey needs.

### Sidebar: 📚 Learning

There is no separate Compliance sidebar item. Everyone sees **Learning**, which carries eight tabs. Five are open to every role — **Overview**, **My CPD**, **Pathways**, **Sessions** and **Venues** — and Supervisors, HR Managers and Admins additionally see **Required Training**, **Renewals** and **Reports**, the three covered in this section. Supervisors can assign training; their visible oversight rosters are limited to their reporting line, while department/role/all assignment targets themselves are broader. Renewal rules and Account Coverage remain HR/Admin only.

> **A visible tab is not a blanket permission.** Sessions and Venues are readable by everyone because staff register for sessions there, but the write controls inside them check the role separately — only Admins, HR Managers and Supervisors can schedule a session, and only Admins and HR Managers can add a venue.

> **Nothing on these pages needs the person to have a login.** Every list here is built from employee records, not user accounts. Someone who has never signed in to HIMS still appears in the compliance figures, still gets assigned mandatory training, and still has renewal cycles tracked. The one page that mentions accounts at all is **Account Coverage**, and there the account is the thing being reported on, not a requirement.

#### The Learning Overview

For roles with oversight access, the Learning Overview includes a **Hospital Requirements** strip showing outstanding requirements, overdue work, and renewal shortfalls. The detailed figures and lists live under Required Training, Renewals, and Reports.

#### Assigning Mandatory Training (Supervisors and above)

Course self-enrolment is not available. An **assignment** is how somebody gets onto a course and how a scheduled session is made mandatory.

1. Go to **Learning → Required Training** and click **Assign Training**. A modal opens on the same page.
2. Search the **Courses** and **Training Sessions** lists and tick any number of items. You can mix both types in one submission; each becomes its own tracked requirement.
3. Choose who it is for:
   - **One employee** — pick them from the list.
   - **A department** — everyone active in it.
   - **Everyone in a role** — every active employee holding that role, e.g. all Staff Nurses.
   - **All active employees** — the whole hospital. Anyone who has left is not included.
4. Optionally set a **Required By** date and a short **Reason** (e.g. "JCI IPSG.1 annual requirement").
5. Click **Assign**.

HIMS creates the enrolments/registrations for each selected requirement and tells you how many were created. Anyone already enrolled in a selected course is skipped rather than duplicated, and session capacity is respected.

> **Supervisor scope depends on the target.** A single employee must be the Supervisor or one of their direct reports. Department, role, and all-active targets expand to every active employee matching that target; the roster shown back to the Supervisor is still limited to people they may see.

#### Who Has Not Finished

Click the **outstanding/overdue badge** to open a modal already filtered to people who have not finished, or click **Roster** to see everyone. The modal shows each person's department, status, contact details, and available completion action without navigating away from Required Training.

The roster respects your role: a Supervisor sees only rows for people who report to them. A direct roster page still exists for saved links, but normal work stays in the modal.

#### Marking Somebody Complete

The completion rate only moves when someone records a completion, and how you do that depends on what was assigned.

- **A training session** — take the register. The last column on each row is an **Attendance** link, which is a shortcut to the session's own page rather than a sheet in the modal; from there you click **Mark Attendance** and save as described in [§9](#9-training-management). Marking someone **Attended** *is* the completion; there is nothing else to do here.
- **A course** — click **Mark complete** on that person's row. HIMS stamps today's date, moves them to Completed, and the rate at the top of the page goes up immediately.

Marking a course complete also **credits that course's CPD hours** to the person, already verified — you do not need to log them separately, and they count straight away toward that employee's renewal cycle. The person gets a notification saying so.

You can do the same thing from the course's own page under **Learning** → open a course → the **Enrolled Employees** table, which is more convenient when you are working through one course rather than one assignment.

> **You cannot mark yourself complete, and Staff never see the button.** Recording a completion is a statement *about* someone, so it is done by an Admin, an HR Manager, or a Supervisor — the same rule that already applies to checking someone in to a session. Supervisors can only do it for their own direct reports.

**Made a mistake?** Admins and HR Managers see a **Reopen** button on completed rows. It puts the person back to In Progress and **removes the CPD hours that were credited**, so their renewal standing goes back to what it was. You will be asked to confirm. Supervisors do not get this — undoing evidence is a narrower job than recording it. Re-completing afterwards credits the hours once, not twice.

#### Renewal Rules (Admins/HR)

A **renewal rule** says how many hours a particular credential or CPD requirement takes, and how often the clock resets. For example: *PRC Nursing Licence — 45 hours every 36 months.*

1. Go to **Learning → Renewals → Rules**.
2. Click **New Rule** and fill in the label, the subject it covers, the required hours, the cycle length in months, and any grace days.
3. Click **Save**.
4. Click **Open Cycles** to apply the rule to current employees.

> **Opening cycles is a deliberate second step.** Saving a rule does not immediately create cycles for hundreds of people — a typo in the hours field would be expensive to unpick. Check the rule reads correctly, then open the cycles.

> **Changing a rule later does not move the goalposts on anyone mid-cycle.** The required hours are frozen into each cycle at the moment it opens. Someone who is 30 hours into a 45-hour cycle still owes 45, even if you raise the rule to 60 tomorrow. The new figure applies to cycles opened after the change.

#### The At-Risk List

**Learning → Renewals** lists everyone who will not reach their required hours at the pace they have been going. Each row shows the person, department, requirement, progress, cycle end, standing, and escalation target.

Filter by department to get a single team's list. Supervisors see their own direct reports automatically, whichever department those people sit in.

> **"At risk" is a projection, not a verdict.** It compares how fast someone has been earning hours against how fast they would now need to go. **Short** is the harder state: the cycle has closed and the hours were not reached.

#### Accreditation Report

**Learning → Reports** opens the accreditation report: one row per active employee with competency, credential and required-training standing.

Filter by **department**, **role**, or **JCI standard**. The JCI filter narrows the competency figures to competencies tagged under that standard, which is what a surveyor asking "show me your evidence for IPSG" actually wants.

> **An employee is marked compliant when they have no expired credentials and no outstanding mandatory training.** Competency proficiency is reported but does not decide the marker — a gap is a development conversation, not a compliance breach.

#### Account Coverage

From **Learning → Reports**, HR Managers and Admins can open **Account Coverage** to see which employees have a HIMS login, which are tracked without one, and any login not linked to an employee record.

Being tracked without a login is a supported state, not a fault. What this page is for is making the split visible: when an alert goes unanswered, you can see whether the person actually had somewhere to receive it. The **unreachable** figure — no account *and* no email address on the employee record — is the one worth acting on, because those people can only be told in person.

#### How Alerts Reach People Without Accounts

The daily check that raises credential and reassessment alerts also covers renewal cycles running short. Each alert goes to the employee, their supervisor, and their department head where one is recorded — see [§13](#13-administration) for why a department created through HIMS has no head.

Where the employee has no HIMS login, the alert can be sent to the email address on their **employee record** instead of the notification bell — but **only if credential-alert email has been switched on for your deployment, which it is not by default.** Until it is, an employee with no login has nowhere to receive their own copy, and their supervisor and department head are the ones who have to act on it. The **unreachable** figure on Account Coverage is exactly this population, which is why it is the one worth working down.

---

## 9. Training Management

**What it does:** Schedules instructor-led training sessions, manages venues, and takes registrations.

### Learning → **Sessions** and **Venues**

> **There is no Training item in the sidebar.** Sessions and venues are two tabs inside **Learning**, and both are visible to every role. This section is kept separate only because the work — scheduling, registering, taking the register — is its own job; everything below starts from **Learning**.

#### Viewing Training Sessions

The Sessions tab lists **upcoming sessions** (workshops, seminars, drills) with their date, venue, instructor, and how many people have registered. It is a list, not an interactive calendar.

#### Registering for Training (all roles)

1. Go to **Learning** → **Sessions**.
2. Find an upcoming session and open it.
3. Click **Register Me**.

The system refuses the registration if the session is already at capacity, if you have already registered, or if your login is not linked to an employee profile.

> **You can only register *yourself* from this page.** A session can be made mandatory for somebody else through **Learning → Required Training** by a Supervisor, HR Manager, or Admin — see [§8](#8-learning-oversight-tabs).

> **Registrations cannot be cancelled from the system.** Ask an Administrator if you need one removed.

#### Creating a Training Session (Admins/HR/Supervisors)

1. Go to **Learning** → **Sessions** and click **New Session**. A modal headed *Schedule New Training Session* opens over the session list.
2. Fill in the session title, date, start and end time, venue, instructor, and maximum capacity.
3. Click **Schedule Session**.

The database refuses two sessions in the same venue on the same date with the **same start time**. Note that this is an exact match, not an overlap check — a 09:00–12:00 session and a 10:00–11:00 session in the same room on the same day are both accepted. The refusal also arrives as a database error page rather than a friendly message, so if saving a session fails unexpectedly, check whether another session already starts at that exact time in that venue.

#### Venues (Admins/HR)

Go to **Learning** → **Venues** and click **Add Venue** to record classrooms, simulator rooms, and other spaces with their capacity. A panel opens over the venue list.

#### Marking Attendance (Instructors, Admins/HR)

After a session runs, someone marks who actually turned up:

1. Open the session from the **Sessions** tab.
2. Click **Mark Attendance**, at the top of the **Registrations** panel. An attendance sheet opens inline above the roster.
3. For each registrant choose **Attended** or **No-show**.
4. Click **Save Attendance**.

**Who can do this:** the button appears for Admins, HR Managers and Supervisors on any *scheduled* session that has at least one registration — so a Supervisor does see it on somebody else's session. What differs is who their marks apply to. An Admin, an HR Manager, or **the instructor running the session** can mark anyone on the roster. A Supervisor who is not the instructor can only mark registrants **from their own department**; marks they submit for anyone else are discarded silently, with no error and no note on the confirmation, so check the roster afterwards rather than assuming everything you ticked was saved.

> **There is no QR code and no self-check-in.** Attendees cannot mark themselves present; it is done from the roster by the instructor or HR. Someone nobody marks stays listed as **Registered** — HIMS does not turn an unmarked registration into a no-show, so "no-show" on a roster always means a person actively recorded it.

#### Giving Feedback on a Session (all roles)

1. Open the session you attended.
2. Click **Give Feedback**, in the **Feedback Summary** panel. A panel opens over the session.
3. Rate the session and add a comment if you want to.
4. Click **Submit Feedback**.

> **The button only appears once you have been marked present.** If you attended but cannot see it, your check-in has not been recorded yet — ask the instructor or HR. You can only give feedback on your own attendance; there is no way to submit it on somebody else's behalf.

> There are no pre-tests or post-tests, and registrations still cannot be cancelled from the system.

---

## 10. Succession Planning

**What it does:** Identifies critical hospital roles and tracks the people being developed to fill them.

### Sidebar: 🏆 Succession

> **Access:** Admins and HR Managers have full succession access. Supervisors see only candidates who report directly to them, and only positions containing one of those candidates. Candidate ratings, 9-box placement, readiness, mentor, nomination status, vacancy risk, risk factors, and estimated vacancy date are hidden from Supervisors. They may add, update, and remove development milestones only for those direct-report candidates. **Staff have no access to this module at all.**

#### Critical Positions

These are key roles that would cause significant operational risk if left vacant (e.g., Chief of Surgery, ICU Head Nurse, Emergency Department Director).

**Adding a Critical Position (Admins/HR):**
1. Go to **Succession**.
2. Click **Add Critical Position**.
3. Type the **Position Title**. This is free text, not a list of existing job titles.
4. Choose the **department**, and the **current holder** if the post is filled — leaving the holder blank records the position as **Vacant**.
5. Choose the vacancy risk level — 🟢 **Low**, 🟡 **Medium**, 🟠 **High**, or 🔴 **Critical**. This is your own judgement; the system does not calculate it. It is used to sort and highlight the positions list and the dashboard's at-risk panel.
6. Optionally record an **estimated vacancy date** — a planned retirement, for example — then click **Add Position**.

Admins and HR Managers can record a **quarterly position review** with optional notes. HIMS stores the review time and the linked employee who recorded it. The organisation dashboard highlights high/critical-risk roles with no candidate marked **Ready Now**.

#### Nominating a Successor

There are **two nomination forms, and they do not ask for the same things.** Which one you get depends on where you start:

**From the Succession page** — click **Nominate Successor**. A modal asks for the candidate, the target position, the readiness stage, an optional mentor, and free-text **Notes**, then you click **Submit Nomination**. It has **no score fields**, so HIMS files the nomination with performance and potential both set to **3**, which places the candidate in the middle box of the grid. Edit the candidate afterwards to record the real scores.

The Notes box is where you write *why* — the rationale behind the nomination, or the first goals you have in mind. Two things to know about it. It is **confidential**, treated exactly like the ratings: HR and Admins read it on the candidate page's Nomination card, and a Supervisor opening their own direct report's page does not get that card at all. And it is **written once, at nomination** — the edit form has no Notes field, so revising somebody's scores later will not blank the reasoning you recorded here, but neither can you revise the reasoning itself. Say what you mean the first time; if the thinking changes materially, that belongs in a quarterly position review, which is editable.

**From a critical position** — open the position and click **Nominate Candidate** (the same button appears on the Admin/HR dashboard). This is the full form:

1. Select an employee as a potential successor and the **target position**.
2. Rate their **Performance** (1–5) and **Potential** (1–5) — both required here. The form previews the resulting placement as you type.
3. The system places them on the **9-Box Grid** automatically:

| | Low Potential (1–2) | Medium Potential (3) | High Potential (4–5) |
|---|---|---|---|
| **High Performance (4–5)** | Solid Performer | High Performer | Star |
| **Medium Performance (3)** | Average Performer | Core Player | High Potential |
| **Low Performance (1–2)** | Underperformer | Inconsistent | Rough Diamond |

4. Set their **readiness level**: Ready Now, 1–2 Years, 2–5 Years, or Long Term (5+ yrs). It defaults to 1–2 Years.
5. Assign a **mentor** if applicable.
6. Click **Nominate Candidate**.

This form has **no Notes field** — it asks for the numbers, not the reasoning. A rationale can only be recorded from the Succession page's **Nominate Successor** modal described above, and only at the moment of nomination, so if the *why* matters as much as the scores, start there and edit the scores in afterwards.

The 9-box placement is always recalculated from the scores, so the badge can never disagree with the numbers next to it. One person can be nominated for the same position only once; a second attempt is refused rather than duplicated.

> The table above uses the wording on the candidate and position pages, which is where you read a placement back. The **live preview on the nomination form words four of the nine cells differently** for the same placement — Star appears as "⭐ Future Star", Solid Performer as "✅ Solid Contributor", Core Player as "👷 Core Employee", and Average Performer as "📊 Average". The scores and the box are identical; only the label text differs.

#### Managing Nominations

Both actions live on the candidate's **edit** screen, reached from the ✏️ button in the pipeline:

- Revise **scores, readiness, or mentor** and save. The 9-box placement is recomputed from the new scores. The nomination **Notes** are not on this screen and are not touched by the save, so the rationale recorded at nomination survives every later revision — it just cannot be rewritten.
- **Withdraw Candidate**, at the foot of the same screen, removes the nomination. It asks you to confirm first, because it also deletes their development milestones and cannot be undone.

> There is **no approval step**. Candidates stay in their initial status; nothing promotes a nomination from "proposed" to "approved."

#### Candidate Pipeline

The pipeline table on the Succession page lists **candidate** (with their current position beneath the name), **target position**, **readiness**, **dev progress** and **status**, plus a **View** link and — for Admins/HR — an edit (✏️) button. Supervisors see the same table with **Readiness** and **Status** removed, and only their direct-report candidates in it. The position filter above the table offers only positions you are allowed to see, and a position id typed into the URL by hand is ignored unless it is one of them.

> Scores, 9-box placement, mentor and the nomination **Notes** are **not** columns here — they are on the candidate's own page, where they are also the fields hidden from Supervisors.

#### Leadership Development Paths

For each candidate in your permitted scope you can add **milestones**: courses, assignments, mentoring, rotations, certifications, or projects, each with a target date. Admins/HR can manage every candidate; Supervisors can manage milestones only for direct reports.

Each milestone moves through **Not Started → In Progress → Completed**. The completion date is stamped automatically when you mark it complete, and cleared if you move it back. The share of completed milestones drives the Dev Progress percentage in the pipeline.

#### Readiness Evidence

Open a candidate and, below their milestones, two panels show what HIMS already records about them elsewhere. Readiness is still your judgement, but you can now check it against evidence instead of recollection.

**Learning Evidence** — courses completed against courses enrolled, total CPD hours, and a bar per learning pathway showing how far through it they are. If they have unfinished **required** courses, that is called out: someone with mandatory training outstanding is arguably not ready now, whatever the readiness field says. Their **renewal standing** appears underneath, so a candidate short of their CPD hours is visible at the point of decision.

**Competency Proficiency** — how many assessed competencies are at or above the required level, then a table of each one with its JCI standard, current level, and required level. Only the newest assessment of each competency is counted, and the ten most recent are listed.

> These panels are read-only, and they pull from Learning and Competency rather than storing anything of their own. A candidate who has never been assessed is shown as such rather than as a zero.

---

## 11. Social Recognition

**What it does:** Lets staff send named public or private appreciation. Open to **every** role.

### Sidebar: ⭐ Recognition

#### Posting a Recognition

1. Go to **Recognition** in the sidebar.
2. Click **Give Recognition**.
3. Select the **colleague** you want to recognise.
4. Optionally choose a **hospital value badge**. The list shows each badge's point value, and **No badge** is a valid choice — the post is still recorded, it just carries no points:

| Badge | What it is for | Points |
|---|---|---|
| **Compassion (Kalinga)** | Exceptional care, empathy, and attention to patients or colleagues | 5 |
| **Teamwork (Bayanihan)** | Stepping in, sharing responsibility, helping the team deliver safely | 5 |
| **Innovation (Diskarte)** | A practical improvement that removes friction or improves service | 5 |
| **Clinical Excellence** | Consistently precise, safe, and evidence-based clinical work | 8 |

5. Write a **message** about what they did (required, up to 1000 characters).
6. Choose **Public** or **Private**. Public is preselected.
7. Click **Post Recognition**.

Your own name is excluded from the colleague list, and the server rejects a self-recognition request even if
the form is bypassed. A post is labelled **Supervisor** only when the recipient's recorded `supervisor_id`
matches the author; every other post is labelled **Peer**.

Recognition is never anonymous: the sender and recipient remain visible in both modes. A **Public** post appears on the hospital wall. A **Private** post is visible only to its sender, its recipient, and HR/Admin moderators. It does not appear in public totals, the top-department calculation, or the leaderboard.

Admins and HR Managers can create additional badge types from the **New Value Badge** modal.

#### Reacting & Commenting

- **React** to a post with a 👍 to show your appreciation. Clicking again takes your reaction back — the button toggles.
- **Comment** on posts to add your thoughts. Approved comments are shown directly below the post.

Both require your login to be linked to an employee profile. For a private post, only the sender, recipient, and HR/Admin moderators can react or comment; the server applies the same audience check even if somebody tries the URL directly.

#### Wall, Sent & Received

Use the **Wall**, **Received**, and **Sent** tabs to move between the public hospital feed and recognition involving you. Received and Sent include your private posts. HR Managers and Administrators also have a **Private** moderation tab containing every private post. The summary cards and leaderboard count public approved posts only.

HR Managers and Administrators can approve, flag, or remove posts and comments from the wall. The moderation
change is audited, and the author receives an in-app notification. Recognition never writes a performance
review score or links itself to a formal review.

---

## 12. AI Assistant

**What it does:** A chatbot built into the system that answers questions in plain language and performs actions on your behalf.

### How to Use

1. Click the **🤖** button in the top bar of any page. The assistant opens as a panel down the right-hand side of the screen and stays there while you work — the page content shifts across to make room rather than being covered up.
2. Type your question or instruction. You can write in **English**, **Tagalog**, or **Taglish** (mixed) — it replies in the language you use.
3. Read the response in the panel.

You can drag the panel's left edge to make it wider or narrower, and close it with the **✕** button in its header. On a narrow screen the panel slides over the page instead of pushing it aside.

### Example Questions and Commands

**Questions:**
- "Ano ang PIP?" (What is a PIP?)
- "How do I record a competency assessment?"
- "How do I register for a training session?"

**Commands:**
- "Create a 2027 annual review cycle"
- "Record a competency assessment for Juan Dela Cruz"
- "Set Maria Santos to probationary status" *(if you have permission)*

> **Conversational answers do not have a general live-database reader.** The assistant is grounded with a maintained map of HIMS navigation and rules, but it cannot freely inspect the current dashboard or answer arbitrary questions from all employee records. When executing a supported command, a separate action path can resolve the named record and call the same controller used by the form. For current figures, use the Dashboard or module pages.

> **Check its step-by-step instructions against the screen.** The assistant has been given a description of how HIMS is actually laid out, so its directions are usually right. But it cannot see the page you are on, and if you ask about something HIMS does not do, it may still describe a plausible-sounding way to do it. If it names a button, tab or field you cannot find, it is very likely not there — trust the screen over the assistant, and check this guide or ask HR.

> **A reply beginning ⚠️ is a system problem, not a mistake you made.** It means the assistant could not reach the AI service, and the rest of the line says which part is wrong — most often that the AI provider has retired the model HIMS was configured to use, in which case the message names that model and states that the account credentials are still working. Nothing you typed caused it and retyping it will not help. Copy the whole ⚠️ line to your system administrator: it names exactly what needs changing. Every other part of HIMS keeps working while the assistant is down, including the AI Gap Analysis pages — only the AI-written commentary is missing.

### Commanding the Assistant

The assistant can perform a defined catalogue of create, update, and delete actions through the same controllers used by the web pages. It does **not** expose every form operation. **It follows the same permission rules you do:** an action your role cannot perform through the routes and controllers is refused in chat too.

When you give an instruction, the assistant either:
- **Performs it immediately** (for creates and updates), reporting what happened in the same words the web form would have used
- **Asks you to confirm first** (for deletions and other destructive actions), naming the exact record it will affect

**Confirming a destructive action:** When the assistant says it needs confirmation, reply **"confirm"**, **"confirmed"**, **"yes"**, **"proceed"**, **"do it"**, or **"go ahead"** to go ahead; anything else cancels it and is treated as a fresh instruction. The confirmation offer expires after five minutes — if you answer after that, nothing happens and you will need to give the instruction again.

> **A mistyped confirmation always cancels.** This is the one place where the assistant will not work out what you meant. If you type "confrim", nothing is deleted — the reply says *"Cancelled — nothing was changed. If you meant “confirm”, send the instruction again"*, and you start over. Approving a deletion by accident is not a mistake worth being helpful about.

**If something is unclear:** The assistant will ask for the missing detail rather than guessing. For example, if you say "delete the training session" but there are three sessions with similar names, it will list them and ask which one you mean.

**Typing mistakes are read through, and the assistant tells you how it read them.** "Crate a 2027 annual review cycle" creates the cycle, and the reply opens with *Read “Crate” as “Create”.* so you can see what it acted on — if that reading is wrong, you know immediately rather than wondering why the wrong thing happened. Two limits are deliberate. A **name** is never second-guessed, because an employee called Cruze is not a mistyped word: if a name matches nobody you can see, the assistant answers with the nearest real record as a question — *no cycle matching "2026 Anual Performance Review" — did you mean "2026 Annual Performance Review"?* — and does nothing until you say. And it will re-read at most three words in one message; if more than that looks wrong, the message is more likely to have been misunderstood than mistyped, so it is answered as written.

> **A suggested name is only ever a record you could have found yourself.** The assistant looks for near-misses among the same records your role can already reach, so a "did you mean" can never tell you that someone exists whose page you are not allowed to open.

### Conversations and Memory

Your chats are organised into separate conversations, listed in the panel with the most recent first.

- **Conversation history is shown whenever the assistant opens.** The clock button can collapse or reopen the list when you need more room for the transcript; that preference is remembered in your browser.
- **New chat** starts a fresh conversation. Each one is named automatically from your first question, and you can rename or delete it from the list.
- **Within one conversation the assistant remembers what you have already asked**, so you can ask follow-up questions naturally — "and how long does that take?" works without repeating the subject.
- **Memory does not cross conversations.** Starting a new chat, or switching to an older one, means the assistant only knows what is in *that* conversation.

Everything is saved to your own account. No one else can see your conversations, and you can delete any of them — or clear all of them — from the panel at any time. Your conversation titles and messages are also included in Global Search for your account only; selecting one opens the assistant with that saved session loaded.

### What You Can and Cannot Ask

The assistant follows the same access rules as the rest of HIMS, based on your role. It will not discuss a subject you would not be able to open a page for.

| Subject | Who can discuss it |
|---|---|
| Your own performance reviews and goals | Everyone |
| Competency assessments and credentials | Everyone |
| Learning pathways and courses | Everyone |
| Training schedules and registration | Everyone |
| Employee recognition | Everyone |
| Succession planning and the talent pipeline | Admins, HR Managers, Supervisors |
| Other employees' records, salaries, disciplinary history | Admins, HR Managers, Supervisors |
| Department administration | Admins, HR Managers |
| Hospital-wide analytics (attrition, turnover, comparisons) | Admins, HR Managers |
| User accounts, passwords, and system roles | Admins only |

If you ask about something outside your access, the assistant will say so plainly and suggest raising it with your supervisor or the HR department instead. This is not an error — it is the same boundary that hides those pages from your sidebar.

> If an ordinary question is refused because it happened to mention a restricted word, rephrase it. For example, ask "what does the training cover?" rather than including a colleague's salary in the same sentence.

---

## 13. Administration

### Sidebar: 👥 Employees

Available to **Admins**, **HR Managers**, and **Supervisors** (view only for Supervisors).

- **View** the employee directory and profiles within your scope *(Admins/HR: everyone; Supervisors: direct reports)*
- **Add**, **edit**, and **delete** employees *(Admins and HR Managers)*
- Use **People Manager** on Create/Edit Employee to control eligibility for new **Reports To** assignments. This never changes the employee's HIMS account role.
- Open **Manager Setup** to find People Managers without accounts, Staff-only managers, Supervisor accounts not marked as managers, inactive managers with active reports, and active employees with no manager.

The **Reports To** list contains only active People Managers with Supervisor, HR Manager, or Admin access. It shows name, position, department, and access role. An existing unavailable manager stays visible while editing and is marked **Setup incomplete**, so unrelated employee changes do not silently remove the reporting line. A manager with direct reports cannot have People Manager turned off until those reports are reassigned. Leave, suspension, resignation, and termination remain allowed employment events; HIMS names active reports that now need reassignment.

### Sidebar: 🏢 Departments

Available to **Admins** and **HR Managers**.

- **View** departments with their head, headcount and code
- **Add** a new department: a name (required and unique), an optional department code, and a **clinical** flag

> Departments cannot be edited or deleted from the system once created.

> **The department head cannot be set from this screen, or from anywhere else in HIMS.** `head_employee_id` is populated only by the demo seeder, so a department you create here has no head recorded — which matters because the credential-alert escalation copies the department head in. Departments added through this screen escalate to the employee and their supervisor only.

### Sidebar: 🔐 Users & Access (Admin only)

- **Add User** — create a login account and link it to an employee record
- **Assign roles** (Admin, HR Manager, Supervisor, Staff)
- **Set a new password** for a user from the edit screen
- **Unlock** a locked account, from the button that appears on its row
- **Delete** an account
- **AI Data Settings** — two switches governing what leaves the hospital for the AI provider: whether supervisors' written review comments are included in gap-analysis prompts at all, and whether employee and patient names are blanked out of them first. The panel also names the provider currently configured. Both default to on (comments included, names redacted), and redaction is what HIMS falls back to if the setting cannot be read.

> There is no activate/deactivate switch. After five failed password attempts, the account is locked for 15 minutes; an Administrator can also unlock it earlier from **Users & Access**. The system will not let you remove or demote the last remaining Administrator.

---

## 14. Account & Security

### Changing Your Password

1. Log in to the system.
2. Click your **name** at the bottom of the sidebar to open your Profile.
3. Enter your current password and your new password.
4. Click **Update Password**.

### Forgot Your Password?

1. On the login page, click **"Forgot your password?"**
2. Enter your email address.
3. Check your inbox for a password reset link.
4. Click the link and set a new password. Each link works only once.

> **Note:** The reset email may take a few minutes to arrive. Check your spam/junk folder if you don't see it.

### How the System Protects Your Account

- Passwords are stored hashed, never in readable form.
- After 5 failed attempts, email/IP rate limiting applies and the account is locked for 15 minutes. An Administrator can unlock it earlier.
- Employee phone numbers and credential numbers are encrypted in the database with Laravel `Crypt`; most other domain fields are not field-encrypted.
- Your session ends when you log out.

> **What the system does not do:** there is no two-factor authentication and no comprehensive log of record views. Selected writes — including performance-review lifecycle changes, succession changes, recognition, employee/account administration, compliance operations, and AI-executed actions — are audited, but the audit trail is not a complete history of every edit.

### Logging Out

Click **Logout** at the bottom of the sidebar.

---

## 15. Frequently Asked Questions

### General

**Q: I can't see a module in the sidebar. Why?**
A: Your role does not have access to it. Contact your HR department or system administrator to request a role change.

**Q: The bell icon never shows anything. Is it broken?**
A: Probably not. The bell can carry credential expiry, competency reassessment, renewal-cycle shortfall, CPD verification, and recognition updates. Scheduled credential/reassessment/renewal checks do not appear the instant a record changes. If you believe an alert is missing, check the relevant module and ask HR or your system administrator.

**Q: The page is loading slowly. What should I do?**
A: Try refreshing the page (press F5 or Ctrl+R). If the problem persists, check your internet connection or contact your IT department.

### Performance Reviews

**Q: How do I know when my review is due?**
A: Check the Dashboard — **Pending Reviews** counts the reviews still open in cycles that are still running, and recent reviews are listed below it. A review whose cycle has ended is frozen, so it drops off that count whether or not it was ever marked Finished. The real deadline is the cycle's **end date**: after it passes, the review can no longer be edited.

**Q: I finished scoring a review. How do I send it for approval?**
A: There is no approval routing in this version. Save the review as **Finished** on the scoring form and tell your HR contact directly.

**Q: I saved a review as Finished. Can I still change it?**
A: Yes, as long as the cycle is still running. Change what you need and save again — the signature is re-applied so it always matches what the review says now. Once the cycle's end date passes, the review becomes **Completed** and no further edits are accepted.

**Q: Can I respond to or appeal my own review?**
A: Yes. After the reviewer marks it Finished, open the review and use **Employee Response**. You can add an explanation or appeal, but cannot change scores or reviewer comments. Once the cycle closes, you can save the final response and acknowledge the review; acknowledgement locks the response.

**Q: The Score button has disappeared from a review I was working on.**
A: Its review cycle has ended, so the review is frozen. Everything you saved is intact and still readable; nothing further can be written. If the cycle ended sooner than intended, an Admin or HR Manager can extend its end date, which reopens every review inside it.

**Q: How do I add a goal or an improvement plan?**
A: Goals and PIPs are shown on the review page but cannot be created from the system yet. Ask your system administrator.

### Competency & Credentials

**Q: My licence is showing as "Expired" but I already renewed it. What do I do?**
A: Ask your supervisor or HR to update the credential record with the new expiry date. The status will change by itself once the date is corrected.

**Q: Will I be emailed before my licence expires?**
A: Only if your hospital administrator has configured email delivery. The system raises expiry alerts into the bell at the top of the screen and marks the credential on your **My Development** page, but whether those same alerts go out by email depends on the mail settings — they are off by default. Check the Dashboard or the **My Development** page regularly rather than waiting for an email.

**Q: What does a competency score of 3 mean?**
A: Scores are on a 1–5 scale: 1 = Novice, 2 = Basic, 3 = Competent, 4 = Proficient, 5 = Expert.

### Learning & Training

**Q: I enrolled in a course. Where do I take it?**
A: Course content is not delivered in HIMS. If a course was assigned to you, ask your training coordinator where its material or class is delivered.

**Q: How do I enrol one of my staff in a course?**
A: Open **Learning → Required Training → Assign Training**. Tick one or more courses or upcoming sessions, choose the employee/department/role/all-active target, and assign. A Supervisor's single-employee list is scoped, but department/role/all-active targets are organisation-wide selections; HR Managers and Admins can see the resulting organisation-wide rosters. See [§8](#8-learning-oversight-tabs).

**Q: Where is my certificate of completion?**
A: The system does not issue certificates.

**Q: How do I cancel my training registration?**
A: You can't do this yourself. Ask an Administrator to remove your registration.

**Q: How do I give feedback on a training session?**
A: Open the session from **Learning** → **Sessions** and click **Give Feedback** in the Feedback Summary panel; the form opens on the page, and you send it with **Submit Feedback**. The button only appears once the instructor has marked you present — if you attended but cannot see it, your attendance has not been recorded yet, so ask the instructor or HR.

**Q: I attended a session but it still says "registered". Why?**
A: Attendance is marked from the session roster by the instructor or by Admin/HR, not by you. Until someone marks you **Attended** you stay listed as *Registered*, and the feedback form stays hidden. There is no QR code and no self-check-in.

**Q: I finished a course. How do I mark it complete?**
A: You do not — someone else does. Ask your Supervisor, an HR Manager, or an Admin to open the course or its **Learning → Required Training** roster modal and click **Mark complete** on your row. Once they do, the course's CPD hours are credited automatically and already verified.

**Q: How do I get my conference or webinar hours onto my CPD record?**
A: Go to **Learning** → **My CPD** → **Record CPD** and enter it yourself. External activities are saved as unverified and wait for an HR Manager or Admin to approve them; in-house courses and training sessions are accepted as verified straight away. You do not need to log hours for a course somebody has already marked you complete on — those were credited at that moment, and entering them again would double-count.

**Q: A course appeared on my list that I never enrolled in. What is it?**
A: It was assigned to you — by department, role, or individually — because the hospital requires it. It may carry a required-by date. Course self-enrolment is not available, so current course enrolments originate from Required Training. See [§8](#8-learning-oversight-tabs).

**Q: What does "at risk" mean on My Renewal Cycles?**
A: It means that at the rate you have been earning hours, you would not reach the total before the cycle closes. It is a warning while there is still time, not a failure. **Short** is the state after a cycle has closed without the hours being reached.

**Q: I logged hours but my renewal cycle has not moved.**
A: Only verified CPD counts towards a cycle. If the activity was external it is waiting on HR approval — ask HR to verify it, and the hours will appear against the cycle. Hours credited by a course completion are verified from the start, so those move the cycle immediately.

**Q: My department's compliance rate is stuck at 0%. Why?**
A: Nobody has been recorded as finishing yet. For an assigned **course**, someone with the right role has to click **Mark complete** on each person's row; for a **session**, the instructor has to mark people **Attended** on the session's attendance sheet. Enrolling in a course does not complete it, and the percentage counts *people finished*, not how far through the material anyone is.

**Q: I do not have a HIMS login. Am I being tracked at all?**
A: Yes. Assignments, renewal cycles, credentials, and compliance reporting all work from your employee record, not from an account. Your supervisor and department head are notified about your expiring credentials and due reassessments, so somebody is told even though you have no notification bell. Whether *you* also get a copy by email depends on your deployment: credential-alert email is **off by default**, and until an administrator switches it on there is nowhere for your own copy to go. You appear in the **unreachable** figure on the Account Coverage report until then.

### AI Assistant

**Q: The AI gave me an incorrect answer. What should I do?**
A: The conversational assistant has no general live-database reader. It uses a maintained HIMS guide plus model knowledge, while supported commands use a separate controller-backed action path. Verify current figures and important decisions against the module pages and with your supervisor or HR.

**Q: The AI described buttons and fields I can't find on the page.**
A: Trust the screen, not the assistant. It has been given a description of how HIMS is laid out, but it cannot see the page you are on, and when asked about something HIMS does not support it may describe a plausible way to do it that does not exist. Check this guide for the real steps, and tell your system administrator which question produced the wrong answer — the description the assistant works from can be corrected.

**Q: Can the assistant perform actions for me?**
A: Yes, as long as your role allows them. It can create records, update them, and delete them (with confirmation), using the same permissions you have when working through the forms. An admin can command the assistant to create a review cycle or delete a user; a supervisor cannot.

**Q: What happens if I tell it to delete something important?**
A: The assistant asks you to confirm first, and names the exact record it will affect. You reply "confirm" to proceed or anything else to cancel. The confirmation offer expires after five minutes. A **misspelt** confirmation counts as "anything else" — type "confrim" and the deletion is cancelled, not carried out.

**Q: Does it matter if I make a typing mistake?**
A: Usually not. "Crate a 2027 annual review cycle" creates the cycle, and the reply tells you how your message was read — *Read “Crate” as “Create”.* — so you can check. Names are the exception: rather than guessing at one, the assistant offers the nearest record it can find as a question (*did you mean "2026 Annual Performance Review"?*) and waits. See [§12](#12-ai-assistant).

**Q: Is the AI assistant available in Filipino/Tagalog?**
A: Yes. Type in English, Tagalog, or Taglish and it will reply in the same language.

**Q: Does the assistant remember what I asked earlier?**
A: Within one conversation, yes — so follow-up questions work naturally. It does not carry memory between conversations, so clicking **New chat** starts it fresh.

**Q: The assistant replied with a ⚠️ line instead of an answer.**
A: The AI service could not be reached, and the line tells you which part is at fault. The usual cause is that the AI provider has retired the model HIMS was set up to use; when that happens the message names the retired model and confirms the account credentials still work, so your administrator knows to update the model rather than the key. Nothing you typed caused it, and asking again will produce the same line. Send the whole message to your system administrator. See [§12](#12-ai-assistant).

**Q: The assistant said a question is outside my access. Why?**
A: It follows the same role-based rules as the rest of HIMS, so it will not discuss subjects your role cannot open a page for — succession planning, other employees' records, department administration, hospital-wide analytics, or user accounts, depending on your role. See the table in [§12](#12-ai-assistant). Ask your supervisor or HR if you need that information for your work.

**Q: How do I delete a conversation?**
A: Open the assistant panel, find the conversation in the list, and delete it there. You can also clear all of your conversations at once.

### Account Issues

**Q: I didn't receive the password reset email. What should I do?**
A: Wait a few minutes and check your spam/junk folder. If it still hasn't arrived, contact your system administrator.

**Q: I've been blocked after too many login attempts. What now?**
A: Wait 15 minutes for the account lock to expire, or ask an Administrator to unlock the account. If you have forgotten the password, use the reset link instead.

---

## Need More Help?

If you have questions that aren't covered in this guide:

1. Click the **❓ Help** button in the top bar for quick FAQ answers.
2. Use the **AI Assistant** for general questions in plain language, or to carry out a task by describing it.
3. Contact your **HR Department** or **System Administrator** for account and access issues.

---

*HIMS Performance & Development Module — User Guide*
*Last updated: August 15, 2026*
