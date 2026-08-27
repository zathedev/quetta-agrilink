# Local Operator Transition

The repository retains five documented development credentials only for an isolated local demonstration. Before a controlled or public operating period, use the protected **Workspace → Local operator transition** register to create accountable local operators and archive the development accounts one by one.

## Safe transition sequence

| Step | Administrator action | Required outcome |
|---|---|---|
| 1 | Import `database/migrations/20260827_add_operator_account_transitions.sql`. | The protected transition history table exists. |
| 2 | Sign in as the current active administrator and create a named operator account with the appropriate role. | A role-scoped active account is created and recorded without its password. |
| 3 | For administrator replacement, create and verify a separate named administrator first. | At least one active named administrator exists before the development administrator is archived. |
| 4 | Have the account owner sign in and complete their profile through the approved local process. | The owner confirms access and the correct workspace. |
| 5 | Return to **Local operator transition** and archive the matching documented development account. | The account is retained for accountability but cannot sign in again. |
| 6 | Review protected transition history and the ordinary audit register. | The administrator, action, timestamp, and account context are visible; passwords and recovery secrets are absent. |

## Security boundary

The transition page creates a temporary password only to establish the new user account. It never shows, exports, logs, or records that password, its hash, a reset link, selector, token, or recovery secret. Deliver the temporary password only through the organisation’s approved verified local process, then have the owner change it through normal account recovery or the agreed account-management procedure.

The archive control accepts only the five documented `*.demo@quettaagrilink.test` development accounts. It cannot archive an arbitrary local operator, cannot archive the administrator who is currently signed in, and refuses to archive the final active administrator. The release checklist remains the final authority for public exposure.
