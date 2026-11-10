# Pay belongs to the Assignment, and "internal" is a Project

Unlike most HR systems, Pay is not stored on the Employee. Each Assignment carries its own Pay (Fixed Pay per month or Hourly Pay, with history of changes), because in outsourcing companies the same person is paid differently for internal work and for each Client. To make this uniform, every installation has one built-in Internal Project with no Client, and every Employee always has an Assignment to it for their whole period of work; its Job Title is their internal position and its Pay is their internal pay.

## Considered Options

- **Pay on the Employee:** rejected because it can't represent different pay per Client or internal work.
- **A separate "position" field and internal pay outside Projects:** rejected because the Internal Project reuses Project, Assignment, Job Title and Pay with no new concepts, and booking hours works the same everywhere.
