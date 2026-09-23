<!-- @loom-file release=0.15.02 revision=1 policy=package-priority -->
# First-Run Identity and Administrator Setup

## New browser installation

When no guest profile exists for the browser installation, LOOM blocks entry to Home until the person acknowledges creation of the first guest profile. The person may enter a username or leave it blank. A blank name is resolved by the server into a unique `LOOMUser-*` name. New guest profiles start with `assets/loom-default-avatar.svg`.

Additional people on the same browser create separate guest profiles through **Switch User**. Guest profiles are intentionally soft identities that can later be attached to permanent email/password LOOM accounts.

## Administrator bootstrap

Administrator bootstrap must never occur from a passive status/read call. The first Admin is created only when `api/admin.php` receives an explicit `claim=true` request for the currently selected LOOM client identity.

The guided path is `/admin/setup/`. On an installation with no Admin, `/admin/` redirects there. The page identifies the current LOOM user before claim and requires acknowledgement. If that user is already authenticated to a permanent account, the Admin claim is linked to that account immediately. If the Admin is still a guest, the same page can create the permanent account; normal account registration promotes and links the authorized bootstrap identity.

Once an Admin exists, opening `/admin/setup/` as another identity does not transfer authority. The page instead instructs the person to switch/sign in as the existing Admin.
