# lambdatt-php/iam

A SplitPHP Framework plugin for an Identity and Access Management system.

---

## Installation

Install via Composer:

```bash
composer require lambdatt-php/iam
```

Run the Migrations:
```bash
php console migrations:apply --module=iam
```

**PS: this can only be installed on a SplitPHP Framework project. For more information refer to: https://github.com/splitphp/core**

## Usage:

### Setup command

The `iam:setup` command seeds permissions, teams, access profiles and users from a
JSON settings file. Point the `IAM_SETTINGS_FILEPATH` environment variable at the file
(resolved relative to the project `ROOT_PATH`), then run:

```bash
php console iam:setup
```

The command is **idempotent** — it can be re-run safely. See
[`iam.settings.example.json`](./iam.settings.example.json) for a complete example.

#### Permission domain

The permission model is **flat**: a permission is just a unique `tag` with a human-readable
`title` (table `IAM_PERMISSION`). Access profiles are associated with permissions by tag
(table `IAM_ACCESSPROFILE_PERMISSION`), users are associated with access profiles
(table `IAM_ACCESSPROFILE_USER`), and authorization is checked with
`iam/permission->canExecute('<tag>')`. There are no modules, entities or CRUD flags.

#### Settings file schema

```jsonc
{
  // 1. Permission catalog — upserted into IAM_PERMISSION, keyed by "tag".
  "permissions": [
    { "tag": "orders.manage", "title": "Manage Orders" }
  ],

  // 2. Teams — upserted into IAM_USERTEAM, keyed by "tag".
  "teams": [
    { "tag": "sales", "name": "Sales", "description": "Sales team." }
  ],

  // 3. Access profiles — upserted by "tag" (then "name").
  "accessprofiles": [
    {
      "tag": "manager",
      "name": "Manager",
      "description": "...",
      // Desired-state list of permission tags, or "*" for every known permission.
      "permissions": ["orders.manage"]
    }
  ],

  // 4. Users — upserted by "username" (then "email").
  "users": [
    {
      "username": "root",
      "email": "root@example.com",
      "first_name": "Root",
      "last_name": "Admin",
      // "generate(<algo>, uniqid)" hashes a random uniqid; any other string is the raw password.
      "password": "generate(sha256, uniqid)",
      "hidden": "N",
      "multi_session": "Y",
      "session_timeout": 3600,
      "accessprofile_tags": ["manager"],
      "team_tags": ["sales"]
    }
  ]
}
```

| Key | Matched/keyed by | Notes |
|-----|------------------|-------|
| `permissions[]` | `tag` | `title` defaults to `tag`; title is kept in sync on re-run. |
| `teams[]` | `tag` | `name`/`description` updated on re-run. |
| `accessprofiles[]` | `tag`, then `name` | `permissions` is a **declarative desired-state**. |
| `users[]` | `username`, then `email` | Profiles/teams linked by their `tag`. Existing users keep their data (only `multi_session` is re-synced). |

> **Declarative permission sync:** when an access profile declares `permissions`, the setup
> makes its associations match that list *exactly* — permissions not listed are **detached**,
> including any attached out-of-band via the API/UI. Omit the `permissions` key to leave a
> profile's existing associations untouched. `"*"` resolves to every permission present in
> `IAM_PERMISSION` at that point in the run (which is why the catalog is processed first).