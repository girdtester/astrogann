<?php

namespace App\Http\Requests\Admin;

use App\Models\Entry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add entry (category, date, time, image) — web and POST /api/entries. Upload rules: docs/SECURITY.md §4.
 */
class StoreEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Entry::class);
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('status', 'active')],
            'entry_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.today()->subYear()->toDateString(), 'before_or_equal:'.today()->addDays(7)->toDateString()],
            'entry_time' => ['required', 'date_format:H:i'],
            'image' => ['required', 'file', 'image', 'mimes:'.implode(',', config('astrogann.entry_image_mimes')),
                'mimetypes:image/jpeg,image/png,image/webp', 'max:'.config('astrogann.entry_image_max_kb'),
                'dimensions:max_width=8000,max_height=8000'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'Choose an active category.',
            'image.max' => 'The image may not be larger than '.round(config('astrogann.entry_image_max_kb') / 1024, 1).' MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Accept HH:MM:SS from some clients by trimming seconds.
        if (is_string($t = $this->input('entry_time')) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $t)) {
            $this->merge(['entry_time' => substr($t, 0, 5)]);
        }
    }
}
