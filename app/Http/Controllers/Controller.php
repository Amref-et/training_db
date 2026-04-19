<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;

abstract class Controller
{
    protected function audit(): AuditLogService
    {
        return app(AuditLogService::class);
    }
}
