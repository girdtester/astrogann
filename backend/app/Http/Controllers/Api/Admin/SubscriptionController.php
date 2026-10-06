<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SubscriptionActionRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * POST /api/admin/subscription — activate / extend / expire (spec 3.6). Same SubscriptionService as web.
 */
class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function __invoke(SubscriptionActionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::findOrFail($data['user_id']);
        $by = $request->user();

        try {
            match ($data['action']) {
                'activate' => $this->subscriptions->activate($user, $data['start_date'] ?? today(), $data['end_date'], $by, $data['notes'] ?? null, $data['payment'] ?? null),
                'extend' => $this->subscriptions->extend($user, $data['end_date'], $by, $data['payment'] ?? null),
                'expire' => $this->subscriptions->expire($user, $by),
            };
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422, ['code' => 'invalid']);
        }

        return ApiResponse::success(
            ['user_id' => $user->id] + $this->subscriptions->summary($user),
            'Subscription '.['activate' => 'activated', 'extend' => 'extended', 'expire' => 'expired'][$data['action']].'.',
        );
    }
}
