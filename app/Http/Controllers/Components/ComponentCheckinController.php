<?php

namespace App\Http\Controllers\Components;

use App\Events\CheckoutableCheckedIn;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\ComponentSerial;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ComponentCheckinController extends Controller
{
    /**
     * Returns a view for checking in a component serial from an asset.
     *
     * @since [v4.1.4]
     * @param int $serialId The ID of the ComponentSerial being checked in.
     * @return View
     * @throws AuthorizationException
     */
    public function create($serialId)
    {
        if ($serial = ComponentSerial::with(['component', 'asset'])->find($serialId)) {
            $component = $serial->component;

            if (! $component) {
                return redirect()->route('components.index')->with('error', trans('admin/components/messages.not_found'));
            }

            $this->authorize('checkin', $component);
            $asset = $serial->asset;

            return view('components/checkin', compact('serial', 'component', 'asset'));
        }

        return redirect()->route('components.index')->with('error', trans('admin/components/messages.not_found'));
    }

    /**
     * Process the checkin of a component serial.
     *
     * @since [v4.1.4]
     * @return RedirectResponse
     * @throws AuthorizationException
     */
    public function store(Request $request, $serialId, $backto = null)
    {
        $serial = ComponentSerial::with('component.asset')->find($serialId);

        if (! $serial || ! $serial->component) {
            return redirect()->route('components.index')->with('error', trans('admin/components/message.not_found'));
        }

        $component = $serial->component;
        $this->authorize('checkin', $component);
        $asset = $serial->asset;

        $serial->checkin($request->input('note'));

        if ($asset) {
            event(new CheckoutableCheckedIn($component, $asset, auth()->user(), $request->input('note'), Carbon::now()));
        }

        session()->put(['redirect_option' => $request->input('redirect_option')]);

        return Helper::getRedirectOption($request, $component->id, 'Components')
            ->with('success', trans('admin/components/message.checkin.success'));
    }
}
