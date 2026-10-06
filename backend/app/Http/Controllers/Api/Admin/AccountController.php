<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\RoleKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\AccountRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * /api/admin/users and /api/admin/admins — create / manage accounts (spec 3.6). Same AccountService as web.
 * The route default "kind" (user|staff) selects which accounts this controller handles.
 */
class AccountController extends Controller
{
    public function __construct(private readonly AccountService $accounts) {}

    public function index(Request $request): JsonResponse
    {
        $page = User::query()
            ->ofKind($this->kind($request))
            ->when($request->string('q')->trim()->value(), fn ($q, $t) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$t}%")->orWhere('email', 'like', "%{$t}%")))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('name')
            ->paginate(min(100, max(1, (int) $request->query('per_page', 20))));

        return ApiResponse::paginated($page, UserResource::class);
    }

    public function store(AccountRequest $request): JsonResponse
    {
        try {
            $user = $this->accounts->create($this->kind($request), $request->validated(), $request->user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422, ['code' => 'invalid']);
        }

        return ApiResponse::success((new UserResource($user))->resolve($request), 'Account created.', 201);
    }

    public function update(AccountRequest $request, User $account): JsonResponse
    {
        abort_unless($account->kind() === $this->kind($request), 404);

        try {
            $this->accounts->update($account, $request->validated(), $request->user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422, ['code' => 'invalid']);
        }

        return ApiResponse::success((new UserResource($account->fresh()))->resolve($request), 'Account updated.');
    }

    private function kind(Request $request): RoleKind
    {
        return RoleKind::from($request->route()->defaults['kind']);
    }
}
