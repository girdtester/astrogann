<?php

namespace App\Exceptions;

use App\Http\Middleware\EnsureActiveSession;
use App\Http\Responses\ApiResponse;
use App\Services\SessionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders every exception on /api/* as the { success, message, data, errors } envelope.
 */
class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null; // web keeps Laravel's normal pages
        }

        return match (true) {
            $e instanceof ValidationException => ApiResponse::error($e->getMessage(), 422, $e->errors()),
            $e instanceof AuthenticationException => $this->unauthenticated($request),
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException
                => ApiResponse::error('You do not have permission to do this.', 403, ['code' => 'forbidden']),
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException
                => ApiResponse::error('Not found.', 404, ['code' => 'not_found']),
            $e instanceof ThrottleRequestsException
                => ApiResponse::error('Too many requests. Please slow down.', 429, ['code' => 'too_many_requests']),
            $e instanceof HttpExceptionInterface && $e->getStatusCode() === 403
                => ApiResponse::error($e->getMessage() ?: 'You do not have permission to do this.', 403, ['code' => 'forbidden']),
            $e instanceof HttpExceptionInterface && $e->getStatusCode() === 404
                => ApiResponse::error('Not found.', 404, ['code' => 'not_found']),
            $e instanceof HttpExceptionInterface
                => ApiResponse::error($e->getMessage() ?: 'Request failed.', $e->getStatusCode(), ['code' => 'http_'.$e->getStatusCode()]),
            default => ApiResponse::error(
                config('app.debug') ? $e->getMessage() : 'Server error. Please try again later.', 500, ['code' => 'server_error']),
        };
    }

    /** A revoked token tells the app *why*: replaced by another login, account deactivated, or plain logout. */
    private function unauthenticated(Request $request): JsonResponse
    {
        return match (app(SessionService::class)->endedReasonForBearer($request->bearerToken())) {
            SessionService::ENDED_REPLACED => ApiResponse::error(EnsureActiveSession::MSG_REPLACED, 401, ['code' => 'session_replaced']),
            SessionService::ENDED_DEACTIVATED => ApiResponse::error(EnsureActiveSession::MSG_INACTIVE, 403, ['code' => 'account_inactive']),
            default => ApiResponse::error('Please log in.', 401, ['code' => 'unauthenticated']),
        };
    }
}
