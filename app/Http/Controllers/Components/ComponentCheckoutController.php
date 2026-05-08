<?php

namespace App\Http\Controllers\Components;

use App\Events\CheckoutableCheckedOut;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Component;
use App\Models\ComponentSerial;
use App\Models\Setting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ComponentCheckoutController extends Controller
{
    /**
     * Returns a view that allows the checkout of a component to an asset.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @see ComponentCheckoutController::store() method that stores the data.
     * @since [v3.0]
     *
     * @param  int  $id
     * @return View
     *
     * @throws AuthorizationException
     */
    public function create($id)
    {

        if ($component = Component::find($id)) {

            $this->authorize('checkout', $component);

            // Make sure the category is valid
            if ($component->category) {

                // Make sure there is at least one available to checkout
                if ($component->numRemaining() <= 0) {
                    return redirect()->route('components.index')
                        ->with('error', trans('admin/components/message.checkout.unavailable'));
                }

                // Return the checkout view
                return view('components/checkout', compact('component'));
            }

            // Invalid category
            return redirect()->route('components.edit', ['component' => $component->id])
                ->with('error', trans('general.invalid_item_category_single', ['type' => trans('general.component')]));
        }

        // Not found
        return redirect()->route('components.index')->with('error', trans('admin/components/message.not_found'));

    }

    /**
     * Validate and store checkout data.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @see ComponentCheckoutController::create() method that returns the form.
     * @since [v3.0]
     *
     * @param  int  $componentId
     * @return RedirectResponse
     *
     * @throws AuthorizationException
     */
    public function store(Request $request, $componentId)
    {
        // Check if the component exists
        if (! $component = Component::find($componentId)) {
            // Redirect to the component management page with error
            return redirect()->route('components.index')->with('error', trans('admin/components/message.not_found'));
        }

        $this->authorize('checkout', $component);

        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:assets,id',
            'serial_ids' => 'required|array|min:1',
            'serial_ids.*' => 'exists:component_serials,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $asset = Asset::find($request->input('asset_id'));

        if ((Setting::getSettings()->full_multiple_companies_support) && $component->company_id !== $asset->company_id) {
            return redirect()->route('components.checkout.show', $componentId)->with('error', trans('general.error_user_company'));
        }

        // Look up the serials, ensuring they belong to this component and are available
        $serials = ComponentSerial::where('component_id', $component->id)
            ->whereIn('id', $request->input('serial_ids'))
            ->where('status', ComponentSerial::STATUS_AVAILABLE)
            ->get();

        if ($serials->count() !== count($request->input('serial_ids'))) {
            return redirect()->back()->withInput()->with('error', 'One or more serials are not available for checkout.');
        }

        foreach ($serials as $serial) {
            $serial->checkout($asset->id, $request->input('note'));
        }

        event(new CheckoutableCheckedOut(
            $component,
            $asset,
            auth()->user(),
            $request->input('note'),
            [],
            $serials->count(),
        ));

        $request->request->add(['checkout_to_type' => 'asset']);
        $request->request->add(['assigned_asset' => $asset->id]);

        session()->put(['redirect_option' => $request->input('redirect_option'), 'checkout_to_type' => $request->input('checkout_to_type')]);

        return Helper::getRedirectOption($request, $component->id, 'Components')
            ->with('success', trans('admin/components/message.checkout.success'));
    }
}
