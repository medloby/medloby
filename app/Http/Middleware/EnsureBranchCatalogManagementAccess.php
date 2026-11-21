<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Services\CatalogManagementAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchCatalogManagementAccess
{
    public function __construct(private CatalogManagementAccess $catalogAccess) {}

    public function handle(Request $request, Closure $next): Response
    {
        $branch = $request->route('branch');
        abort_unless($branch instanceof Branch, 404);

        $this->catalogAccess->authorizeManagement($request->user(), $branch->business_id, $branch->id);

        return $next($request);
    }
}
