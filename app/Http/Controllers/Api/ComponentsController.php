<?php

namespace App\Http\Controllers\Api;

use App\Events\CheckoutableCheckedIn;
use App\Events\CheckoutableCheckedOut;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImageUploadRequest;
use App\Http\Resources\ComponentResource;
use App\Http\Resources\ComponentSerialResource;
use App\Http\Transformers\ActionlogsTransformer;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Component;
use App\Models\ComponentSerial;
use App\Services\ComponentService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ComponentsController extends Controller
{
    public function __construct(
        private readonly ComponentService $componentService,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @since [v4.0]
     */
    public function index(Request $request): JsonResponse|array
    {
        $this->authorize('view', Component::class);

        // This array is what determines which fields should be allowed to be sorted on ON the table itself, no relations
        // Relations will be handled in query scopes a little further down.
        $allowed_columns =
            [
                'id',
                'name',
                'min_amt',
                'order_number',
                'model_number',
                'serial',
                'purchase_date',
                'purchase_cost',
                'qty',
                'image',
                'notes',
                // These are *relationships* so we wouldn't normally include them in this array,
                // since they would normally create a `column not found` error,
                // BUT we account for them in the ordering switch down at the end of this method
                // DO NOT ADD ANYTHING TO THIS LIST WITHOUT CHECKING THE ORDERING SWITCH BELOW!
                'company',
                'location',
                'category',
                'manufacturer',
                'supplier',

            ];

        $components = Component::select('components.*')
            ->with('company', 'location', 'category', 'supplier', 'adminuser', 'manufacturer')
            ->withCount('serials')
            ->withCount(['serials as available_serials_count' => fn($q) => $q->where('status', 'available')])
            ->withCount(['serials as checked_out_serials_count' => fn($q) => $q->where('status', 'checked_out')]);

        $filter = [];

        if ($request->filled('filter')) {
            $filter = json_decode($request->input('filter'), true);

            $filter = array_filter($filter, function ($key) use ($allowed_columns) {
                return in_array($key, $allowed_columns);
            }, ARRAY_FILTER_USE_KEY);

        }

        // This invokes the Searchable model trait scopeTextSearch and will handle input by search or by advanced search filter
        if ($request->filled('filter') || $request->filled('search')) {
            $components->TextSearch($request->input('filter') ? $request->input('filter') : $request->input('search'));
        }

        if ($request->filled('name')) {
            $components->where('components.name', '=', $request->input('name'));
        }

        if ($request->filled('company_id')) {
            $components->where('components.company_id', '=', $request->input('company_id'));
        }

        if ($request->filled('order_number')) {
            $components->where('components.order_number', '=', $request->input('order_number'));
        }

        if ($request->filled('category_id')) {
            $components->where('components.category_id', '=', $request->input('category_id'));
        }

        if ($request->filled('supplier_id')) {
            $components->where('components.supplier_id', '=', $request->input('supplier_id'));
        }

        if ($request->filled('manufacturer_id')) {
            $components->where('components.manufacturer_id', '=', $request->input('manufacturer_id'));
        }

        if ($request->filled('model_number')) {
            $components->where('components.model_number', '=', $request->input('model_number'));
        }

        if ($request->filled('location_id')) {
            $components->where('components.location_id', '=', $request->input('location_id'));
        }

        if ($request->filled('notes')) {
            $components->where('components.notes', '=', $request->input('notes'));
        }

        // Make sure the offset and limit are actually integers and do not exceed system limits
        $components_count = $components->count();
        $offset = ($request->input('offset') > $components_count) ? $components_count : app('api_offset_value');
        $limit = app('api_limit_value');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $sort_override = $request->input('sort');
        $column_sort = in_array($sort_override, $allowed_columns) ? $sort_override : 'created_at';

        switch ($sort_override) {
            case 'category':
                $components = $components->OrderCategory($order);
                break;
            case 'location':
                $components = $components->OrderLocation($order);
                break;
            case 'company':
                $components = $components->OrderCompany($order);
                break;
            case 'supplier':
                $components = $components->OrderSupplier($order);
                break;
            case 'manufacturer':
                $components = $components->OrderManufacturer($order);
                break;
            case 'created_by':
                $components = $components->OrderByCreatedBy($order);
                break;
            default:
                $components = $components->orderBy($column_sort, $order);
                break;
        }

        $total = $components_count;
        $components = $components->skip($offset)->take($limit)->get();

        return ComponentResource::collection($components)->additional(['total' => $total]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @since [v4.0]
     */
    public function store(ImageUploadRequest $request): JsonResponse
    {
        $this->authorize('create', Component::class);
        $component = new Component;
        $component->fill($request->all());
        $component->company_id = Company::getIdForCurrentUser($request->input('company_id'));
        $component = $request->handleImages($component);

        if ($component->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', $component, trans('admin/components/message.create.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $component->getErrors()));
    }

    /**
     * Display the specified resource.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @param  int  $id
     */
    public function show($id): array
    {
        $this->authorize('view', Component::class);
        $component = Component::findOrFail($id);

        if ($component) {
            return new ComponentResource($component);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @since [v4.0]
     *
     * @param  int  $id
     */
    public function update(ImageUploadRequest $request, $id): JsonResponse
    {
        $this->authorize('update', Component::class);
        $component = Component::findOrFail($id);
        $component->fill($request->all());
        $component->company_id = Company::getIdForCurrentUser($request->input('company_id'));
        $component = $request->handleImages($component);

        if ($component->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', $component, trans('admin/components/message.update.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $component->getErrors()));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @since [v4.0]
     *
     * @param  int  $id
     */
    public function destroy($id): JsonResponse
    {
        $this->authorize('delete', Component::class);
        $component = Component::findOrFail($id);
        $this->authorize('delete', $component);

        if ($component->numCheckedOut() > 0) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/components/message.delete.error_qty')));
        }

        $component->delete();

        return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/components/message.delete.success')));
    }

    /**
     * Display all checked-out serials for a component.
     *
     * @author [A. Bergamasco] [@vjandrea]
     * @since [v4.0]
     */
    public function getAssets(Component $component, Request $request): array
    {
        $this->authorize('view', Asset::class);

        $offset = request('offset', 0);
        $limit = $request->input('limit', 50);

        $serials = $component->checkedOutSerials()->with('asset');

        if ($request->filled('search')) {
            $search_str = '%'.$request->input('search').'%';
            $serials->where(function ($query) use ($search_str) {
                $query->where('serial', 'like', $search_str)
                    ->orWhereHas('asset', function ($q) use ($search_str) {
                        $q->where('name', 'like', $search_str)
                            ->orWhere('asset_tag', 'like', $search_str);
                    });
            });
        }

        $total = $serials->count();
        $serials = $serials->skip($offset)->take($limit)->get();

        return ComponentSerialResource::collection($serials)->additional(['total' => $total]);
    }

    /**
     * Checkout one or more serial numbers to an asset.
     *
     * @since [v5.1.8]
     */
    public function checkout(Request $request, $componentId): JsonResponse
    {
        if (! $component = Component::find($componentId)) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/components/message.does_not_exist')));
        }

        $this->authorize('checkout', $component);

        $validator = Validator::make($request->all(), [
            'assigned_to' => 'required|exists:assets,id',
            'serial_ids' => 'required|array|min:1',
            'serial_ids.*' => 'exists:component_serials,id',
        ]);

        if ($validator->fails()) {
            return response()->json(Helper::formatStandardApiResponse('error', $validator->errors()));
        }

        $asset = Asset::find($request->input('assigned_to'));

        if ($this->componentService->checkout($component, $asset, $request->input('serial_ids'), $request->input('note'))->isEmpty()) {
            return response()->json(Helper::formatStandardApiResponse('error', null, 'One or more serials are not available for checkout.'));
        }

        return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/components/message.checkout.success')));
    }

    /**
     * Checkin a single serial from an asset.
     *
     * @since [v5.1.8]
     */
    public function checkin(Request $request, $serialId): JsonResponse
    {
        $serial = ComponentSerial::find($serialId);

        if (! $serial) {
            return response()->json(Helper::formatStandardApiResponse('error', null, 'Serial not found.'));
        }

        if (! $serial->isCheckedOut()) {
            return response()->json(Helper::formatStandardApiResponse('error', null, 'This serial is not currently checked out.'));
        }

        $component = $serial->component;
        $this->authorize('checkin', $component);

        $this->componentService->checkin($serial, $request->input('note'));

        return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/components/message.checkin.success')));
    }

    /**
     * Checkin multiple serials from a component at once.
     *
     * @since [v5.1.8]
     */
    public function bulkCheckin(Request $request, Component $component): JsonResponse
    {
        if (! $component) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/components/message.does_not_exist')));
        }

        $this->authorize('checkin', $component);

        $validator = Validator::make($request->all(), [
            'serial_ids' => 'required|array|min:1',
            'serial_ids.*' => 'exists:component_serials,id',
        ]);

        if ($validator->fails()) {
            return response()->json(Helper::formatStandardApiResponse('error', $validator->errors()));
        }

        $result = $this->componentService->bulkCheckin($component, $request->input('serial_ids'), $request->input('note'));

        return response()->json(Helper::formatStandardApiResponse('success', $result, trans('admin/components/message.checkin.success')));
    }

    /**
     * List all serials for a component.
     */
    public function listSerials(Component $component, Request $request): array
    {
        $this->authorize('view', $component);

        $serials = $component->serials()->with('asset');

        if ($request->filled('status')) {
            $serials->where('status', $request->input('status'));
        }

        $total = $serials->count();
        $offset = request('offset', 0);
        $limit = $request->input('limit', 50);
        $serials = $serials->skip($offset)->take($limit)->get();

        return ComponentSerialResource::collection($serials)->additional(['total' => $total]);
    }

    /**
     * Show a single serial.
     */
    public function showSerial(Component $component, $serialId): array
    {
        $this->authorize('view', $component);
        $serial = $component->serials()->findOrFail($serialId);

        return new ComponentSerialResource($serial);
    }

    /**
     * Add new serials to a component.
     */
    public function storeSerial(Request $request, Component $component): JsonResponse
    {
        $this->authorize('update', $component);

        $validator = Validator::make($request->all(), [
            'serials' => 'required|array|min:1',
            'serials.*.serial' => "required|string|distinct|max:191|unique:component_serials,serial,NULL,id,component_id,{$component->id}",
            'serials.*.notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(Helper::formatStandardApiResponse('error', $validator->errors()));
        }

        $created = $this->componentService->createSerials(
            $component,
            $request->input('serials'),
        );

        return response()->json(Helper::formatStandardApiResponse('success', $created, 'Serials added successfully.'));
    }

    /**
     * Update a single serial.
     */
    public function updateSerial(Request $request, Component $component, $serialId): JsonResponse
    {
        $this->authorize('update', $component);
        $serial = $component->serials()->findOrFail($serialId);

        $validator = Validator::make($request->all(), [
            'serial' => "sometimes|string|max:191|unique:component_serials,serial,{$serial->id},id,component_id,{$component->id}",
            'status' => 'sometimes|string|in:available,checked_out,defective,retired',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(Helper::formatStandardApiResponse('error', $validator->errors()));
        }

        $serial = $this->componentService->updateSerial($serial, $request->only(['serial', 'status', 'notes']));

        return response()->json(Helper::formatStandardApiResponse('success', $serial, 'Serial updated.'));
    }

    /**
     * Delete a serial (only if not checked out).
     */
    public function deleteSerial(Component $component, $serialId): JsonResponse
    {
        $this->authorize('update', $component);
        $serial = $component->serials()->findOrFail($serialId);

        if (! $this->componentService->deleteSerial($serial)) {
            return response()->json(Helper::formatStandardApiResponse('error', null, 'Cannot delete a checked-out serial.'));
        }

        return response()->json(Helper::formatStandardApiResponse('success', null, 'Serial deleted.'));
    }

    public function history(Request $request, Component $component): JsonResponse
    {
        $this->authorize('history', $component);
        $historyQuery = $component->getHistory($request);
        $total = (clone $historyQuery)->count();
        $offset = ($request->input('offset') > $total) ? $total : app('api_offset_value');
        $limit = app('api_limit_value');
        $history = (clone $historyQuery)->skip($offset)->take($limit)->get();

        return response()->json((new ActionlogsTransformer)->transformActionlogs($history, $total), 200, ['Content-Type' => 'application/json;charset=utf8'], JSON_UNESCAPED_UNICODE);
    }
}
