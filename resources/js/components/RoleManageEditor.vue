<template>
  <div>
    <div class="alert alert-info py-2 small">
      Role and permission changes affect access immediately. Core roles and code-referenced permissions are locked when they are in use.
    </div>

    <div v-if="loading" class="text-muted small py-4">Loading...</div>

    <template v-else>
      <div class="row g-3 mb-4">
        <div class="col-lg-6">
          <section class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
              <span class="fw-semibold">Role list</span>
              <button class="btn btn-sm btn-outline-primary" @click="resetRoleForm">New</button>
            </div>
            <div class="card-body">
              <div class="row g-2 align-items-start mb-3">
                <div class="col">
                  <input
                    v-model="roleForm.name"
                    type="text"
                    class="form-control form-control-sm"
                    :class="{ 'is-invalid': errors.role.name }"
                    placeholder="role_name"
                  >
                  <div class="invalid-feedback">{{ errors.role.name }}</div>
                </div>
                <div class="col-auto">
                  <button class="btn btn-sm btn-primary" :disabled="saving" @click="saveRole">
                    {{ roleForm.id ? 'Update' : 'Add' }}
                  </button>
                </div>
              </div>

              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Name</th>
                      <th>Guard</th>
                      <th class="text-end">Users</th>
                      <th class="text-end">Perms</th>
                      <th style="width:120px"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="role in roles" :key="role.id">
                      <td>
                        <span class="fw-semibold">{{ role.name }}</span>
                        <span v-if="role.is_core" class="badge text-bg-secondary ms-1">core</span>
                      </td>
                      <td class="text-muted small">{{ role.guard_name }}</td>
                      <td class="text-end">{{ role.users_count }}</td>
                      <td class="text-end">{{ role.permissions_count }}</td>
                      <td class="text-end">
                        <button
                          class="btn btn-xs btn-outline-secondary me-1"
                          :disabled="!role.can_update"
                          :title="lockTitle(role, 'role')"
                          @click="editRole(role)"
                        >Edit</button>
                        <button
                          class="btn btn-xs btn-outline-danger"
                          :disabled="!role.can_delete"
                          :title="lockTitle(role, 'role')"
                          @click="deleteRole(role)"
                        >Del</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
        </div>

        <div class="col-lg-6">
          <section class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
              <span class="fw-semibold">Permission list</span>
              <button class="btn btn-sm btn-outline-primary" @click="resetPermissionForm">New</button>
            </div>
            <div class="card-body">
              <div class="row g-2 align-items-start mb-3">
                <div class="col">
                  <input
                    v-model="permissionForm.name"
                    type="text"
                    class="form-control form-control-sm"
                    :class="{ 'is-invalid': errors.permission.name }"
                    placeholder="permission.name"
                  >
                  <div class="invalid-feedback">{{ errors.permission.name }}</div>
                </div>
                <div class="col-auto">
                  <button class="btn btn-sm btn-primary" :disabled="saving" @click="savePermission">
                    {{ permissionForm.id ? 'Update' : 'Add' }}
                  </button>
                </div>
              </div>

              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Name</th>
                      <th>Guard</th>
                      <th class="text-end">Roles</th>
                      <th class="text-end">Users</th>
                      <th style="width:120px"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="permission in permissions" :key="permission.id">
                      <td>
                        <span class="fw-semibold">{{ permission.name }}</span>
                        <span v-if="permission.is_code_permission" class="badge text-bg-secondary ms-1">code</span>
                      </td>
                      <td class="text-muted small">{{ permission.guard_name }}</td>
                      <td class="text-end">{{ permission.roles_count }}</td>
                      <td class="text-end">{{ permission.users_count }}</td>
                      <td class="text-end">
                        <button
                          class="btn btn-xs btn-outline-secondary me-1"
                          :disabled="!permission.can_update"
                          :title="lockTitle(permission, 'permission')"
                          @click="editPermission(permission)"
                        >Edit</button>
                        <button
                          class="btn btn-xs btn-outline-danger"
                          :disabled="!permission.can_delete"
                          :title="lockTitle(permission, 'permission')"
                          @click="deletePermission(permission)"
                        >Del</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
        </div>
      </div>

      <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#rolePermTab" type="button">Role has permission</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#modelRoleTab" type="button">Model has role</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#modelPermTab" type="button">Model has permission</button>
        </li>
      </ul>

      <div class="tab-content">
        <div id="rolePermTab" class="tab-pane fade show active">
          <section class="card">
            <div class="card-header py-2 fw-semibold">Role has permission</div>
            <div class="card-body">
              <div class="row g-2 align-items-start mb-3">
                <div class="col-md-5">
                  <select v-model="rolePermissionForm.role_id" class="form-select form-select-sm" :disabled="rolePermissionForm.editing">
                    <option value="">Select role</option>
                    <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                  </select>
                </div>
                <div class="col-md-5">
                  <select v-model="rolePermissionForm.permission_id" class="form-select form-select-sm">
                    <option value="">Select permission</option>
                    <option v-for="permission in permissions" :key="permission.id" :value="permission.id">{{ permission.name }}</option>
                  </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                  <button class="btn btn-sm btn-primary w-100" :disabled="saving" @click="saveRolePermission">
                    {{ rolePermissionForm.editing ? 'Update' : 'Add' }}
                  </button>
                  <button v-if="rolePermissionForm.editing" class="btn btn-sm btn-outline-secondary" @click="resetRolePermissionForm">Cancel</button>
                </div>
              </div>

              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Role</th>
                      <th>Permission</th>
                      <th style="width:120px"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in rolePermissions" :key="`${row.role_id}-${row.permission_id}`">
                      <td>{{ row.role_name }}</td>
                      <td>{{ row.permission_name }}</td>
                      <td class="text-end">
                        <button class="btn btn-xs btn-outline-secondary me-1" @click="editRolePermission(row)">Edit</button>
                        <button class="btn btn-xs btn-outline-danger" @click="deleteRolePermission(row)">Del</button>
                      </td>
                    </tr>
                    <tr v-if="rolePermissions.length === 0">
                      <td colspan="3" class="text-muted small">No role permission relations.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
        </div>

        <div id="modelRoleTab" class="tab-pane fade">
          <section class="card">
            <div class="card-header py-2 fw-semibold">Model has role</div>
            <div class="card-body">
              <div class="row g-2 align-items-start mb-3">
                <div class="col-md-5">
                  <input
                    v-model="modelRoleSearch.user"
                    list="user-search-datalist"
                    class="form-control form-control-sm"
                    placeholder="User search: Last / First / employee_code"
                    autocomplete="off"
                    aria-label="User search"
                    @keyup.enter="searchModelRoles"
                  >
                </div>
                <div class="col-md-5">
                  <input
                    v-model="modelRoleSearch.role"
                    list="role-search-datalist"
                    class="form-control form-control-sm"
                    placeholder="Role search"
                    autocomplete="off"
                    aria-label="Role search"
                    @keyup.enter="searchModelRoles"
                  >
                </div>
                <div class="col-md-2">
                  <div class="btn-group w-100 rm-search-actions" role="group" aria-label="Model role search actions">
                    <button class="btn btn-sm btn-primary" @click="searchModelRoles">Search</button>
                    <button class="btn btn-sm btn-outline-secondary" @click="clearModelRoleSearch">Clear</button>
                  </div>
                </div>
              </div>

              <datalist id="role-search-datalist">
                <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.guard_name }}</option>
              </datalist>
              <datalist id="user-search-datalist">
                <option
                  v-for="user in users"
                  :key="user.id"
                  :value="userSearchValue(user)"
                >{{ user.email }}</option>
              </datalist>

              <div class="row g-2 align-items-start mb-3">
                <div class="col-md-5">
                  <select v-model="modelRoleForm.user_id" class="form-select form-select-sm" :disabled="modelRoleForm.editing">
                    <option value="">Select user</option>
                    <option v-for="user in users" :key="user.id" :value="user.id">{{ userLabel(user) }}</option>
                  </select>
                </div>
                <div class="col-md-5">
                  <select v-model="modelRoleForm.role_id" class="form-select form-select-sm">
                    <option value="">Select role</option>
                    <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                  </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                  <button class="btn btn-sm btn-primary w-100" :disabled="saving" @click="saveModelRole">
                    {{ modelRoleForm.editing ? 'Update' : 'Add' }}
                  </button>
                  <button v-if="modelRoleForm.editing" class="btn btn-sm btn-outline-secondary" @click="resetModelRoleForm">Cancel</button>
                </div>
              </div>

              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>User</th>
                      <th>Email</th>
                      <th>Role</th>
                      <th style="width:120px"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in filteredModelRoles" :key="`${row.user_id}-${row.role_id}`">
                      <td>{{ row.employee_code }} - {{ row.user_name }}</td>
                      <td class="text-muted small">{{ row.email }}</td>
                      <td>{{ row.role_name }}</td>
                      <td class="text-end">
                        <button class="btn btn-xs btn-outline-secondary me-1" @click="editModelRole(row)">Edit</button>
                        <button class="btn btn-xs btn-outline-danger" @click="deleteModelRole(row)">Del</button>
                      </td>
                    </tr>
                    <tr v-if="!modelRoleSearch.searched">
                      <td colspan="4" class="text-muted small">Search by role or user to show user role relations.</td>
                    </tr>
                    <tr v-else-if="filteredModelRoles.length === 0">
                      <td colspan="4" class="text-muted small">No user role relations match the search.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
        </div>

        <div id="modelPermTab" class="tab-pane fade">
          <section class="card">
            <div class="card-header py-2 fw-semibold">Model has permission</div>
            <div class="card-body">
              <div class="row g-2 align-items-start mb-3">
                <div class="col-md-5">
                  <select v-model="modelPermissionForm.user_id" class="form-select form-select-sm" :disabled="modelPermissionForm.editing">
                    <option value="">Select user</option>
                    <option v-for="user in users" :key="user.id" :value="user.id">{{ userLabel(user) }}</option>
                  </select>
                </div>
                <div class="col-md-5">
                  <select v-model="modelPermissionForm.permission_id" class="form-select form-select-sm">
                    <option value="">Select permission</option>
                    <option v-for="permission in permissions" :key="permission.id" :value="permission.id">{{ permission.name }}</option>
                  </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                  <button class="btn btn-sm btn-primary w-100" :disabled="saving" @click="saveModelPermission">
                    {{ modelPermissionForm.editing ? 'Update' : 'Add' }}
                  </button>
                  <button v-if="modelPermissionForm.editing" class="btn btn-sm btn-outline-secondary" @click="resetModelPermissionForm">Cancel</button>
                </div>
              </div>

              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>User</th>
                      <th>Email</th>
                      <th>Direct permission</th>
                      <th style="width:120px"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in modelPermissions" :key="`${row.user_id}-${row.permission_id}`">
                      <td>{{ row.employee_code }} - {{ row.user_name }}</td>
                      <td class="text-muted small">{{ row.email }}</td>
                      <td>{{ row.permission_name }}</td>
                      <td class="text-end">
                        <button class="btn btn-xs btn-outline-secondary me-1" @click="editModelPermission(row)">Edit</button>
                        <button class="btn btn-xs btn-outline-danger" @click="deleteModelPermission(row)">Del</button>
                      </td>
                    </tr>
                    <tr v-if="modelPermissions.length === 0">
                      <td colspan="4" class="text-muted small">No direct user permission relations.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
        </div>
      </div>
    </template>

    <div class="modal fade" tabindex="-1" ref="confirmModalEl">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header py-2">
            <h6 class="modal-title mb-0">{{ confirm.title }}</h6>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-danger py-2 small mb-3">
              Confirm this change only after checking the target and relation names.
            </div>
            <ul class="mb-0 small">
              <li v-for="line in confirm.lines" :key="line">{{ line }}</li>
            </ul>
          </div>
          <div class="modal-footer py-2">
            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-sm btn-danger" :disabled="confirm.saving" @click="runConfirmedAction">
              {{ confirm.saving ? 'Processing...' : 'Confirm' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken();

export default {
  name: 'RoleManageEditor',

  props: {
    snapshotUrl: { type: String, required: true },
    roleUrl: { type: String, required: true },
    permissionUrl: { type: String, required: true },
    rolePermissionUrl: { type: String, required: true },
    modelRoleUrl: { type: String, required: true },
    modelPermissionUrl: { type: String, required: true },
    userUrl: { type: String, required: true },
  },

  data() {
    return {
      loading: true,
      saving: false,
      roles: [],
      permissions: [],
      users: [],
      rolePermissions: [],
      modelRoles: [],
      modelPermissions: [],
      roleForm: { id: null, name: '', original_name: '' },
      permissionForm: { id: null, name: '', original_name: '' },
      rolePermissionForm: { editing: false, role_id: '', permission_id: '', original_permission_id: '' },
      modelRoleSearch: { role: '', user: '', searched: false },
      modelRoleForm: { editing: false, user_id: '', role_id: '', original_role_id: '' },
      modelPermissionForm: { editing: false, user_id: '', permission_id: '', original_permission_id: '' },
      errors: {
        role: {},
        permission: {},
      },
      confirmModal: null,
      confirm: {
        title: '',
        lines: [],
        action: null,
        saving: false,
      },
    };
  },

  mounted() {
    this.confirmModal = new window.bootstrap.Modal(this.$refs.confirmModalEl);
    this.loadSnapshot();
  },

  computed: {
    filteredModelRoles() {
      if (!this.modelRoleSearch.searched) return [];

      const roleQuery = this.normalizeSearch(this.modelRoleSearch.role);
      const userQuery = this.normalizeSearch(this.modelRoleSearch.user);

      return this.modelRoles.filter((row) => {
        const roleMatches = !roleQuery || this.normalizeSearch(row.role_name).includes(roleQuery);
        const userHaystack = this.normalizeSearch([
          row.employee_code,
          row.family_name,
          row.first_name,
          row.user_name,
          row.email,
        ].filter(Boolean).join(' '));
        const userMatches = !userQuery || userHaystack.includes(userQuery);

        return roleMatches && userMatches;
      });
    },
  },

  methods: {
    async loadSnapshot() {
      this.loading = true;
      try {
        const { data } = await axios.get(this.snapshotUrl);
        this.roles = data.roles;
        this.permissions = data.permissions;
        this.users = data.users;
        this.rolePermissions = data.role_permissions;
        this.modelRoles = data.model_roles;
        this.modelPermissions = data.model_permissions;
      } catch (err) {
        this.showFlash(err.response?.data?.message ?? 'Failed to load role management data.', false);
      } finally {
        this.loading = false;
      }
    },

    showFlash(text, ok = true) {
      if (typeof window.showToast === 'function') {
        window.showToast(text, { variant: ok ? 'success' : 'danger', delay: 9000 });
      }
    },

    lockTitle(row, type) {
      if (type === 'role') {
        if (row.is_core) return 'Core roles cannot be renamed or deleted.';
        if (row.users_count > 0) return 'This role is assigned to users.';
        if (row.permissions_count > 0) return 'This role has permissions.';
      }

      if (row.is_code_permission) return 'This permission is referenced by application code.';
      if (row.roles_count > 0) return 'This permission is assigned to roles.';
      if (row.users_count > 0) return 'This permission is assigned directly to users.';
      return '';
    },

    userLabel(user) {
      return `${user.employee_code} - ${user.name}`;
    },

    userSearchValue(user) {
      return `${user.employee_code} ${user.family_name ?? ''} ${user.first_name ?? ''}`.trim();
    },

    normalizeSearch(value) {
      return String(value ?? '').trim().toLowerCase();
    },

    searchModelRoles() {
      if (!this.normalizeSearch(this.modelRoleSearch.role) && !this.normalizeSearch(this.modelRoleSearch.user)) {
        this.showFlash('Enter a role or user search term.', false);
        return;
      }

      this.modelRoleSearch.searched = true;
    },

    clearModelRoleSearch() {
      this.modelRoleSearch = { role: '', user: '', searched: false };
    },

    roleName(id) {
      return this.roles.find((role) => Number(role.id) === Number(id))?.name ?? id;
    },

    permissionName(id) {
      return this.permissions.find((permission) => Number(permission.id) === Number(id))?.name ?? id;
    },

    userName(id) {
      const user = this.users.find((item) => Number(item.id) === Number(id));
      return user ? this.userLabel(user) : id;
    },

    roleItemUrl(id) {
      return `${this.roleUrl}/${id}`;
    },

    permissionItemUrl(id) {
      return `${this.permissionUrl}/${id}`;
    },

    rolePermissionItemUrl(roleId, permissionId) {
      return `${this.roleUrl}/${roleId}/permissions/${permissionId}`;
    },

    modelRoleItemUrl(userId, roleId) {
      return `${this.userUrl}/${userId}/roles/${roleId}`;
    },

    modelPermissionItemUrl(userId, permissionId) {
      return `${this.userUrl}/${userId}/permissions/${permissionId}`;
    },

    ask(title, lines, action) {
      this.confirm = { title, lines, action, saving: false };
      this.confirmModal.show();
    },

    async runConfirmedAction() {
      if (!this.confirm.action) return;

      this.confirm.saving = true;
      this.saving = true;
      try {
        await this.confirm.action();
        this.confirmModal.hide();
      } catch (err) {
        this.handleError(err);
      } finally {
        this.confirm.saving = false;
        this.saving = false;
      }
    },

    handleError(err, bucket = null) {
      const validation = err.response?.data?.errors ?? null;
      if (validation && bucket) {
        this.errors[bucket] = Object.fromEntries(
          Object.entries(validation).map(([key, messages]) => [key, messages[0]])
        );
      }
      this.showFlash(err.response?.data?.message ?? 'The change failed.', false);
    },

    resetRoleForm() {
      this.roleForm = { id: null, name: '', original_name: '' };
      this.errors.role = {};
    },

    editRole(role) {
      this.roleForm = { id: role.id, name: role.name, original_name: role.name };
      this.errors.role = {};
    },

    saveRole() {
      this.errors.role = {};
      const name = this.roleForm.name.trim();
      const isEdit = Boolean(this.roleForm.id);
      const lines = isEdit
        ? [`Role: ${this.roleForm.original_name}`, `New name: ${name}`]
        : [`New role: ${name}`, 'Guard: web'];

      this.ask(isEdit ? 'Update role' : 'Create role', lines, async () => {
        try {
          if (isEdit) {
            await axios.put(this.roleItemUrl(this.roleForm.id), { name, confirm: true });
          } else {
            await axios.post(this.roleUrl, { name, confirm: true });
          }
          this.resetRoleForm();
          await this.loadSnapshot();
          this.showFlash(isEdit ? 'Role updated.' : 'Role added.');
        } catch (err) {
          this.handleError(err, 'role');
          throw err;
        }
      });
    },

    deleteRole(role) {
      this.ask('Delete role', [`Role: ${role.name}`, 'This is allowed only when the role is unused.'], async () => {
        await axios.delete(this.roleItemUrl(role.id), { data: { confirm: true } });
        await this.loadSnapshot();
        this.showFlash('Role deleted.');
      });
    },

    resetPermissionForm() {
      this.permissionForm = { id: null, name: '', original_name: '' };
      this.errors.permission = {};
    },

    editPermission(permission) {
      this.permissionForm = { id: permission.id, name: permission.name, original_name: permission.name };
      this.errors.permission = {};
    },

    savePermission() {
      this.errors.permission = {};
      const name = this.permissionForm.name.trim();
      const isEdit = Boolean(this.permissionForm.id);
      const lines = isEdit
        ? [`Permission: ${this.permissionForm.original_name}`, `New name: ${name}`]
        : [`New permission: ${name}`, 'Guard: web'];

      this.ask(isEdit ? 'Update permission' : 'Create permission', lines, async () => {
        try {
          if (isEdit) {
            await axios.put(this.permissionItemUrl(this.permissionForm.id), { name, confirm: true });
          } else {
            await axios.post(this.permissionUrl, { name, confirm: true });
          }
          this.resetPermissionForm();
          await this.loadSnapshot();
          this.showFlash(isEdit ? 'Permission updated.' : 'Permission added.');
        } catch (err) {
          this.handleError(err, 'permission');
          throw err;
        }
      });
    },

    deletePermission(permission) {
      this.ask('Delete permission', [`Permission: ${permission.name}`, 'This is allowed only when the permission is unused.'], async () => {
        await axios.delete(this.permissionItemUrl(permission.id), { data: { confirm: true } });
        await this.loadSnapshot();
        this.showFlash('Permission deleted.');
      });
    },

    resetRolePermissionForm() {
      this.rolePermissionForm = { editing: false, role_id: '', permission_id: '', original_permission_id: '' };
    },

    editRolePermission(row) {
      this.rolePermissionForm = {
        editing: true,
        role_id: row.role_id,
        permission_id: row.permission_id,
        original_permission_id: row.permission_id,
      };
    },

    saveRolePermission() {
      const form = this.rolePermissionForm;
      const isEdit = form.editing;
      const lines = isEdit
        ? [`Role: ${this.roleName(form.role_id)}`, `From: ${this.permissionName(form.original_permission_id)}`, `To: ${this.permissionName(form.permission_id)}`]
        : [`Role: ${this.roleName(form.role_id)}`, `Permission: ${this.permissionName(form.permission_id)}`];

      this.ask(isEdit ? 'Update role permission' : 'Add role permission', lines, async () => {
        if (isEdit) {
          await axios.put(this.rolePermissionItemUrl(form.role_id, form.original_permission_id), {
            permission_id: form.permission_id,
            confirm: true,
          });
        } else {
          await axios.post(this.rolePermissionUrl, {
            role_id: form.role_id,
            permission_id: form.permission_id,
            confirm: true,
          });
        }
        this.resetRolePermissionForm();
        await this.loadSnapshot();
        this.showFlash(isEdit ? 'Role permission updated.' : 'Role permission added.');
      });
    },

    deleteRolePermission(row) {
      this.ask('Delete role permission', [`Role: ${row.role_name}`, `Permission: ${row.permission_name}`], async () => {
        await axios.delete(this.rolePermissionItemUrl(row.role_id, row.permission_id), { data: { confirm: true } });
        await this.loadSnapshot();
        this.showFlash('Role permission deleted.');
      });
    },

    resetModelRoleForm() {
      this.modelRoleForm = { editing: false, user_id: '', role_id: '', original_role_id: '' };
    },

    editModelRole(row) {
      this.modelRoleForm = {
        editing: true,
        user_id: row.user_id,
        role_id: row.role_id,
        original_role_id: row.role_id,
      };
    },

    saveModelRole() {
      const form = this.modelRoleForm;
      const isEdit = form.editing;
      const lines = isEdit
        ? [`User: ${this.userName(form.user_id)}`, `From: ${this.roleName(form.original_role_id)}`, `To: ${this.roleName(form.role_id)}`]
        : [`User: ${this.userName(form.user_id)}`, `Role: ${this.roleName(form.role_id)}`];

      this.ask(isEdit ? 'Update user role' : 'Add user role', lines, async () => {
        if (isEdit) {
          await axios.put(this.modelRoleItemUrl(form.user_id, form.original_role_id), {
            role_id: form.role_id,
            confirm: true,
          });
        } else {
          await axios.post(this.modelRoleUrl, {
            user_id: form.user_id,
            role_id: form.role_id,
            confirm: true,
          });
        }
        this.resetModelRoleForm();
        await this.loadSnapshot();
        this.showFlash(isEdit ? 'User role updated.' : 'User role added.');
      });
    },

    deleteModelRole(row) {
      this.ask('Delete user role', [`User: ${row.employee_code} - ${row.user_name}`, `Role: ${row.role_name}`], async () => {
        await axios.delete(this.modelRoleItemUrl(row.user_id, row.role_id), { data: { confirm: true } });
        await this.loadSnapshot();
        this.showFlash('User role deleted.');
      });
    },

    resetModelPermissionForm() {
      this.modelPermissionForm = { editing: false, user_id: '', permission_id: '', original_permission_id: '' };
    },

    editModelPermission(row) {
      this.modelPermissionForm = {
        editing: true,
        user_id: row.user_id,
        permission_id: row.permission_id,
        original_permission_id: row.permission_id,
      };
    },

    saveModelPermission() {
      const form = this.modelPermissionForm;
      const isEdit = form.editing;
      const lines = isEdit
        ? [`User: ${this.userName(form.user_id)}`, `From: ${this.permissionName(form.original_permission_id)}`, `To: ${this.permissionName(form.permission_id)}`]
        : [`User: ${this.userName(form.user_id)}`, `Permission: ${this.permissionName(form.permission_id)}`];

      this.ask(isEdit ? 'Update user direct permission' : 'Add user direct permission', lines, async () => {
        if (isEdit) {
          await axios.put(this.modelPermissionItemUrl(form.user_id, form.original_permission_id), {
            permission_id: form.permission_id,
            confirm: true,
          });
        } else {
          await axios.post(this.modelPermissionUrl, {
            user_id: form.user_id,
            permission_id: form.permission_id,
            confirm: true,
          });
        }
        this.resetModelPermissionForm();
        await this.loadSnapshot();
        this.showFlash(isEdit ? 'User direct permission updated.' : 'User direct permission added.');
      });
    },

    deleteModelPermission(row) {
      this.ask('Delete user direct permission', [`User: ${row.employee_code} - ${row.user_name}`, `Permission: ${row.permission_name}`], async () => {
        await axios.delete(this.modelPermissionItemUrl(row.user_id, row.permission_id), { data: { confirm: true } });
        await this.loadSnapshot();
        this.showFlash('User direct permission deleted.');
      });
    },
  },
};
</script>

<style scoped>
.btn-xs {
  padding: 0.1rem 0.45rem;
  font-size: 0.75rem;
  border-radius: 0.2rem;
}

.table td,
.table th {
  white-space: nowrap;
}

.rm-search-actions .btn {
  min-width: 0;
  padding-left: 0.35rem;
  padding-right: 0.35rem;
}
</style>
