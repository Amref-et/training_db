<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $queryText = trim((string) $request->query('q', ''));
        $userId = $request->query('user_id');
        $logType = trim((string) $request->query('log_type', ''));
        $method = strtoupper(trim((string) $request->query('method', '')));
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));

        $logs = UserActivityLog::query()
            ->with('user')
            ->when($queryText !== '', function ($query) use ($queryText) {
                $query->where(function ($inner) use ($queryText) {
                    $inner
                        ->orWhere('action', 'like', '%'.$queryText.'%')
                        ->orWhere('event_key', 'like', '%'.$queryText.'%')
                        ->orWhere('path', 'like', '%'.$queryText.'%')
                        ->orWhere('route_name', 'like', '%'.$queryText.'%')
                        ->orWhere('ip_address', 'like', '%'.$queryText.'%')
                        ->orWhere('auditable_type', 'like', '%'.$queryText.'%')
                        ->orWhere('auditable_label', 'like', '%'.$queryText.'%')
                        ->orWhereHas('user', function ($userQuery) use ($queryText) {
                            $userQuery
                                ->where('name', 'like', '%'.$queryText.'%')
                                ->orWhere('email', 'like', '%'.$queryText.'%');
                        });
                });
            })
            ->when($userId !== null && $userId !== '', fn ($query) => $query->where('user_id', (int) $userId))
            ->when($logType !== '', fn ($query) => $query->where('log_type', $logType))
            ->when($method !== '', fn ($query) => $query->where('method', $method))
            ->when($from !== '', fn ($query) => $query->whereDate('occurred_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('occurred_at', '<=', $to))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.users.activity-log', [
            'logs' => $logs,
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'queryText' => $queryText,
            'selectedUserId' => $userId !== null && $userId !== '' ? (int) $userId : null,
            'selectedLogType' => $logType,
            'selectedMethod' => $method,
            'from' => $from,
            'to' => $to,
            'logTypes' => ['activity', 'audit', 'auth'],
            'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        ]);
    }
}
