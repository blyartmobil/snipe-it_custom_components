<?php

namespace App\Http\Controllers;

use App\Events\CheckoutAccepted;
use App\Events\CheckoutDeclined;
use App\Helpers\Helper;
use App\Mail\CheckoutAcceptanceResponseMail;
use App\Models\CheckoutAcceptance;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AcceptanceItemAcceptedNotification;
use App\Notifications\AcceptanceItemAcceptedToUserNotification;
use App\Notifications\AcceptanceItemDeclinedNotification;
use App\Services\AcceptanceService;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DirectAcceptanceController extends Controller
{
    public function __construct(
        private AcceptanceService $acceptanceService,
    ) {}

    /**
     * Show the tokenized acceptance form (public, no auth required).
     */
    public function show(string $token): View|RedirectResponse
    {
        $acceptance = CheckoutAcceptance::findByValidationToken($token);

        if (! $acceptance) {
            return redirect()->route('login')->with('error', trans('admin/users/message.error.invalid_acceptance_token'));
        }

        $assignedUser = User::find($acceptance->assigned_to_id);

        if (! $assignedUser) {
            return redirect()->route('login')->with('error', trans('admin/users/message.error.invalid_acceptance_token'));
        }

        $item = $acceptance->checkoutable_type::find($acceptance->checkoutable_id);

        $checkedOutAt = Helper::getFormattedDateObject($acceptance->created_at, 'datetime', false);

        return view('acceptances.direct', [
            'acceptance'    => $acceptance,
            'item'          => $item,
            'assignedUser'  => $assignedUser,
            'checkedOutAt'  => $checkedOutAt,
            'settings'      => Setting::getSettings(),
        ]);
    }

    /**
     * Handle the acceptance/declination submission (public, no auth required).
     */
    public function store(Request $request, string $token): RedirectResponse
    {
        $acceptance = CheckoutAcceptance::findByValidationToken($token);

        if (! $acceptance) {
            return redirect()->route('login')->with('error', trans('admin/users/message.error.invalid_acceptance_token'));
        }

        $assignedUser = User::find($acceptance->assigned_to_id);

        if (! $assignedUser) {
            return redirect()->route('login')->with('error', trans('admin/users/message.error.invalid_acceptance_token'));
        }

        $settings = Setting::getSettings();

        if (! $request->filled('asset_acceptance')) {
            return redirect()->back()->with('error', trans('admin/users/message.error.accept_or_decline'));
        }

        // Process signature (if required)
        [$sigFilename, $encodedSignatureImage] = $this->acceptanceService->processSignature($request, $settings);

        $requiresSignature = (string) $settings->require_accept_signature === '1';

        // If signatures are required and we didn't get valid data, kick back
        if ($requiresSignature && $request->input('asset_acceptance') === 'accepted') {
            if (! $request->filled('signature_output')) {
                return redirect()->back()->with('error', trans('general.shitty_browser'));
            }

            if ($sigFilename === '' && $request->filled('signature_output')) {
                return redirect()->back()->with('error', trans('general.shitty_browser'));
            }
        }

        $this->acceptanceService->ensureEulaPdfDirectory();

        // Build the data array for notifications and PDF
        $data = $this->acceptanceService->buildAcceptanceData($acceptance, $assignedUser, [
            'note'              => $request->input('note'),
            'encoded_signature' => $encodedSignatureImage,
        ]);

        if ($request->input('asset_acceptance') === 'accepted') {
            // Generate and store the PDF
            $pdfFilename = $this->acceptanceService->generateAndStorePdf($acceptance, $data);

            // Log the acceptance
            $acceptance->accept($sigFilename, $acceptance->checkoutable_type::find($acceptance->checkoutable_id)?->getEula(), $pdfFilename, $request->input('note'));

            // Send the PDF to the signing user
            if ($request->input('send_copy') === '1' && $assignedUser->email !== '') {
                $data['file'] = $pdfFilename;
                try {
                    $assignedUser->notify((new AcceptanceItemAcceptedToUserNotification($data))->locale($assignedUser->locale));
                } catch (Exception $e) {
                    Log::warning($e);
                }
            }

            try {
                $acceptance->notify((new AcceptanceItemAcceptedNotification($data))->locale($settings->locale));
            } catch (Exception $e) {
                Log::warning($e);
            }

            event(new CheckoutAccepted($acceptance));
            $returnMsg = trans('admin/users/message.accepted');
        } else {
            // Declined
            for ($i = 0; $i < ($acceptance->qty ?? 1); $i++) {
                $acceptance->decline($sigFilename, $request->input('note'));
            }

            $acceptance->notify(new AcceptanceItemDeclinedNotification($data));
            event(new CheckoutDeclined($acceptance));
            $returnMsg = trans('admin/users/message.declined');
        }

        // Send alert-on-response email if configured
        if ($acceptance->alert_on_response_id) {
            try {
                $recipient = User::find($acceptance->alert_on_response_id);

                if ($recipient?->email) {
                    Log::debug('Attempting to send email acceptance (direct flow).');
                    Mail::to($recipient)->send(new CheckoutAcceptanceResponseMail(
                        $acceptance,
                        $recipient,
                        $request->input('asset_acceptance') === 'accepted',
                    ));
                    Log::debug('Send email notification success on checkout acceptance response (direct flow).');
                }
            } catch (Exception $e) {
                Log::error($e->getMessage());
                Log::warning($e);
            }
        }

        // Single-use: invalidate the token
        $acceptance->invalidateToken();

        return redirect()->route('direct.acceptance.complete')->with('success', $returnMsg);
    }

    /**
     * Show a completion/thank-you page after the acceptance flow.
     */
    public function complete(): View
    {
        return view('acceptances.complete');
    }
}