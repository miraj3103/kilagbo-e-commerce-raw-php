-- NOTE 11 - Superadmin Role & Permission Management
-- Run migrations/011_admin_rbac.php for the idempotent installation.
-- Creates: admin_roles, admin_permissions, admin_role_permissions
-- Alters: admins.role_id

-- Courier and Settings are delegable permissions in the NOTE 11 hotfix.
-- Re-run migrations/011_admin_rbac.php after installing the hotfix.
