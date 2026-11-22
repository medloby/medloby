<?php

namespace App\Http\Controllers;

use App\Services\PrivacyPublication;
use Illuminate\View\View;

class PrivacyNoticeController extends Controller
{
    public function __invoke(string $audience, PrivacyPublication $publication): View
    {
        abort_unless($publication->isPublished(), 404);

        $clinic = $audience === 'klinik';

        return view('privacy.notice', [
            'clinic' => $clinic,
            'title' => $clinic ? 'Klinik başvurusu ve hesabı KVKK aydınlatma metni' : 'Hasta hesabı KVKK aydınlatma metni',
            'publication' => $publication,
        ]);
    }
}
