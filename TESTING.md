# Manual test checklist (Patient profile linking)

Setup: import `schema.sql` (no migration needed), open `http://localhost/MediFlow/`. Demo password for seeded users: `password`.

1. Login `admin` > Users > create user `newpat`, role Patient, Linked Patient = "Tanvir Ahmed (#5)".
2. Log out, login as `newpat` > Patient Dashboard shows "Welcome, Tanvir!" and only Tanvir's records.
3. As admin, edit `newpat`, change Linked Patient to another profile; reload the patient page (no re-login needed) - data changes.
4. Edit `newpat`, set Linked Patient to "-- None --"; patient page shows the "not linked" message, no warnings.
5. Create a second user linking a profile already used - rejected with "already linked to user ...". (Used profiles are also greyed out in the dropdown.)
6. Create user `doc2`, role Doctor, link "Nusrat Jahan"; login, doctor dashboard works.
7. Login `m.karim` (doctor), `admin` - still work.
8. Edit a user with blank password - old password still works.
9. Change a Patient user to Doctor/Admin - patient link is cleared automatically; the patient profile itself is kept.
