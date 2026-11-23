<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\ConsentTemplate;
use App\Models\ConsentTemplateVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClinicConsentTemplateController extends Controller
{
    private const array CONTENT_RULES = [
        'title' => ['required', 'string', 'max:255'],
        'content' => ['required', 'string', 'max:20000'],
    ];

    private const array REVISION_RULES = ['revision' => ['required', 'integer', 'min:1']];

    public function index(Request $request, Business $business): JsonResponse
    {
        $this->authorizeOwner($request, $business);

        return $this->respond($business->consentTemplates()
            ->orderByDesc('id')
            ->paginate(20, ['id', 'title'])
            ->toArray());
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $this->authorizeOwner($request, $business);
        $fields = $request->validate(self::CONTENT_RULES);

        $template = DB::transaction(function () use ($request, $business, $fields): ConsentTemplate {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            $this->authorizeOwner($request, $business);

            return $business->consentTemplates()->create($fields);
        }, attempts: 3);

        return $this->templateResponse($template, 201);
    }

    public function show(Request $request, Business $business, string $template): JsonResponse
    {
        $this->authorizeOwner($request, $business);

        return $this->templateResponse($this->findDraft($business, $template));
    }

    public function update(Request $request, Business $business, string $template): JsonResponse
    {
        $this->authorizeOwner($request, $business);

        $saved = DB::transaction(function () use ($request, $business, $template): ConsentTemplate {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            $this->authorizeOwner($request, $business);
            $draft = $this->findDraft($business, $template);
            $fields = $request->validate(self::CONTENT_RULES + self::REVISION_RULES);

            $this->ensureCurrentRevision($draft, (int) $fields['revision']);

            $draft->fill(['title' => $fields['title'], 'content' => $fields['content']]);
            $draft->revision++;
            $draft->save();

            return $draft;
        }, attempts: 3);

        return $this->templateResponse($saved);
    }

    public function versions(Request $request, Business $business, string $template): JsonResponse
    {
        $this->authorizeOwner($request, $business);

        return $this->respond($this->findDraft($business, $template)->versions()
            ->orderByDesc('revision')
            ->paginate(20, ['id', 'revision', 'created_at'])
            ->toArray());
    }

    public function prepareVersion(Request $request, Business $business, string $template): JsonResponse
    {
        $this->authorizeOwner($request, $business);

        $version = DB::transaction(function () use ($request, $business, $template): ConsentTemplateVersion {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            $this->authorizeOwner($request, $business);
            $draft = $this->findDraft($business, $template);
            $revision = (int) $request->validate(self::REVISION_RULES)['revision'];
            $prepared = $revision <= $draft->revision ? $draft->versions()->where('revision', $revision)->first() : null;
            if ($prepared !== null) {
                return $prepared;
            }

            $this->ensureCurrentRevision($draft, $revision);

            return $draft->versions()->create([
                'revision' => $revision,
                'title' => $draft->title,
                'content' => $draft->content,
                'prepared_by_user_id' => $request->user()->id,
            ]);
        }, attempts: 3);

        return $this->versionResponse($version, $version->wasRecentlyCreated ? 201 : 200);
    }

    public function showVersion(Request $request, Business $business, string $template, string $version): JsonResponse
    {
        $this->authorizeOwner($request, $business);
        $draft = $this->findDraft($business, $template);

        return $this->versionResponse($draft->versions()->findOrFail($this->validId($version)));
    }

    private function authorizeOwner(Request $request, Business $business): void
    {
        abort_unless(config('medloby.consent_template_preview') === true && ! app()->isProduction(), 404);
        abort_unless($business->allowsOwnerManagement($request->user()), 403, 'Onam taslaklarını yönetme yetkiniz yok.');
    }

    private function findDraft(Business $business, string $template): ConsentTemplate
    {
        return $business->consentTemplates()->findOrFail($this->validId($template));
    }

    private function validId(string $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        abort_unless($id !== false && $id > 0, 404);

        return $id;
    }

    private function ensureCurrentRevision(ConsentTemplate $draft, int $revision): void
    {
        abort_if($draft->revision !== $revision, 409,
            'Bu taslak başka bir oturumda değişti. En son kaydı açıp değişikliklerinizi yeniden uygulayın.');
    }

    private function templateResponse(ConsentTemplate $template, int $status = 200): JsonResponse
    {
        return $this->respond($template->only(['id', 'title', 'content', 'revision']) + [
            'prepared_revision' => $template->versions()->max('revision'),
        ], $status);
    }

    private function versionResponse(ConsentTemplateVersion $version, int $status = 200): JsonResponse
    {
        return $this->respond($version->only(['id', 'revision', 'title', 'content', 'created_at']), $status);
    }

    private function respond(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status)
            ->header('Cache-Control', 'private, no-store');
    }
}
