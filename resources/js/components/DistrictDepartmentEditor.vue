<template>
  <div>
    <!-- Flash -->
    <div v-if="flash.text" :class="['alert','py-2','px-3','mb-3', flash.ok ? 'alert-success' : 'alert-danger']">
      {{ flash.text }}
    </div>

    <div class="row g-4">
      <!-- ── Districts ─────────────────────────────────────────── -->
      <div class="col-lg-7">
        <div class="card h-100">
          <div class="card-header d-flex align-items-center justify-content-between py-2">
            <span class="fw-semibold">Districts</span>
            <button class="btn btn-sm btn-primary" @click="openDistrictModal(null)">+ Add</button>
          </div>
          <div class="card-body p-0">
            <div v-if="districtLoading" class="p-3 text-muted small">Loading…</div>
            <div v-else-if="districts.length === 0" class="p-3 text-muted small">No districts yet.</div>
            <table v-else class="table table-sm table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>Name</th>
                  <th>Sub-districts</th>
                  <th style="width:100px"></th>
                </tr>
              </thead>
              <tbody>
                <template v-for="d in districts" :key="d.id">
                  <!-- Parent row -->
                  <tr>
                    <td class="fw-semibold">{{ d.name }}</td>
                    <td class="text-muted small">
                      <span v-for="(c, i) in d.children" :key="c.id">
                        {{ c.name }}<span v-if="i < d.children.length - 1">、</span>
                      </span>
                      <span v-if="d.children.length === 0">—</span>
                    </td>
                    <td class="text-end">
                      <button class="btn btn-xs btn-outline-secondary me-1" @click="openDistrictModal(d)">Edit</button>
                      <button class="btn btn-xs btn-outline-danger" @click="deleteDistrict(d)">Del</button>
                    </td>
                  </tr>
                  <!-- Child rows -->
                  <tr v-for="c in d.children" :key="'c'+c.id" class="table-secondary">
                    <td class="ps-4 text-muted small">↳ {{ c.name }}</td>
                    <td></td>
                    <td class="text-end">
                      <button class="btn btn-xs btn-outline-secondary me-1" @click="openDistrictModal(c)">Edit</button>
                      <button class="btn btn-xs btn-outline-danger" @click="deleteDistrict(c)">Del</button>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ── Departments ───────────────────────────────────────── -->
      <div class="col-lg-5">
        <div class="card h-100">
          <div class="card-header d-flex align-items-center justify-content-between py-2">
            <span class="fw-semibold">Departments</span>
            <button class="btn btn-sm btn-primary" @click="openDeptModal(null)">+ Add</button>
          </div>
          <div class="card-body p-0">
            <div v-if="deptLoading" class="p-3 text-muted small">Loading…</div>
            <div v-else-if="departments.length === 0" class="p-3 text-muted small">No departments yet.</div>
            <table v-else class="table table-sm table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>Name</th>
                  <th style="width:100px"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="dept in departments" :key="dept.id">
                  <td>{{ dept.name }}</td>
                  <td class="text-end">
                    <button class="btn btn-xs btn-outline-secondary me-1" @click="openDeptModal(dept)">Edit</button>
                    <button class="btn btn-xs btn-outline-danger" @click="deleteDept(dept)">Del</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ── District Modal ─────────────────────────────────────── -->
    <div class="modal fade" id="districtModal" tabindex="-1" ref="districtModalEl">
      <div class="modal-dialog modal-sm">
        <div class="modal-content">
          <div class="modal-header py-2">
            <h6 class="modal-title mb-0">{{ districtForm.id ? 'Edit District' : 'Add District' }}</h6>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label small">Name <span class="text-danger">*</span></label>
              <input v-model="districtForm.name" type="text" class="form-control form-control-sm"
                     :class="{'is-invalid': districtErrors.name}" @keyup.enter="saveDistrict" />
              <div class="invalid-feedback">{{ districtErrors.name }}</div>
            </div>
            <div class="mb-1">
              <label class="form-label small">Parent District</label>
              <select v-model="districtForm.parent_id" class="form-select form-select-sm">
                <option :value="null">— None (top-level) —</option>
                <option v-for="d in parentOptions" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>
            </div>
          </div>
          <div class="modal-footer py-2">
            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-sm btn-primary" :disabled="districtSaving" @click="saveDistrict">
              {{ districtSaving ? 'Saving…' : 'Save' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Department Modal ───────────────────────────────────── -->
    <div class="modal fade" id="deptModal" tabindex="-1" ref="deptModalEl">
      <div class="modal-dialog modal-sm">
        <div class="modal-content">
          <div class="modal-header py-2">
            <h6 class="modal-title mb-0">{{ deptForm.id ? 'Edit Department' : 'Add Department' }}</h6>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-1">
              <label class="form-label small">Name <span class="text-danger">*</span></label>
              <input v-model="deptForm.name" type="text" class="form-control form-control-sm"
                     :class="{'is-invalid': deptErrors.name}" @keyup.enter="saveDept" />
              <div class="invalid-feedback">{{ deptErrors.name }}</div>
            </div>
          </div>
          <div class="modal-footer py-2">
            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-sm btn-primary" :disabled="deptSaving" @click="saveDept">
              {{ deptSaving ? 'Saving…' : 'Save' }}
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
  name: 'DistrictDepartmentEditor',

  props: {
    districtUrl:  { type: String, required: true },
    departmentUrl: { type: String, required: true },
  },

  data() {
    return {
      districts:       [],
      departments:     [],
      districtLoading: true,
      deptLoading:     true,
      flash:           { text: '', ok: true },

      // District modal
      districtForm:   { id: null, name: '', parent_id: null },
      districtErrors: {},
      districtSaving: false,
      districtModal:  null,

      // Department modal
      deptForm:   { id: null, name: '' },
      deptErrors: {},
      deptSaving: false,
      deptModal:  null,
    };
  },

  computed: {
    parentOptions() {
      const editingId = this.districtForm.id;
      // Flat list of top-level districts (cannot pick self or own children as parent)
      const forbidden = new Set([editingId]);
      const childrenOfEditing = (this.districts.find(d => d.id === editingId)?.children ?? []).map(c => c.id);
      childrenOfEditing.forEach(id => forbidden.add(id));

      return this.districts.filter(d => !forbidden.has(d.id));
    },

    allDistrictsFlat() {
      const flat = [];
      this.districts.forEach(d => {
        flat.push(d);
        d.children.forEach(c => flat.push(c));
      });
      return flat;
    },
  },

  mounted() {
    this.districtModal = new window.bootstrap.Modal(this.$refs.districtModalEl);
    this.deptModal     = new window.bootstrap.Modal(this.$refs.deptModalEl);
    this.loadDistricts();
    this.loadDepartments();
  },

  methods: {
    // ── Flash ──────────────────────────────────────────────────
    showFlash(text, ok = true) {
      this.flash = { text, ok };
      setTimeout(() => { this.flash.text = ''; }, 3000);
    },

    // ── Districts ──────────────────────────────────────────────
    async loadDistricts() {
      this.districtLoading = true;
      try {
        const { data } = await axios.get(this.districtUrl);
        this.districts = data;
      } catch {
        this.showFlash('Failed to load districts.', false);
      } finally {
        this.districtLoading = false;
      }
    },

    openDistrictModal(district) {
      this.districtErrors = {};
      if (district) {
        this.districtForm = { id: district.id, name: district.name, parent_id: district.parent_id ?? null };
      } else {
        this.districtForm = { id: null, name: '', parent_id: null };
      }
      this.districtModal.show();
    },

    async saveDistrict() {
      this.districtErrors = {};
      this.districtSaving = true;
      const { id, name, parent_id } = this.districtForm;
      try {
        if (id) {
          await axios.put(`${this.districtUrl}/${id}`, { name, parent_id });
        } else {
          await axios.post(this.districtUrl, { name, parent_id });
        }
        this.districtModal.hide();
        await this.loadDistricts();
        this.showFlash(id ? 'District updated.' : 'District added.');
      } catch (err) {
        if (err.response?.status === 422) {
          const errs = err.response.data.errors ?? {};
          this.districtErrors = Object.fromEntries(
            Object.entries(errs).map(([k, v]) => [k, v[0]])
          );
        } else {
          this.showFlash(err.response?.data?.message ?? 'Error saving district.', false);
        }
      } finally {
        this.districtSaving = false;
      }
    },

    async deleteDistrict(district) {
      if (!confirm(`Delete "${district.name}"? Sub-districts will become top-level.`)) return;
      try {
        await axios.delete(`${this.districtUrl}/${district.id}`);
        await this.loadDistricts();
        this.showFlash('District deleted.');
      } catch {
        this.showFlash('Failed to delete district.', false);
      }
    },

    // ── Departments ────────────────────────────────────────────
    async loadDepartments() {
      this.deptLoading = true;
      try {
        const { data } = await axios.get(this.departmentUrl);
        this.departments = data;
      } catch {
        this.showFlash('Failed to load departments.', false);
      } finally {
        this.deptLoading = false;
      }
    },

    openDeptModal(dept) {
      this.deptErrors = {};
      if (dept) {
        this.deptForm = { id: dept.id, name: dept.name };
      } else {
        this.deptForm = { id: null, name: '' };
      }
      this.deptModal.show();
    },

    async saveDept() {
      this.deptErrors = {};
      this.deptSaving = true;
      const { id, name } = this.deptForm;
      try {
        if (id) {
          await axios.put(`${this.departmentUrl}/${id}`, { name });
        } else {
          await axios.post(this.departmentUrl, { name });
        }
        this.deptModal.hide();
        await this.loadDepartments();
        this.showFlash(id ? 'Department updated.' : 'Department added.');
      } catch (err) {
        if (err.response?.status === 422) {
          const errs = err.response.data.errors ?? {};
          this.deptErrors = Object.fromEntries(
            Object.entries(errs).map(([k, v]) => [k, v[0]])
          );
        } else {
          this.showFlash(err.response?.data?.message ?? 'Error saving department.', false);
        }
      } finally {
        this.deptSaving = false;
      }
    },

    async deleteDept(dept) {
      if (!confirm(`Delete "${dept.name}"?`)) return;
      try {
        await axios.delete(`${this.departmentUrl}/${dept.id}`);
        await this.loadDepartments();
        this.showFlash('Department deleted.');
      } catch {
        this.showFlash('Failed to delete department.', false);
      }
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
</style>
