<?php

namespace App\Services;

use App\Helpers\Helper;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AcceptanceService
{
    /**
     * Process the signature from the request, if signatures are required.
     * Returns the signature filename and base64-encoded image data.
     *
     * @return array{string, string|null}
     */
    public function processSignature(Request $request, Setting $settings): array
    {
        $sigFilename = '';
        $encodedSignatureImage = null;

        if ((string) $settings->require_accept_signature !== '1') {
            return [$sigFilename, $encodedSignatureImage];
        }

        if (! $request->filled('signature_output')) {
            return [$sigFilename, $encodedSignatureImage];
        }

        $sigFilename = 'siglog-' . Str::uuid() . '-' . date('Y-m-d-his') . '.png';
        $dataUri = (string) $request->input('signature_output');
        $encodedSignatureImage = Str::contains($dataUri, ',')
            ? Str::after($dataUri, ',')
            : $dataUri;

        $decodedImage = base64_decode($encodedSignatureImage, true);

        if ($decodedImage === false) {
            return [$sigFilename, $encodedSignatureImage];
        }

        $decodedImage = $this->flattenSignatureBackgroundToWhite($decodedImage);
        $encodedSignatureImage = base64_encode($decodedImage);

        if (! Storage::exists('private_uploads/signatures')) {
            Storage::makeDirectory('private_uploads/signatures', 775);
        }

        Storage::put('private_uploads/signatures/' . $sigFilename, (string) $decodedImage);

        return [$sigFilename, $encodedSignatureImage];
    }

    /**
     * Ensure the EULA PDFs directory exists.
     */
    public function ensureEulaPdfDirectory(): void
    {
        if (! Storage::exists('private_uploads/eula-pdfs')) {
            Storage::makeDirectory('private_uploads/eula-pdfs', 775);
        }
    }

    /**
     * Build the data array used for notifications and PDF generation.
     *
     * @return array<string, mixed>
     */
    public function buildAcceptanceData(
        CheckoutAcceptance $acceptance,
        User $assignedUser,
        array $overrides = []
    ): array {
        $settings = Setting::getSettings();
        $item = $acceptance->checkoutable_type::find($acceptance->checkoutable_id);

        // Convert PDF logo to base64 for TCPDF
        $encodedLogo = null;
        if ($settings->acceptance_pdf_logo && Storage::disk('public')->exists($settings->acceptance_pdf_logo)) {
            $encodedLogo = base64_encode(file_get_contents(public_path() . '/uploads/' . basename($settings->acceptance_pdf_logo)));
        }

        $data = [
            'item_tag'      => $item->asset_tag ?? null,
            'item_name'     => $item->display_name ?? ($item->name ?? null),
            'item_model'    => $item->model?->name,
            'item_serial'   => $item->serial ?? null,
            'item_status'   => $item->status?->name,
            'eula'          => $item->getEula(),
            'note'          => $overrides['note'] ?? null,
            'check_out_date'    => Helper::getFormattedDateObject($acceptance->created_at, 'datetime', false),
            'accepted_date'     => Helper::getFormattedDateObject(now()->format('Y-m-d H:i:s'), 'datetime', false),
            'declined_date'     => Helper::getFormattedDateObject(now()->format('Y-m-d H:i:s'), 'datetime', false),
            'assigned_to'       => $assignedUser->display_name,
            'email'             => $assignedUser->email,
            'employee_num'      => $assignedUser->employee_num,
            'site_name'         => $settings->site_name,
            'company_name'      => $item->company?->name ?? $settings->site_name,
            'signature'         => $overrides['encoded_signature'] ?? null,
            'logo'              => $encodedLogo ?? null,
            'date_settings'     => $settings->date_display_format,
            'qty'               => $acceptance->qty ?? 1,
        ];

        // Include asset custom fields that are explicitly allowed in outbound emails/PDFs.
        if ($item instanceof Asset && $item->model && $item->model->fieldset) {
            $customFields = [];
            $fields = $item->model->fieldset->fields
                ->where('show_in_email', true)
                ->where('field_encrypted', false);

            foreach ($fields as $field) {
                $dbColumn = $field->db_column;
                $value = $item->{$dbColumn};

                if (! is_null($value) && $value !== '') {
                    $customFields[] = [
                        'label' => $field->name,
                        'value' => $value,
                    ];
                }
            }

            if (! empty($customFields)) {
                $data['custom_fields'] = $customFields;
            }
        }

        return $data;
    }

    /**
     * Generate the acceptance PDF and store it.
     */
    public function generateAndStorePdf(CheckoutAcceptance $acceptance, array $data): string
    {
        $pdfFilename = 'accepted-' . $acceptance->checkoutable_id . '-' . $acceptance->display_checkoutable_type . '-eula-' . date('Y-m-d-h-i-s') . '.pdf';

        $this->ensureEulaPdfDirectory();

        $pdfContent = $acceptance->generateAcceptancePdf($data, $pdfFilename);
        Storage::put('private_uploads/eula-pdfs/' . $pdfFilename, $pdfContent);

        return $pdfFilename;
    }

    /**
     * Flatten a signature image background to white, preserving transparency-based
     * signatures captured on transparent canvases.
     */
    public function flattenSignatureBackgroundToWhite(string $signatureBinary): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagecreatetruecolor')) {
            return $signatureBinary;
        }

        $source = @imagecreatefromstring($signatureBinary);

        if ($source === false) {
            return $signatureBinary;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $flattened = imagecreatetruecolor($width, $height);

        if ($flattened === false) {
            imagedestroy($source);

            return $signatureBinary;
        }

        $white = imagecolorallocate($flattened, 255, 255, 255);
        imagefilledrectangle($flattened, 0, 0, $width, $height, $white);
        imagecopy($flattened, $source, 0, 0, 0, 0, $width, $height);

        ob_start();
        imagepng($flattened);
        $output = ob_get_clean();

        imagedestroy($source);
        imagedestroy($flattened);

        return is_string($output) ? $output : $signatureBinary;
    }
}