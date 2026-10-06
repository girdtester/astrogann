<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEntryRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\EntryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use App\Services\CategoryService;
use App\Services\EntryService;
use App\Services\SettingsService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Settings, categories and entries — same services as the web screens (spec 3.9).
 */
class ContentController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly CategoryService $categories,
        private readonly EntryService $entries,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /** GET /api/settings — public app config for the mobile app. */
    public function settings(): JsonResponse
    {
        $s = $this->settings->public();

        return ApiResponse::success([
            'app_name' => $s['app_name'],
            'entries_limit_active' => (int) $s['entries_limit_active'],
            'entries_limit_expired' => (int) $s['entries_limit_expired'],
            'reminder_days' => (int) $s['reminder_days'],
            'show_more_text' => $s['show_more_text'],
            'contact' => [
                'phone' => $s['contact_phone'] ?: null,
                'whatsapp' => $s['contact_whatsapp'] ?: null,
                'email' => $s['contact_email'] ?: null,
                // One item per number (the owner has two numbers for calls + WhatsApp).
                'phones' => $this->settings->contact()['phones'],
                'whatsapp_links' => $this->settings->contact()['whatsapp'],
            ],
            'notice_text' => $s['notice_text'] ?: null,
        ]);
    }

    /** GET /api/categories — active categories in tab order. */
    public function categories(): JsonResponse
    {
        return ApiResponse::success(CategoryResource::collection($this->categories->activeTabs()));
    }

    /** GET /api/categories/{id}/entries — latest 7 or 3 depending on the subscription (spec 3.4). */
    public function entries(Request $request, Category $category): JsonResponse
    {
        abort_unless($category->isActive(), 404);
        $user = $request->user();
        $visible = $this->entries->visibleFor($user, $category);
        $s = $this->settings->public();

        return ApiResponse::success([
            'category' => (new CategoryResource($category))->resolve($request),
            'subscription_active' => ! $visible['restricted'],
            'limit' => $visible['limit'],
            'restricted' => $visible['restricted'],
            'entries' => EntryResource::collection($visible['entries'])->resolve($request),
            'show_more' => $visible['restricted'] ? [
                'text' => $s['show_more_text'],
                'phone' => $s['contact_phone'] ?: null,
                'whatsapp' => $s['contact_whatsapp'] ?: null,
                'email' => $s['contact_email'] ?: null,
                'phones' => $this->settings->contact()['phones'],
                'whatsapp_links' => $this->settings->contact()['whatsapp'],
            ] : null,
        ]);
    }

    /** POST /api/entries — (Admin) add entry, multipart/form-data. */
    public function storeEntry(StoreEntryRequest $request): JsonResponse
    {
        try {
            $entry = $this->entries->create($request->user(), $request->validated(), $request->file('image'));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422, ['code' => 'invalid']);
        }

        return ApiResponse::success((new EntryResource($entry))->resolve($request), 'Entry added.', 201);
    }

    /** GET /api/subscription — the user's subscription details. */
    public function subscription(Request $request): JsonResponse
    {
        return ApiResponse::success($this->subscriptions->summary($request->user()));
    }
}
