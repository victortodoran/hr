# An Employee is their login, and a rehire is a new Employee

There is no separate "User": each Employee is exactly one sign-in account, which works from their Start Date until their Leaving Date and is blocked afterwards, while their records are kept. A rehired person becomes a brand-new Employee with no link to their earlier one, and an email can never be used by two Employees, so a returning person needs a new address. We chose this over linking a person's employment periods under one record because it keeps one Employee equal to one unbroken period of work. Do not "fix" this by reusing the old Employee or their email: that reverses a deliberate decision.

## Consequences

- A rehired person cannot see the records from their earlier period of work; HR can see both Employees.
- Outsiders such as consultants or auditors cannot sign in, since every account is an Employee.
