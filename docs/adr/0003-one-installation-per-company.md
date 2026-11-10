# One installation per company

The HR system is generic enough for any company to use, but each company runs its own installation; it is not a shared multi-company (SaaS) service. This keeps the data model free of a "company" on every record. Moving to multi-company later would touch every table, so revisit this decision before building anything that assumes it, rather than after.
