# HIMS User Guide
## Hospital Information Management System — Performance & Development Module

Welcome to the **HIMS Performance & Development Module**! This guide describes what the system does today and how to use each screen. No technical knowledge required.

> **This guide describes only what is actually in the system.** If a capability is not described here, it is not available — please do not assume a feature exists because a similar system has it.

---

## Table of Contents

1. [Getting Started](#1-getting-started)
2. [Your Dashboard](#2-your-dashboard)
3. [Performance Management](#3-performance-management)
4. [Competency Management](#4-competency-management)
5. [AI-Assisted Gap Analysis](#5-ai-assisted-gap-analysis)
6. [Learning Management](#6-learning-management)
7. [Training Management](#7-training-management)
8. [Succession Planning](#8-succession-planning)
9. [Social Recognition](#9-social-recognition)
10. [AI Assistant](#10-ai-assistant)
11. [Administration](#11-administration)
12. [Account & Security](#12-account--security)
13. [Frequently Asked Questions](#13-frequently-asked-questions)

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
- **Top bar** — A search box, a notifications bell 🔔, and a help/FAQ button ❓.
- **Main content area** — Displays the page you selected.
- **AI Assistant** — A floating 🤖 button at the bottom-right of every page.

> **About the bell 🔔:** the notifications dropdown is in place but no part of the system sends notifications yet, so it always reads "You're all caught up."

### Your Role

What you can reach depends on your assigned role:

| Role | What You Can Do |
|---|---|
| **Admin** | Everything — all modules, plus user accounts |
| **HR Manager** | All modules except user accounts |
| **Supervisor** | Create and score reviews, create competency assessments and credentials, run gap analysis, schedule training sessions, view succession planning |
| **Staff** | Dashboard, performance and competency pages, learning, training registration, and recognition |

> **Note:** Menu items you do not have access to are hidden from your sidebar.
>
> **What you see within a module varies.** In **Employees**, **Performance**, and **Gap Analysis** the system limits you to what your role should see — Admins and HR Managers see everyone, Supervisors see their own department, and Staff see only themselves. In the other modules (Competency, Learning, Training, Succession, Recognition) anyone who can open the page sees every record on it.

---

## 2. Your Dashboard

The **Dashboard** is the first page you see after logging in, and it changes depending on your role.

**Admins and HR Managers** see a hospital-wide view: active headcount, pending reviews, expiring and expired credentials, active enrolments, recognitions this month, critical competency gaps, upcoming training sessions, headcount by department, competency hotspots, recent reviews, at-risk critical positions, credential alerts, and recent recognitions. Admins additionally see system account totals.

**Supervisors** see the same shape of information limited to their own department — team size, pending reviews, expiring credentials, critical gaps, active enrolments, and average team score. If your account is not linked to a department, the page will tell you so and show nothing else.

**Staff** see a personal view of their own records.

Every figure on the dashboard is calculated live from the database.

---

## 3. Performance Management

**What it does:** Records employee performance through review cycles and structured KPI scoring.

### Sidebar: 📋 Performance

#### Review Cycles

A **review cycle** is an evaluation period (monthly, quarterly, semi-annual, or annual) with a status of *planned*, *active*, or *closed*.

**For Admins/HR Managers:**
1. Click **Performance** in the sidebar.
2. Click **Create Review Cycle**.
3. Fill in the cycle name, type, start date, and end date.
4. Click **Save**.

Cycles can also be edited after creation.

#### Performance Reviews

A **performance review** evaluates one employee within one review cycle. An employee can only have one review per cycle.

**Creating a Review (Admins/HR/Supervisors):**
1. Go to **Performance** → click **Create Review**.
2. Select the **employee**, the **review cycle**, the review type, and the **KPIs** to score.
3. Click **Create & Score** — you are taken straight to the scoring form with the chosen KPIs already listed.
4. Enter a score for each KPI and save.

The review's status (Draft, Self Assessment, Supervisor Review, Calibration, Completed) is a field you set on the scoring form. There is **no submit-and-approve workflow** — nothing routes a review to another person for sign-off, and there is no digital signature.

**Viewing a Review:**
Open **Performance** and click any review in the list to see its details, KPI scores, goals, and any linked improvement plan. Admins and HR Managers see all reviews, Supervisors see their department's, and Staff see only their own.

#### Goals

Goals attached to a review are shown on the review page with their title, target date, and progress percentage.

> Goals are **display only** in this version — there is no screen for adding a goal or updating its progress. Goal records must be loaded into the database directly.

#### Performance Improvement Plans (PIPs)

If a review has a linked improvement plan, it is shown on that review's page, and the count of active PIPs appears on the Performance page.

> PIPs are **display only** in this version — the system does not create a PIP automatically from a low score, and there is no screen for creating or editing one.

---

## 4. Competency Management

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
1. Go to **Competency** → **Create Assessment**.
2. Select the employee and the competency, and rate their current proficiency level.
3. Choose the assessment method (observation, self assessment, supervisor rating, practical test, or written exam).
4. Add assessor notes if needed.
5. Click **Save**.

#### Credentials (Licenses & Certifications)

The system records clinical licences and certifications (e.g., PRC License, Board Certifications, BLS, ACLS). Each credential's status is worked out automatically from its expiry date:

| Colour | Status | Meaning |
|---|---|---|
| 🟢 Green | **Active** | Valid and current |
| 🟡 Yellow | **Expiring Soon** | Expires within 30 days |
| 🔴 Red | **Expired** | Past the expiry date |
| ⚪ Grey | **Revoked** | Marked as revoked |

**Adding a Credential (Admins/HR/Supervisors):**
1. Go to **Competency** → **Add Credential**.
2. Enter the credential name, type, licence number, issue date, and expiry date.
3. Click **Save**.

> **These statuses are shown on screen only.** The system does **not** send renewal reminders or escalation emails, and nothing alerts HR when a credential expires. Someone has to look at the Credential Alerts panel on the Competency page or the Dashboard.

#### Department Skills Gap Matrix

The Competency page shows a department-level matrix of where proficiency falls short of the required level, so managers can see at a glance if a ward is missing a critical skill.

---

## 5. AI-Assisted Gap Analysis

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

> **If the AI service is unavailable**, the numbers and tables still work; only the AI-written commentary is replaced with a ⚠️ message.

---

## 6. Learning Management

**What it does:** A catalogue of courses and learning pathways that employees can enrol in, plus a record of CPD hours.

### Sidebar: 📚 Learning

#### Course Catalog

Browse available courses organised by category:
- **Compliance** — mandatory training (Infection Control, Fire Safety, etc.)
- **Clinical** — clinical skills development
- **Soft Skills** — communication, leadership, teamwork

Each course record carries CPD hours, difficulty, duration, passing score, and retake limit.

#### Enrolling in a Course (all roles)

1. Go to **Learning** in the sidebar.
2. Browse the available courses and open one.
3. Click **Enroll**.

> **Course content is not delivered inside HIMS.** There is no lesson player and no quiz engine — enrolling records that you are taking the course; the learning itself happens elsewhere. Progress percentage is a stored field, not something the system advances for you.

#### Creating a Course (Admins/HR)

1. Go to **Learning** → **Create Course**.
2. Fill in the course title, description, category, and CPD points.
3. Click **Save**.

#### Learning Pathways

Pathways group several courses into an ordered curriculum (e.g., "Critical Care Nurse Pathway"). Admins and HR Managers can create them from the Learning page.

#### CPD (Continuing Professional Development) Records

The **CPD** page lists recorded CPD hours per employee, and your yearly total appears on the dashboard.

> CPD records are **display only** in this version — completing a course does not add CPD hours automatically, and there is no form for entering them. The records must be loaded into the database directly.

#### Certificates

The Learning page shows a count of certificates on record.

> **The system does not issue certificates.** Nothing generates a certificate when you finish a course, and there is no download or QR verification.

---

## 7. Training Management

**What it does:** Schedules instructor-led training sessions, manages venues, and takes registrations.

### Sidebar: 🎓 Training

#### Viewing Training Sessions

The Training page lists **upcoming sessions** (workshops, seminars, drills) with their date, venue, instructor, and how many people have registered. It is a list, not an interactive calendar.

#### Registering for Training (all roles)

1. Go to **Training** in the sidebar.
2. Find an upcoming session and open it.
3. Click **Register**.

The system refuses the registration if the session is already at capacity, if you have already registered, or if your login is not linked to an employee profile.

> **Registrations cannot be cancelled from the system.** Ask an Administrator if you need one removed.

#### Creating a Training Session (Admins/HR/Supervisors)

1. Go to **Training** → **Create Session**.
2. Fill in the session title, date, start and end time, venue, instructor, and maximum capacity.
3. Click **Save**.

The system prevents two sessions being booked into the same venue at the same date and time.

#### Venues (Admins/HR)

Go to **Training** → **Venues** to add classrooms, simulator rooms, and other spaces with their capacity.

#### Attendance & Feedback

> **Attendance is not recorded.** There is no check-in of any kind, and no way to mark someone present, absent, or late — so the "Avg Attendance" figure on the Training page will read 0%.
>
> **There is no feedback form.** The page shows average ratings and comments if feedback rows exist in the database, but the system provides no way to submit them.
>
> There are no pre-tests or post-tests.

---

## 8. Succession Planning

**What it does:** Identifies critical hospital roles and tracks the people being developed to fill them.

### Sidebar: 🏆 Succession

> **Access:** Admins, HR Managers, and Supervisors. Supervisors can view; only Admins and HR Managers can add or change positions and candidates. **Staff have no access to this module at all.**

#### Critical Positions

These are key roles that would cause significant operational risk if left vacant (e.g., Chief of Surgery, ICU Head Nurse, Emergency Department Director).

**Adding a Critical Position (Admins/HR):**
1. Go to **Succession**.
2. Click **Add Critical Position**.
3. Select the department, role, and current holder.
4. Choose the vacancy risk level — **low**, **medium**, **high**, or **critical**. This is your own judgement; the system does not calculate it. It is used to sort and highlight the positions list and the dashboard's at-risk panel.

#### Nominating a Successor

1. From the critical positions list, click **Nominate Candidate**.
2. Select an employee as a potential successor.
3. Rate their **Performance** (1–5) and **Potential** (1–5). The form previews the resulting placement as you type.
4. The system places them on the **9-Box Grid** automatically:

| | Low Potential (1–2) | Medium Potential (3) | High Potential (4–5) |
|---|---|---|---|
| **High Performance (4–5)** | Solid Performer | High Performer | ⭐ Star Talent |
| **Medium Performance (3)** | Average Performer | Core Contributor | High Potential |
| **Low Performance (1–2)** | Underperformer | Inconsistent | Rough Diamond |

5. Set their **readiness level**: Ready Now, Ready in 1–2 Years, Ready in 2–5 Years, or Long Term.
6. Assign a **mentor** if applicable.
7. Click **Save**.

The 9-box placement is always recalculated from the scores, so the badge can never disagree with the numbers next to it.

#### Managing Nominations

- **Edit** a candidate to revise scores, readiness, or mentor.
- **Withdraw** a candidate to remove the nomination. This also deletes their development milestones and cannot be undone.

> There is **no approval step**. Candidates stay in their initial status; nothing promotes a nomination from "proposed" to "approved."

#### Candidate Pipeline

A single view of everyone nominated across the hospital — candidate, target role, 9-box placement, readiness, development progress, and status. Filter by position.

#### Leadership Development Paths

For each candidate you can add **milestones**: courses, assignments, mentoring, rotations, certifications, or projects, each with a target date.

Each milestone moves through **Not Started → In Progress → Completed**. The completion date is stamped automatically when you mark it complete, and cleared if you move it back. The share of completed milestones drives the Dev Progress percentage in the pipeline.

---

## 9. Social Recognition

**What it does:** A recognition board where staff can publicly appreciate each other's contributions. Open to **every** role.

### Sidebar: ⭐ Recognition

#### Posting a Recognition

1. Go to **Recognition** in the sidebar.
2. Click **Create Post**.
3. Select the **colleague** you want to recognise.
4. Choose a **badge** that best describes their contribution:

| Badge | Meaning |
|---|---|
| 💙 **Compassion (Kalinga)** | Exemplary patient bedside manner |
| 🤝 **Teamwork (Bayanihan)** | Helping colleagues in understaffed shifts |
| 💡 **Innovation (Diskarte)** | Creative solutions to problems |
| ⭐ **Clinical Excellence** | Zero-error documentation or procedures |

5. Write a short message about what they did.
6. Click **Post**.

You cannot recognise yourself — the database rejects it.

Admins and HR Managers can create additional badge types.

#### Reacting & Commenting

- **React** to a post with a 👍 to show your appreciation. A reaction **cannot be taken back** once given.
- **Comment** on posts to add your thoughts.

Both require your login to be linked to an employee profile.

#### Recognition Wall & Leaderboard

The main feed shows recent recognitions across the hospital, alongside a monthly leaderboard of the most-recognised staff and departments.

> Posts carry a moderation status internally, but there is no screen for moderating or removing a post. Contact an Administrator if something needs to come down.

---

## 10. AI Assistant

**What it does:** A chatbot built into the system that answers questions in plain language.

### How to Use

1. Click the floating **🤖 AI Assistant** button at the bottom-right of any page.
2. Type your question. You can write in **English**, **Tagalog**, or **Taglish** (mixed) — it replies in the language you use.
3. Read the response in the chat panel.

### Example Questions You Can Ask

- "Ano ang PIP?" (What is a PIP?)
- "How do I record a competency assessment?"
- "What should I look for when nominating a successor?"

> **The assistant is told it works for a Philippine hospital HR system, but it cannot read your live HIMS data.** It answers from general knowledge, not from your records. For actual figures, use the Dashboard or the module pages. Always verify anything important with your supervisor or HR.

### Chat History

Your conversation is saved to your own account, and you can reopen the panel to see past messages or clear your history from it. The saved history is a record for you to read — it is not sent back to the assistant, so each question is answered on its own without memory of the previous ones.

---

## 11. Administration

### Sidebar: 👥 Employees

Available to **Admins**, **HR Managers**, and **Supervisors** (view only for Supervisors).

- **View** the employee directory and open any employee's profile
- **Add**, **edit**, and **delete** employees *(Admins and HR Managers)*

### Sidebar: 🏢 Departments

Available to **Admins** and **HR Managers**.

- **View** departments with their head and headcount
- **Add** a new department with a name and department code

> Departments cannot be edited or deleted from the system once created.

### Sidebar: 🔐 Users & Access (Admin only)

- **Create** login accounts and link them to an employee record
- **Assign roles** (Admin, HR Manager, Supervisor, Staff)
- **Set a new password** for a user from the edit screen
- **Delete** an account

> There is no activate/deactivate switch and no account lockout — an account either exists or is deleted. The system will not let you remove or demote the last remaining Administrator.

---

## 12. Account & Security

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
- After 5 failed login attempts from the same email and network address, further attempts are blocked for a short period.
- Your session ends when you log out.

> **What the system does not do:** there is no two-factor authentication, and the system does not keep an activity log of who viewed or changed a record.

### Logging Out

Click **Logout** at the bottom of the sidebar.

---

## 13. Frequently Asked Questions

### General

**Q: I can't see a module in the sidebar. Why?**
A: Your role does not have access to it. Contact your HR department or system administrator to request a role change.

**Q: The bell icon never shows anything. Is it broken?**
A: No — the system does not generate notifications yet. Check the Dashboard for anything needing your attention.

**Q: The page is loading slowly. What should I do?**
A: Try refreshing the page (press F5 or Ctrl+R). If the problem persists, check your internet connection or contact your IT department.

### Performance Reviews

**Q: How do I know when my review is due?**
A: Check the Dashboard — pending reviews are counted there, and recent reviews are listed.

**Q: I finished scoring a review. How do I send it for approval?**
A: There is no approval routing in this version. Set the review's status on the scoring form and tell your HR contact directly.

**Q: How do I add a goal or an improvement plan?**
A: Goals and PIPs are shown on the review page but cannot be created from the system yet. Ask your system administrator.

### Competency & Credentials

**Q: My licence is showing as "Expired" but I already renewed it. What do I do?**
A: Ask your supervisor or HR to update the credential record with the new expiry date. The status will change by itself once the date is corrected.

**Q: Will I be emailed before my licence expires?**
A: No. Credential status is shown on screen only — check the Credential Alerts panel on the Competency page or the Dashboard.

**Q: What does a competency score of 3 mean?**
A: Scores are on a 1–5 scale: 1 = Novice, 2 = Basic, 3 = Competent, 4 = Proficient, 5 = Expert.

### Learning & Training

**Q: I enrolled in a course. Where do I take it?**
A: Not in HIMS — the system records your enrolment, but course content is delivered elsewhere. Ask your training coordinator.

**Q: Where is my certificate of completion?**
A: The system does not issue certificates.

**Q: How do I cancel my training registration?**
A: You can't do this yourself. Ask an Administrator to remove your registration.

**Q: How do I give feedback on a training session?**
A: There is no feedback form in the system yet. Pass your feedback to your training coordinator.

### AI Assistant

**Q: The AI gave me an incorrect answer. What should I do?**
A: The assistant does not read your live HIMS data — it answers from general knowledge. Always verify anything important against the module pages and with your supervisor or HR. Rephrasing the question often helps.

**Q: Is the AI assistant available in Filipino/Tagalog?**
A: Yes. Type in English, Tagalog, or Taglish and it will reply in the same language.

### Account Issues

**Q: I didn't receive the password reset email. What should I do?**
A: Wait a few minutes and check your spam/junk folder. If it still hasn't arrived, contact your system administrator.

**Q: I've been blocked after too many login attempts. What now?**
A: Wait a short while and try again — the block clears by itself. If you have forgotten the password, use the reset link instead.

---

## Need More Help?

If you have questions that aren't covered in this guide:

1. Click the **❓ Help** button in the top bar for quick FAQ answers.
2. Use the **AI Assistant** for general questions in plain language.
3. Contact your **HR Department** or **System Administrator** for account and access issues.

---

*HIMS Performance & Development Module — User Guide*
*Last updated: August 6, 2026*
