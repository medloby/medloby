<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\SocialMediaAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::create([
        'name' => 'Test Klinik',
        'slug' => 'test-klinik-' . uniqid(),
    ]);

    $this->branch = Branch::create([
        'business_id' => $this->business->id,
        'name' => 'Merkez Şube',
        'slug' => 'merkez-sube-' . uniqid(),
        'status' => 'active',
    ]);

    $this->otherBranch = Branch::create([
        'business_id' => $this->business->id,
        'name' => 'İkinci Şube',
        'slug' => 'ikinci-sube-' . uniqid(),
        'status' => 'active',
    ]);
});

test('can list social media accounts for branch', function () {
    $account = SocialMediaAccount::create([
        'branch_id' => $this->branch->id,
        'platform' => 'instagram',
        'username' => '@testklinik',
        'page_name' => 'Test Klinik',
        'profile_url' => 'https://instagram.com/testklinik',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/social-media"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath(
            'data.0.id',
            $account->id
        )
        ->assertJsonPath(
            'data.0.platform',
            'instagram'
        )
        ->assertJsonPath(
            'data.0.profile_url',
            'https://instagram.com/testklinik'
        );
});

test('social media accounts are returned in sort order', function () {
    $facebook = SocialMediaAccount::create([
        'branch_id' => $this->branch->id,
        'platform' => 'facebook',
        'page_name' => 'Test Klinik Facebook',
        'profile_url' => 'https://facebook.com/testklinik',
        'sort_order' => 2,
    ]);

    $instagram = SocialMediaAccount::create([
        'branch_id' => $this->branch->id,
        'platform' => 'instagram',
        'username' => '@testklinik',
        'profile_url' => 'https://instagram.com/testklinik',
        'sort_order' => 1,
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/social-media"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.0.id',
            $instagram->id
        )
        ->assertJsonPath(
            'data.1.id',
            $facebook->id
        );
});

test('can add social media account to branch', function () {
    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/social-media",
        [
            'platform' => 'Instagram',
            'username' => '@testklinik',
            'page_name' => 'Test Klinik',
            'profile_url' => 'https://instagram.com/testklinik',
            'is_active' => true,
            'sort_order' => 1,
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'data.branch_id',
            $this->branch->id
        )
        ->assertJsonPath(
            'data.platform',
            'instagram'
        )
        ->assertJsonPath(
            'data.username',
            '@testklinik'
        )
        ->assertJsonPath(
            'data.page_name',
            'Test Klinik'
        )
        ->assertJsonPath(
            'data.profile_url',
            'https://instagram.com/testklinik'
        )
        ->assertJsonPath(
            'data.is_active',
            true
        )
        ->assertJsonPath(
            'data.sort_order',
            1
        );

    $this->assertDatabaseHas(
        'social_media_accounts',
        [
            'branch_id' => $this->branch->id,
            'platform' => 'instagram',
            'username' => '@testklinik',
        ]
    );
});

test('platform is automatically normalized to lowercase', function () {
    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/social-media",
        [
            'platform' => 'FACEBOOK',
            'page_name' => 'Test Klinik',
            'profile_url' => 'https://facebook.com/testklinik',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.platform',
            'facebook'
        );
});

test('social media account uses correct default values', function () {
    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/social-media",
        [
            'platform' => 'instagram',
            'profile_url' => 'https://instagram.com/testklinik',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.is_active',
            true
        )
        ->assertJsonPath(
            'data.sort_order',
            0
        )
        ->assertJsonPath(
            'data.username',
            null
        )
        ->assertJsonPath(
            'data.page_name',
            null
        );
});

test('duplicate platform cannot be added to same branch', function () {
    SocialMediaAccount::create([
        'branch_id' => $this->branch->id,
        'platform' => 'instagram',
        'profile_url' => 'https://instagram.com/testklinik',
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/social-media",
        [
            'platform' => 'instagram',
            'profile_url' => 'https://instagram.com/testklinik2',
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath(
            'success',
            false
        );

    $this->assertDatabaseCount(
        'social_media_accounts',
        1
    );
});

test('same platform can be added to different branches', function () {
    SocialMediaAccount::create([
        'branch_id' => $this->branch->id,
        'platform' => 'instagram',
        'profile_url' => 'https://instagram.com/branchone',
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->otherBranch->id}/social-media",
        [
            'platform' => 'instagram',
            'profile_url' => 'https://instagram.com/branchtwo',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.platform',
            'instagram'
        )
        ->assertJsonPath(
            'data.branch_id',
            $this->otherBranch->id
        );
});

test('profile url is required', function () {
    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/social-media",
        [
            'platform' => 'instagram',
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'profile_url',
        ]);
});

test('profile url must be a valid url', function () {
    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/social-media",
        [
            'platform' => 'instagram',
            'profile_url' => 'not-a-url',
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'profile_url',
        ]);
});

test('can show social media account', function () {
    $account = SocialMediaAccount::create([
        'branch_id' => $this->branch->id,
        'platform' => 'instagram',
        'username' => '@testklinik',
        'page_name' => 'Test Klinik',
        'profile_url' => 'https://instagram.com/testklinik',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/social-media/{$account->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.id',
            $account->id
        )
        ->assertJsonPath(
            'data.branch.id',
            $this->branch->id
        );
});

test('cannot show account belonging to another branch', function () {
    $account = SocialMediaAccount::create([
        'branch_id' => $this->otherBranch->id,
        'platform' => 'instagram',
        'profile_url' => 'https://instagram.com/otherbranch',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/social-media/{$account->id}"
    );

    $response
        ->assertNotFound()
        ->assertJsonPath(
            'success',
            false
        );
});

test('can update social media account', function () {
    $account = SocialMediaAccount::create([
        'branch_id' => $this->branch->id,
        'platform' => 'instagram',
        'username' => '@oldaccount',
        'page_name' => 'Eski Sayfa',
        'profile_url' => 'https://instagram.com/oldaccount',
        'sort_order' => 1,
    ]);

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/social-media/{$account->id}",
        [
            'platform' => 'facebook',
            'username' => 'testklinik',
            'page_name' => 'Test Klinik Facebook',
            'profile_url' => 'https://facebook.com/testklinik',
            'sort_order' => 2,
        ]
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'success',
            true
        )
        ->assertJsonPath(
            'data.platform',
            'facebook'
        )
        ->assertJsonPath(
            'data.username',
            'testklinik'
        )
        ->assertJsonPath(
            'data.page_name',
            'Test Klinik Facebook'
        )
        ->assertJsonPath(
            'data.profile_url',
            'https://facebook.com/testklinik'
        )
        ->assertJsonPath(
            'data.sort_order',
            2
        );

    $this->assertDatabaseHas(
        'social_media_accounts',
        [
            'id' => $account->id,
            'platform' => 'facebook',
            'profile_url' => 'https://facebook.com/testklinik',
        ]
    );
});

test('cannot update account to duplicate platform on same branch', function () {
    $instagram = SocialMediaAccount::create([
        'branch_id' => $this->branch->id,
        'platform' => 'instagram',
        'profile_url' => 'https://instagram.com/testklinik',
    ]);

    SocialMediaAccount::create([
        'branch_id' => $this->branch->id,
        'platform' => 'facebook',
        'profile_url' => 'https://facebook.com/testklinik',
    ]);

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/social-media/{$instagram->id}",
        [
            'platform' => 'facebook',
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath(
            'success',
            false
        );
});

test('cannot update account belonging to another branch', function () {
    $account = SocialMediaAccount::create([
        'branch_id' => $this->otherBranch->id,
        'platform' => 'instagram',
        'profile_url' => 'https://instagram.com/otherbranch',
    ]);

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/social-media/{$account->id}",
        [
            'page_name' => 'Yetkisiz Güncelleme',
        ]
    );

    $response
        ->assertNotFound()
        ->assertJsonPath(
            'success',
            false
        );
});

test('can deactivate social media account', function () {
    $account = SocialMediaAccount::create([
        'branch_id' => $this->branch->id,
        'platform' => 'instagram',
        'profile_url' => 'https://instagram.com/testklinik',
        'is_active' => true,
    ]);

    $response = $this->deleteJson(
        "/api/branches/{$this->branch->id}/social-media/{$account->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'success',
            true
        )
        ->assertJsonPath(
            'data.is_active',
            false
        );

    $this->assertDatabaseHas(
        'social_media_accounts',
        [
            'id' => $account->id,
            'is_active' => false,
        ]
    );
});

test('inactive social media account remains in database after deactivation', function () {
    $account = SocialMediaAccount::create([
        'branch_id' => $this->branch->id,
        'platform' => 'instagram',
        'profile_url' => 'https://instagram.com/testklinik',
        'is_active' => true,
    ]);

    $this->deleteJson(
        "/api/branches/{$this->branch->id}/social-media/{$account->id}"
    );

    $this->assertDatabaseHas(
        'social_media_accounts',
        [
            'id' => $account->id,
            'is_active' => false,
        ]
    );
});

test('can add facebook account to branch', function () {
    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/social-media",
        [
            'platform' => 'facebook',
            'page_name' => 'Test Klinik',
            'profile_url' => 'https://facebook.com/testklinik',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.platform',
            'facebook'
        )
        ->assertJsonPath(
            'data.profile_url',
            'https://facebook.com/testklinik'
        );
});

test('can add tiktok account to branch', function () {
    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/social-media",
        [
            'platform' => 'tiktok',
            'username' => '@testklinik',
            'profile_url' => 'https://tiktok.com/@testklinik',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.platform',
            'tiktok'
        )
        ->assertJsonPath(
            'data.profile_url',
            'https://tiktok.com/@testklinik'
        );
});