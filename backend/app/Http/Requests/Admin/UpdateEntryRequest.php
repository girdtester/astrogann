<?php

namespace App\Http\Requests\Admin;

/**
 * Edit entry — same rules as adding, but the image is optional (keep the current one when empty).
 */
class UpdateEntryRequest extends StoreEntryRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('entry'));
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['image'][0] = 'nullable';

        return $rules;
    }
}
