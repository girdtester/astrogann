<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** New announcement (D-100). Route is already guarded by can:announcements.send. */
class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:500'],
            'audience' => ['required', Rule::in(array_keys(Announcement::AUDIENCES))],
        ];
    }
}
