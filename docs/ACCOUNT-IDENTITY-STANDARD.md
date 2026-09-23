<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# LOOM Account & Project Identity Standard — v0.11.20

LOOM separates account authentication from public project identity.

A permanent **LOOM account** owns: User ID, email, password hash, privilege, linked Client IDs, network metadata, and project identities.

A **project identity** owns: project-scoped username, project-scoped profile picture mode/file metadata, creation/update timestamps, and its stable Project Identity ID.

The same User ID may therefore be `MichaelYebba` in Green Beans and a completely different username/avatar in another project. Project usernames are unique only inside that project.

Email/password remain account-level so one sign-in recovers all project identities. The legacy `loom_users.username` column remains for compatibility/internal account handles and is not the public project username from v0.11.20 onward.

Existing usernames/profile pictures are lazily migrated into the first project identity that uses them, preserving current users without forcing that same persona into future projects.

## v0.11.20 global/profile split
A permanent LOOM account has a LOOM-wide profile (username + profile picture). Each project identity independently chooses to inherit each global value or use a project-specific override. Account email/password/User ID/privilege remain global.

## Guest History attachment (v0.12.00+)

A permanent account may have multiple attached Guest Histories originating from different browser/install clients. Signing into an account from a client with existing unattached work attaches that Guest History to the authenticated user instead of replacing it.

Attachment is provenance-preserving: project module source rows remain retained, a pre-attachment Guest snapshot is created, uniqueness-constrained live identity/profile structures are promoted only after snapshotting, and an attachment/audit record is written.

A permanent user is therefore an authenticated ownership layer over one or more preserved Guest Histories, not a replacement for those histories.
