<?php

namespace App\Http\Controllers;

use App\Services\PrivacyPublication;
use Illuminate\View\View;

class PolicyPageController extends Controller
{
    public function __invoke(string $policy, PrivacyPublication $publication): View
    {
        $title = config('medloby.policy_pages')[$policy] ?? null;
        abort_if($title === null, 404);

        return view('policies.show', [
            'title' => $title,
            'content' => 'policies.pages.'.$policy,
            'publication' => $publication,
            'draft' => config('medloby.policies_published') !== true
                || ! $publication->isPublished() || ! $publication->contactSettingsReady(),
        ]);
    }
}
