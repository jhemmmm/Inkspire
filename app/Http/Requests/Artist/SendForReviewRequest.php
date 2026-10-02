<?php

namespace App\Http\Requests\Artist;

use App\Models\SystemConfiguration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendForReviewRequest extends FormRequest
{
    /**
     * No authorize() override — the route's role:artist group middleware
     * is the access gate, matching every other non-Admin FormRequest.
     *
     * Tighter than FrontlineStaff's ReplaceJobOrderFileRequest (T-04-08):
     * the file is either an artist-uploaded PNG or JPG or the app's own
     * canvas export. PSD, PDF and AI are intentionally not accepted because
     * neither the artist nor the customer review page can preview them.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxKilobytes = SystemConfiguration::getInt('max_file_size_mb', 50) * 1024;

        return [
            'file' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg', 'max:'.$maxKilobytes],
        ];
    }
}
