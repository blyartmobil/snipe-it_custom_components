<?php

namespace App\Http\Controllers\Components;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImageUploadRequest;
use App\Models\Company;
use App\Models\Component;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * This class controls all actions related to Components for
 * the Snipe-IT Asset Management application.
 *
 * @version    v1.0
 */
class ComponentsController extends Controller
{
    /**
     * Returns a view that invokes the ajax tables which actually contains
     * the content for the components listing, which is generated in getDatatable.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @see ComponentsController::getDatatable() method that generates the JSON response
     * @since [v3.0]
     *
     * @return View
     *
     * @throws AuthorizationException
     */
    public function index()
    {
        $this->authorize('view', Component::class);

        return view('components/index');
    }

    /**
     * Returns a form to create a new component.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @see ComponentsController::postCreate() method that stores the data
     * @since [v3.0]
     *
     * @return View
     *
     * @throws AuthorizationException
     */
    public function create()
    {
        $this->authorize('create', Component::class);

        return view('components/edit')->with('category_type', 'component')
            ->with('item', new Component);
    }

    /**
     * Validate and store data for new component.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @see ComponentsController::getCreate() method that generates the view
     * @since [v3.0]
     *
     * @return RedirectResponse
     *
     * @throws AuthorizationException
     */
    public function store(ImageUploadRequest $request)
    {
        $this->authorize('create', Component::class);
        $component = new Component;
        $component->name = $request->input('name');
        $component->category_id = $request->input('category_id');
        $component->supplier_id = $request->input('supplier_id');
        $component->manufacturer_id = $request->input('manufacturer_id');
        $component->model_number = $request->input('model_number');
        $component->location_id = $request->input('location_id');
        $component->company_id = Company::getIdForCurrentUser($request->input('company_id'));
        $component->order_number = $request->input('order_number', null);
        $component->min_amt = $request->input('min_amt', null);
        $component->purchase_date = $request->input('purchase_date', null);
        $component->purchase_cost = $request->input('purchase_cost', null);
        $component->created_by = auth()->id();
        $component->notes = $request->input('notes');

        $component = $request->handleImages($component);

        if ($request->input('redirect_option') === 'back') {
            session()->put(['redirect_option' => 'index']);
        } else {
            session()->put(['redirect_option' => $request->input('redirect_option')]);
        }

        if ($component->save()) {
            // Process serial numbers from the textarea
            $serialInput = $request->input('serials');
            if (! empty($serialInput)) {
                $serialLines = preg_split('/\r\n|\r|\n/', trim($serialInput));
                $serialLines = array_filter(array_map('trim', $serialLines));
                foreach ($serialLines as $serialNumber) {
                    $component->serials()->create([
                        'serial' => $serialNumber,
                        'status' => \App\Models\ComponentSerial::STATUS_AVAILABLE,
                    ]);
                }
                $component->syncQtyFromSerials();
            }

            return Helper::getRedirectOption($request, $component->id, 'Components')
                ->with('success', trans('admin/components/message.create.success'));
        }

        return redirect()->back()->withInput()->withErrors($component->getErrors());
    }

    /**
     * Return a view to edit a component.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @see ComponentsController::postEdit() method that stores the data.
     * @since [v3.0]
     *
     * @param  int  $componentId
     * @return View
     *
     * @throws AuthorizationException
     */
    public function edit(Component $component)
    {

        $this->authorize('update', $component);
        session()->put('url.intended', url()->previous());

        return view('components/edit')
            ->with('item', $component)
            ->with('category_type', 'component');
    }

    /**
     * Return a view to edit a component.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @see ComponentsController::getEdit() method presents the form.
     *
     * @param  int  $componentId
     * @return RedirectResponse
     *
     * @throws AuthorizationException
     *
     * @since [v3.0]
     */
    public function update(ImageUploadRequest $request, Component $component)
    {
        $this->authorize('update', $component);

        // Update the component data
        $component->name = $request->input('name');
        $component->category_id = $request->input('category_id');
        $component->supplier_id = $request->input('supplier_id');
        $component->manufacturer_id = $request->input('manufacturer_id');
        $component->model_number = $request->input('model_number');
        $component->location_id = $request->input('location_id');
        $component->company_id = Company::getIdForCurrentUser($request->input('company_id'));
        $component->order_number = $request->input('order_number');
        $component->min_amt = $request->input('min_amt');
        $component->purchase_date = $request->input('purchase_date');
        $component->purchase_cost = request('purchase_cost');
        $component->notes = $request->input('notes');

        $component = $request->handleImages($component);

        session()->put(['redirect_option' => $request->input('redirect_option')]);

        if ($component->save()) {
            // Process serial numbers from the textarea
            $serialInput = $request->input('serials');
            if ($serialInput !== null) {
                $serialLines = preg_split('/\r\n|\r|\n/', trim($serialInput));
                $serialLines = array_filter(array_map('trim', $serialLines));

                // Get existing serial numbers for comparison
                $existingSerials = $component->serials->pluck('serial')->all();

                // Delete serials that were removed from the textarea
                $removedSerials = array_diff($existingSerials, $serialLines);
                if (! empty($removedSerials)) {
                    $component->serials()
                        ->whereIn('serial', $removedSerials)
                        ->where('status', \App\Models\ComponentSerial::STATUS_AVAILABLE)
                        ->delete();
                }

                // Add new serials that don't exist yet
                $existingNotDeleted = array_diff($existingSerials, $removedSerials);
                foreach ($serialLines as $serialNumber) {
                    if (! in_array($serialNumber, $existingNotDeleted)) {
                        $component->serials()->create([
                            'serial' => $serialNumber,
                            'status' => \App\Models\ComponentSerial::STATUS_AVAILABLE,
                        ]);
                    }
                }

                $component->syncQtyFromSerials();
            }

            return Helper::getRedirectOption($request, $component->id, 'Components')
                ->with('success', trans('admin/components/message.update.success'));
        }

        return redirect()->back()->withInput()->withErrors($component->getErrors());
    }

    /**
     * Delete a component.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @since [v3.0]
     *
     * @param  int  $componentId
     * @return RedirectResponse
     *
     * @throws AuthorizationException
     */
    public function destroy($componentId)
    {
        if (is_null($component = Component::find($componentId))) {
            return redirect()->route('components.index')->with('error', trans('admin/components/message.does_not_exist'));
        }

        $this->authorize('delete', $component);

        // Remove the image if one exists
        if ($component->image && Storage::disk('public')->exists('components/'.$component->image)) {
            try {
                Storage::disk('public')->delete('components/'.$component->image);
            } catch (\Exception $e) {
                Log::debug($e);
            }
        }

        if ($component->numCheckedOut() > 0) {
            return redirect()->route('components.index')->with('error', trans('admin/components/message.delete.error_qty'));
        }

        $component->delete();

        return redirect()->route('components.index')->with('success', trans('admin/components/message.delete.success'));
    }

    /**
     * Return a view to display component information.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @see ComponentsController::getDataView() method that generates the JSON response
     * @since [v3.0]
     *
     * @param  int  $componentId
     * @return View
     *
     * @throws AuthorizationException
     */
    public function show(Component $component)
    {
        $this->authorize('view', $component);

        return view('components/view', compact('component'))->with('snipe_component', $component);
    }

    public function getClone(Component $component): View|RedirectResponse
    {
        $this->authorize('create', Component::class);

        $cloned_component = clone $component;
        $cloned_component->id = null;
        $cloned_component->deleted_at = null;

        // Show the page
        return view('components/edit')
            ->with('item', $cloned_component)
            ->with('component', $cloned_component);
    }
}
