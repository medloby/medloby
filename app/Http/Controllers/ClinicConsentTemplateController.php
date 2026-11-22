<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\ConsentTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClinicConsentTemplateController extends Controller
{
    private const array CONTENT_RULES = [
        'title' => ['required', 'string', 'max:255'],
        'content' => ['required', 'string', 'max:20000'],
    ];

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
            $fields = $request->validate(self::CONTENT_RULES + ['revision' => ['required', 'integer', 'min:1']]);

            abort_if($draft->revision !== (int) $fields['revision'], 409,
                'Bu taslak başka bir oturumda değişti. En son kaydı açıp değişikliklerinizi yeniden uygulayın.');

            $draft->fill(['title' => $fields['title'], 'content' => $fields['content']]);
            $draft->revision++;
            $draft->save();

            return $draft;
        }, attempts: 3);

        return $this->templateResponse($saved);
    }

    private function authorizeOwner(Request $request, Business $business): void
    {
        abort_unless($business->allowsOwnerManagement($request->user()), 403, 'Onam taslaklarını yönetme yetkiniz yok.');
    }

    private function findDraft(Business $business, string $template): ConsentTemplate
    {
        $id = filter_var($template, FILTER_VALIDATE_INT);
        abort_unless($id !== false && $id > 0, 404);

        return $business->consentTemplates()->findOrFail($id);
    }

    private function templateResponse(ConsentTemplate $template, int $status = 200): JsonResponse
    {
        return $this->respond($template->only(['id', 'title', 'content', 'revision']), $status);
    }

    private function respond(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status)
            ->header('Cache-Control', 'private, no-store');
    }
}
