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
The first day an Employee works for the company. From this day on, they can sign in.
_Avoid_: Hire date, joining date, first day

**Former Employee**:
An Employee who has left the company. Their records are kept, but they can no longer sign in.
_Avoid_: Leaver, ex-employee, deleted user, inactive user

**Leaving Date**:
The last day an Employee works for the company. From the next day on, they are a Former Employee.
_Avoid_: End date, termination date, exit date

### Reporting line

**Manager**:
The one Employee that another Employee reports to directly. Every Employee has exactly one Manager, except the single Employee at the top of the company (for example the CEO), who has none.
_Avoid_: Boss, supervisor, line manager, team lead

**Report**:
An Employee anywhere below another Employee in the reporting line, whether directly or through other Managers.
_Avoid_: Subordinate, team member, direct

**Reporting Chain**:
An Employee's Manager, that Manager's Manager, and so on up to the single Employee at the top of the company.
_Avoid_: Hierarchy, org path, chain of command, management line

### Organisation

**Department**:
A part of the company that an Employee belongs to, such as Operations or Finance. Each Employee belongs to exactly one Department, which is separate from their reporting line.
_Avoid_: Team, division, unit, group

**Project**:
A piece of work that Employees can be assigned to, running from a start date to an optional end date. A Project may belong to a Client, or to no Client if it is internal.
_Avoid_: Engagement, contract, account, job

**Internal Project**:
The one built-in Project, with no Client, that represents work for the company itself. Every Employee always has an Assignment to it for their whole period of work, and the Job Title on that Assignment is their internal position. Its share of time can be planned like any other Assignment; if it isn't, it is whatever time other Assignments leave over.
_Avoid_: Internal, bench, overhead, company project

**Client**:
An outside organisation the company does work for. Companies that do not work for outside organisations have no Clients. An Employee works for a Client only through an Assignment to one of its Projects.
_Avoid_: Customer, account, partner

**Assignment**:
An Employee working on a Project for a period of time, with a Job Title, Pay, a start date, an optional end date and a share of the Employee's time. An Employee can have Assignments to several Projects at once, and so can work for several Clients at once.
_Avoid_: Allocation, staffing, placement, membership

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

**Over-booked**:
Describes an Employee whose Assignments add up to more than 100% of their time on some day. It is allowed, but flagged so someone can fix it.
_Avoid_: Over-allocated, overloaded, overcommitted

### Access

**Role**:
A named, customizable set of Permissions, such as "Admin", "Manager" or "Employee". An Employee can hold several Roles.
_Avoid_: Group, profile, user type

**Permission**:
A single thing an Employee is allowed to do in the HR system, granted through a Role together with a Scope. The HR system defines every Permission; companies choose which ones go into each Role but cannot invent new ones.
_Avoid_: Right, privilege, ability

**Access Log**:
The permanent record of every change to access: a Role being created, edited or deleted, or given to or taken from an Employee, with who made the change and when. It is kept for investigation and is not shown anywhere in the HR system.
_Avoid_: Audit trail, history, activity log, permission log

**Scope**:
Which Employees a Permission about Employees (their records, Assignments or Pay) applies to. Permissions about anything else, such as Projects, Clients or Roles, have no Scope and always apply company-wide. The Scopes are **own** (only the Employee themselves), **reports** (the Employees who report to them) or **everyone**.
_Avoid_: Reach, level, visibility
