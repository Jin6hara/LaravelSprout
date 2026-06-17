<?php
/**
 * 地区（District）および部署（Department）のCRUD操作と一覧画面を担うコントローラ。
 */
namespace App\Http\Controllers;

use App\Http\Requests\DistrictDepartment\StoreDepartmentRequest;
use App\Http\Requests\DistrictDepartment\StoreDistrictRequest;
use App\Http\Requests\DistrictDepartment\UpdateDepartmentRequest;
use App\Http\Requests\DistrictDepartment\UpdateDistrictRequest;
use App\Models\Department;
use App\Models\District;

class DistrictDepartmentController extends Controller
{
    // ─── Page ───────────────────────────────────────────────────────────────

    public function index()
    {
        $this->authorize('viewAny', District::class);

        return view('data.district_department');
    }

    // ─── District API ────────────────────────────────────────────────────────

    public function districtIndex()
    {
        $this->authorize('viewAny', District::class);

        $districts = District::with('children')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get()
            ->map(fn($d) => $this->formatDistrict($d));

        return response()->json($districts);
    }

    public function districtStore(StoreDistrictRequest $request)
    {
        $this->authorize('create', District::class);

        $data = $request->validated();

        $district = District::create($data);
        $district->load('children');

        return response()->json($this->formatDistrict($district), 201);
    }

    public function districtUpdate(UpdateDistrictRequest $request, District $district)
    {
        $this->authorize('update', $district);

        $data = $request->validated();

        if ($data['parent_id'] ?? null) {
            abort_if($data['parent_id'] == $district->id, 422, 'Cannot set self as parent.');
        }

        $district->update($data);
        $district->load('children');

        return response()->json($this->formatDistrict($district));
    }

    public function districtDestroy(District $district)
    {
        $this->authorize('delete', $district);

        District::where('parent_id', $district->id)->update(['parent_id' => null]);
        $district->delete();

        return response()->json(null, 204);
    }

    private function formatDistrict(District $d): array
    {
        return [
            'id'        => $d->id,
            'name'      => $d->name,
            'parent_id' => $d->parent_id,
            'children'  => $d->children->sortBy('name')->map(fn($c) => [
                'id'        => $c->id,
                'name'      => $c->name,
                'parent_id' => $c->parent_id,
                'children'  => [],
            ])->values()->toArray(),
        ];
    }

    // ─── Department API ──────────────────────────────────────────────────────

    public function departmentIndex()
    {
        $this->authorize('viewAny', Department::class);

        return response()->json(Department::orderBy('name')->get(['id', 'name']));
    }

    public function departmentStore(StoreDepartmentRequest $request)
    {
        $this->authorize('create', Department::class);

        $data = $request->validated();
        $dept = Department::create($data);

        return response()->json($dept, 201);
    }

    public function departmentUpdate(UpdateDepartmentRequest $request, Department $department)
    {
        $this->authorize('update', $department);

        $data = $request->validated();
        $department->update($data);

        return response()->json($department);
    }

    public function departmentDestroy(Department $department)
    {
        $this->authorize('delete', $department);

        $department->delete();

        return response()->json(null, 204);
    }
}
