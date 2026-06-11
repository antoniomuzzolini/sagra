<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Cross-tenant access looks like a missing record, never a 403.
     */
    protected function authorizeTenant(Request $request, Model $model): void
    {
        abort_unless($model->tenant_id === $request->user()->tenant_id, 404);
    }
}
