# HR

An HR system that any company can run for itself, one installation per company, to manage its own staff. It does not assume what kind of business the company is in.

## Language

### Employees and their lifecycle

**Employee**:
A person who works, or has worked, for the company, covering one unbroken period of work from Start Date to Leaving Date. Each Employee has exactly one account for signing in to the HR system. A person who is rehired becomes a new Employee, not linked to their earlier one.
_Avoid_: User, account, staff member

**Future Employee**:
An Employee who has accepted an offer but whose Start Date has not arrived yet. HR can prepare their records, but they cannot sign in until their Start Date.
_Avoid_: New hire, pre-hire, starter, pending employee

**Start Date**:
The first day an Employee works for the company. From this day on, they can sign in. It can't move past the first day of any of their Work Entries, or of their Pending or Approved Time Off or Travel Requests.
_Avoid_: Hire date, joining date, first day

**Former Employee**:
An Employee who has left the company. Their records are kept, but they can no longer sign in.
_Avoid_: Leaver, ex-employee, deleted user, inactive user

**Leaving Date**:
The last day an Employee works for the company. From the next day on, they are a Former Employee. It can't fall before the last day of any of their Work Entries, or of their Pending or Approved Time Off or Travel Requests; those must be cancelled or moved first. An Employee can't be given a Leaving Date while it would leave one of their Reports with no Manager and with Pending Work Entries, Time Off, Travel Requests or Travel Expenses, or an Open Performance Evaluation.
_Avoid_: End date, termination date, exit date

### Reporting line

**Manager**:
The one Employee that another Employee reports to directly. An Employee has at most one Manager; any number of Employees have none, such as a CEO or co-founders. An Employee with no Manager records no Work Entries, since nobody could approve them, and their Time Off is Approved as soon as they request it. An Employee's Manager can't be removed while they have Pending Work Entries, Time Off, Travel Requests or Travel Expenses, or an Open Performance Evaluation, which nobody could then decide.
_Avoid_: Boss, supervisor, line manager, team lead

**Report**:
An Employee anywhere below another Employee in the reporting line, whether directly or through other Managers.
_Avoid_: Subordinate, team member

**Direct Report**:
An Employee one level below another in the reporting line: one whose Manager they are, or, while that Manager has left, the next Manager up who hasn't. Future Employees count, Former ones don't. The Team page lists an Employee's Direct Reports, so "Team" means those, never a Department.
_Avoid_: Direct, team member, subordinate

**Reporting Chain**:
An Employee's place in the reporting line: their Manager, that Manager's Manager, and so on up to an Employee with no Manager, then the Employee themselves, then the Employees they are the Manager of. It reaches only one level down, not to every Report. Every Employee can always see their own, on their Profile, whatever their Roles.
_Avoid_: Hierarchy, org path, chain of command, management line

### Organisation

**Department**:
A part of the company that an Employee belongs to, such as Operations or Finance. Each Employee belongs to exactly one Department, which is separate from their reporting line.
_Avoid_: Team, division, unit, group

**Project**:
A piece of work that Employees can be assigned to, running from a start date to an optional end date. A Project may belong to a Client, or to no Client if it is internal.
_Avoid_: Engagement, contract, account, job

**Internal Project**:
The one built-in Project, with no Client, that represents work for the company itself. Every Employee always has an Internal Assignment to it for their whole period of work.
_Avoid_: Internal, bench, overhead, company project

**Client**:
An outside organisation the company does work for. Companies that do not work for outside organisations have no Clients. An Employee works for a Client only through an Assignment to one of its Projects.
_Avoid_: Customer, account, partner

**Assignment**:
An Employee working on a Project for a period of time, with a Job Title, Pay, a start date, an optional end date and a Capacity. An Employee can have Assignments to several Projects at once, and so can work for several Clients at once. An Assignment with no Work Entries or Travel Requests can be deleted, its Pay with it; once it has any, it can only be ended. The Internal Assignment is never deleted.
_Avoid_: Allocation, staffing, placement, membership

**Capacity**:
The whole percentage of an Employee's time, from 0% to 100%, that an Assignment is set to take, as in "Cara is assigned to Relaunch with 60% capacity". Every Assignment has one, set by hand and never worked out from other Assignments. An Internal Assignment starts at 0%.
_Avoid_: Share of time, share, allocation, percentage, FTE, load

**Internal Assignment**:
An Employee's Assignment to the Internal Project. Every Employee has exactly one, running from their Start Date to their Leaving Date. Its Job Title is the Employee's internal Job Title, the one the directory shows.
_Avoid_: Internal position, bench

**Pay**:
What the company pays an Employee for their work on one Assignment. It is either Fixed Pay or Hourly Pay, so an Employee with several Assignments can have several kinds of Pay at once. An Assignment's Pay can change over time, and each change takes effect from a given date.
_Avoid_: Salary, wage, compensation, rate, remuneration

**Fixed Pay**:
Pay of a set amount for each month, regardless of hours worked.
_Avoid_: Salary, base pay, flat rate

**Hourly Pay**:
Pay of a set amount for each hour worked on the Assignment.
_Avoid_: Hourly rate, wage, pay rate

**Job Title**:
What an Employee does on a particular Assignment, such as "Senior Developer" or "Tech Lead". An Employee with several Assignments can have several Job Titles at once.
_Avoid_: Role, position, function

### Time

**Work Entry**:
A record of how many hours an Employee worked on one Assignment on one date, with a description of what was done, as in "Cara worked 3 hours on Relaunch on 10 Nov reviewing the API". An Employee can record several Work Entries for the same Assignment on the same date. Work Entries stand on their own and are not grouped into Timesheets. No Work Entry can fall on a day of the Employee's Pending or Approved Time Off: a day is either worked or off.
_Avoid_: Booking, time entry, timesheet, timesheet item, time log, worked hours

**Pending**:
The state of a Work Entry or Travel Expense that has not been approved or Declined yet, or whose approval was withdrawn. A Pending one can still be changed or deleted.
_Avoid_: Draft, open, submitted, unapproved

**Approved**:
The state of a Work Entry whose hours, or a Travel Expense whose amount, have been accepted as correct. It cannot be changed or deleted, but it can be Reopened so it can be corrected and then approved again. Several can be approved at once, but each is approved in its own right. Only the Employee's Manager approves their Work Entries and Travel Expenses (while that Manager has left, the next Manager up who hasn't), even after the Employee has left. No Role can grant approving, and no Employee can approve their own.
_Avoid_: Signed off, confirmed, validated, accepted

**Reopen**:
To send an Approved Work Entry or Travel Expense back to Pending, withdrawing its approval so it can be corrected, or a Completed Performance Evaluation back to Open. Only someone whose Role grants reopening for that Employee can reopen it; being the Employee's Manager is not enough on its own. No Employee can reopen their own.
_Avoid_: Unlock, unapprove, reject, revert

### Time Off

**Time Off**:
One or more whole days an Employee asks to be away from work, from a first to a last date, as in "Cara requested Time Off from 22 to 24 Dec". Only weekdays count as days of Time Off; weekends never do, and public holidays count like any other weekday. Each Time Off is either Paid Time Off or Unpaid Time Off. It is Pending until the Employee's Manager approves or declines it (while that Manager has left, the next Manager up who hasn't). The Time Off of an Employee with no Manager is Approved as soon as they request it. An Employee's Time Off can't overlap their other Pending or Approved Time Off, or any day they already have Pending or Approved Work Entries on, and one Time Off covers at most a year; longer leave is several.
_Avoid_: Holiday, leave, vacation, absence, day off, PTO

**Paid Time Off**:
Time Off the company still pays for, using up the Employee's Paid Days for the year each day falls in. It can only be requested while enough Paid Days are left, counting both Pending and Approved Paid Time Off.
_Avoid_: Paid leave, annual leave, holiday

**Unpaid Time Off**:
Time Off the company doesn't pay for. It uses no Paid Days, so any Employee can request it, including one with no Paid Days at all.
_Avoid_: Unpaid leave

**Paid Days**:
How many days of Paid Time Off an Employee gets each calendar year, as in "Cara has 20 Paid Days a year". Each Employee has one number that applies to every year, starting at none; changing it changes every year, past ones included. Unused Paid Days are lost when the year ends.
_Avoid_: Allowance, entitlement, balance, holiday days, PTO days

**Declined**:
The state of Time Off, a Work Entry or a Travel Request the Employee's Manager has refused (while that Manager has left, the next Manager up who hasn't), always with a reason the Employee can see. No Role can grant declining, and no Employee can decline their own. Declined Time Off uses no Paid Days and is kept on record; the Employee can request other dates instead. Only a Pending Work Entry or Travel Expense can be declined, several at once with one reason; it counts nowhere, and the Employee can change it, which sends it back to Pending without the reason, or delete it. A Declined Travel Request can have no Travel Expenses.
_Avoid_: Rejected, denied, refused

**Cancelled**:
The state of Time Off or a Travel Request the Employee withdrew, whether Pending or Approved, before its first day. Cancelled Time Off uses no Paid Days. Once Time Off or a Travel Request has started, it can no longer be cancelled or changed; to change its dates, the Employee cancels it and asks again.
_Avoid_: Withdrawn, revoked, deleted

### Travel

**Travel Request**:
An Employee asking to travel for work on one of their Assignments, with a destination, a first and a last date and a purpose, as in "Cara made a Travel Request to go to Berlin from 3 to 5 Dec for the Relaunch workshop". Travel for the company itself uses the Internal Assignment. Every day from the first to the last date counts, weekends included, and all of them must fall within the Assignment's dates. An Employee's Travel Request can't share a day with their other Pending or Approved Travel Requests, or with their Pending or Approved Time Off. Every Employee makes their own Travel Requests, and nobody makes one for someone else. It is Pending until the Employee's Manager approves or declines it (while that Manager has left, the next Manager up who hasn't), and the Travel Request of an Employee with no Manager is Approved as soon as they make it. It carries no estimate of what the travel will cost. Only an Approved Travel Request can have Travel Expenses, and they stay claimable if it is later Cancelled.
_Avoid_: Trip, business trip, travel, journey

**Travel Expense**:
Money an Employee paid themselves for the travel of one of their Approved Travel Requests, which the company pays back to them, as in "Cara paid 1,990 RON for the Berlin hotel". It has the amount in the company's currency, the date it was paid (any day up to today), a Travel Expense Category, a description and the receipt, which is always required. What the company paid for directly is not a Travel Expense. A Travel Expense is always seen as part of its Travel Request, never listed on its own. Every Employee records their own, and nobody records one for someone else. Like a Work Entry, each Travel Expense is Pending, Approved or Declined on its own: the Employee's Manager approves or declines it (while that Manager has left, the next Manager up who hasn't), and it can be Reopened. The Travel Expenses of an Employee with no Manager are Approved as soon as they record them.
_Avoid_: Expense, expense claim, cost, receipt, reimbursement, per diem

**Travel Expense Category**:
The kind of spending a Travel Expense is, from a fixed list the HR system defines: Transport, Accommodation, Meals or Other. Companies can't add their own.
_Avoid_: Expense type, cost type, category

### Performance

**Performance Evaluation**:
The yearly look at how one Employee did over one calendar year, holding the Goals they were given for it, as in "Cara's 2027 Performance Evaluation". An Employee has at most one per year, and only an Employee with a Manager has one. The Employee's Manager creates it whenever they choose, for the current or the next year (while that Manager has left, the next Manager up who hasn't); there is no company-wide round that creates them all. It belongs to the Employee, not to the Manager, so a new Manager takes over the same one. It is Open from the moment it is created, with no separate start, until the Manager Completes it, with no deadline, and is kept after the Employee leaves.
_Avoid_: Review, performance review, appraisal, assessment, evaluation cycle

**Goal**:
Something an Employee's Manager asks them to achieve during the year of one Performance Evaluation, with a title and a description, as in "Ben gave Cara the Goal of leading the Relaunch API work". Every Goal belongs to exactly one Performance Evaluation and to no Assignment or Project. Only the Employee's Manager gives, changes or removes Goals (while that Manager has left, the next Manager up who hasn't), and only while the Performance Evaluation is Open. No Role can grant it, and no Employee sets their own. The Employee sees their Goals as soon as they are given. Changing a Goal's Progress or writing a Comment on it is not changing the Goal.
_Avoid_: Objective, OKR, KPI, target

**Progress**:
How far along a Goal is during the year, from a fixed list the HR system defines: Not Started, On Track, At Risk or Done, as in "Cara's Relaunch API Goal is At Risk". Companies can't add their own. Every Goal starts Not Started. The Employee and their Manager (while that Manager has left, the next Manager up who hasn't) can both change it while the Performance Evaluation is Open, and every change is kept with who made it and when. Done means the work is finished, not how well it went: that is the Outcome, and a Performance Evaluation can be Completed whatever its Goals' Progress.
_Avoid_: Status, percent complete, check-in

**Comment**:
A short written message on a Goal, part of the running conversation between the Employee and their Manager about it, as in "Ben commented on Cara's Relaunch API Goal: auth is late, let's talk Friday". Only the Employee and their Manager (while that Manager has left, the next Manager up who hasn't) write Comments, and only while the Performance Evaluation is Open. Everyone who can see the Performance Evaluation reads them as soon as they are written. A Comment is never changed or removed on its own; a correction is another Comment. Removing a Goal removes its Comments and its Progress history with it.
_Avoid_: Note, feedback, remark

**Outcome**:
How well a Goal, or a Performance Evaluation as a whole, was achieved, from a fixed list the HR system defines: Not Met, Partly Met, Met or Exceeded. Companies can't add their own. The Manager gives one to every Goal and writes a summary first; giving the Performance Evaluation as a whole its Outcome is what Completes it, so a Goal's Outcome can be given, changed or taken back while it is Open, but the whole's only on Completing. The Employee sees them only once it is Completed.
_Avoid_: Rating, score, grade, result

**Open**:
The state of a Performance Evaluation that has not been Completed yet, or that was Reopened. Its Goals can still be given, changed or removed, their Progress changed and Comments written, and its Outcomes are not yet shown to the Employee.
_Avoid_: Draft, in progress, active

**Completed**:
The state of a Performance Evaluation whose Manager has given every Goal an Outcome, written the summary and then given the whole its Outcome, which closes it. It cannot be changed, not even by its Manager, but someone whose Role grants it can Reopen it, which sends it back to Open with its Outcomes and summary kept; the Manager Completes it again by giving the whole its Outcome again.
_Avoid_: Closed, finalised, signed off, submitted

### Access

**Role**:
A named, customizable set of Permissions, such as "Admin", "Manager" or "Employee". An Employee can hold several Roles. At most one Role is given to every new Employee; giving another Role to new Employees takes that from the first.
_Avoid_: Group, profile, user type

**Admin**:
The one built-in Role that always holds every Permission with the everyone Scope, including Permissions added to the HR system later. It can be renamed, but its Permissions can't be changed, it is never given to every new Employee, and it can't be deleted.
_Avoid_: Superuser, root, owner, administrator

**Permission**:
A single thing an Employee is allowed to do in the HR system, granted through a Role together with a Scope. The HR system defines every Permission; companies choose which ones go into each Role but cannot invent new ones.
_Avoid_: Right, privilege, ability

**Access Log**:
The permanent record of every change to access: a Role being created, edited or deleted, or given to or taken from an Employee, with who made the change and when. It is kept for investigation and is not shown anywhere in the HR system.
_Avoid_: Audit trail, history, activity log, permission log

**Scope**:
Which Employees a Permission about Employees (their records, Assignments, Pay, Work Entries, Time Off, Paid Days, Travel Requests, Travel Expenses or Performance Evaluations) applies to. Permissions about anything else, such as Projects, Clients or Roles, have no Scope and always apply company-wide, and so does adding Employees, as nobody reports to an Employee who isn't there yet. The Scopes are **own** (only the Employee themselves), **reports** (the Employees who report to them) or **everyone**.
_Avoid_: Reach, level, visibility
