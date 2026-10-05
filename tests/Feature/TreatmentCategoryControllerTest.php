<?php

use App\Models\TreatmentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can list treatment categories', function () {
    TreatmentCategory::create([
        'name' => 'Sac Ekimi',
        'slug' => 'sac-ekimi',
        'description' => 'Sac ekimi tedavileri',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/treatment-categories');

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.categories')
        ->assertJsonPath('data.categories.0.name', 'Sac Ekimi');
});

test('can list only active treatment categories', function () {
    TreatmentCategory::create([
        'name' => 'Sac Ekimi',
        'slug' => 'sac-ekimi',
        'is_active' => true,
    ]);

    TreatmentCategory::create([
        'name' => 'Pasif Kategori',
        'slug' => 'pasif-kategori',
        'is_active' => false,
    ]);

    $response = $this->getJson(
        '/api/treatment-categories?active_only=1'
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.categories')
        ->assertJsonPath('data.categories.0.slug', 'sac-ekimi');
});

test('can create treatment category', function () {
    $response = $this->postJson('/api/treatment-categories', [
        'name' => 'Dis Tedavileri',
        'slug' => 'dis-tedavileri',
        'description' => 'Dis tedavileri kategorisi',
        'icon' => 'tooth',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Dis Tedavileri')
        ->assertJsonPath('data.slug', 'dis-tedavileri')
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('treatment_categories', [
        'name' => 'Dis Tedavileri',
        'slug' => 'dis-tedavileri',
        'is_active' => true,
    ]);
});

test('category slug can be generated automatically', function () {
    $response = $this->postJson('/api/treatment-categories', [
        'name' => 'Hair Transplant',
        'is_active' => true,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.slug', 'hair-transplant');
});

test('category is inactive by default', function () {
    $response = $this->postJson('/api/treatment-categories', [
        'name' => 'Yeni Kategori',
        'slug' => 'yeni-kategori',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.is_active', false);
});

test('duplicate category slug is rejected', function () {
    TreatmentCategory::create([
        'name' => 'Sac Ekimi',
        'slug' => 'sac-ekimi',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/treatment-categories', [
        'name' => 'Yeni Sac Ekimi',
        'slug' => 'sac-ekimi',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'slug',
        ]);
});

test('can create child treatment category', function () {
    $parent = TreatmentCategory::create([
        'name' => 'Sac Ekimi',
        'slug' => 'sac-ekimi',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/treatment-categories', [
        'parent_id' => $parent->id,
        'name' => 'FUE',
        'slug' => 'fue',
        'is_active' => true,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.parent_id', $parent->id)
        ->assertJsonPath('data.name', 'FUE');
});

test('cannot use non existing parent category', function () {
    $response = $this->postJson('/api/treatment-categories', [
        'parent_id' => 999999,
        'name' => 'FUE',
        'slug' => 'fue',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'parent_id',
        ]);
});

test('can show treatment category with parent and children', function () {
    $parent = TreatmentCategory::create([
        'name' => 'Sac Ekimi',
        'slug' => 'sac-ekimi',
        'is_active' => true,
    ]);

    TreatmentCategory::create([
        'parent_id' => $parent->id,
        'name' => 'FUE',
        'slug' => 'fue',
        'is_active' => true,
    ]);

    $response = $this->getJson(
        "/api/treatment-categories/{$parent->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Sac Ekimi')
        ->assertJsonCount(1, 'data.children');
});

test('can filter categories by parent', function () {
    $parent = TreatmentCategory::create([
        'name' => 'Sac Ekimi',
        'slug' => 'sac-ekimi',
        'is_active' => true,
    ]);

    TreatmentCategory::create([
        'parent_id' => $parent->id,
        'name' => 'FUE',
        'slug' => 'fue',
        'is_active' => true,
    ]);

    TreatmentCategory::create([
        'name' => 'Dis',
        'slug' => 'dis',
        'is_active' => true,
    ]);

    $response = $this->getJson(
        "/api/treatment-categories?parent_id={$parent->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.categories')
        ->assertJsonPath('data.categories.0.name', 'FUE');
});

test('can update treatment category', function () {
    $category = TreatmentCategory::create([
        'name' => 'Eski Kategori',
        'slug' => 'eski-kategori',
        'description' => 'Eski aciklama',
        'is_active' => true,
    ]);

    $response = $this->putJson(
        "/api/treatment-categories/{$category->id}",
        [
            'name' => 'Yeni Kategori',
            'slug' => 'yeni-kategori',
            'description' => 'Yeni aciklama',
            'is_active' => true,
        ]
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Yeni Kategori')
        ->assertJsonPath('data.slug', 'yeni-kategori');

    $this->assertDatabaseHas('treatment_categories', [
        'id' => $category->id,
        'name' => 'Yeni Kategori',
        'slug' => 'yeni-kategori',
    ]);
});

test('category cannot be its own parent', function () {
    $category = TreatmentCategory::create([
        'name' => 'Sac Ekimi',
        'slug' => 'sac-ekimi',
        'is_active' => true,
    ]);

    $response = $this->putJson(
        "/api/treatment-categories/{$category->id}",
        [
            'parent_id' => $category->id,
        ]
    );

    $response
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('can deactivate treatment category', function () {
    $category = TreatmentCategory::create([
        'name' => 'Sac Ekimi',
        'slug' => 'sac-ekimi',
        'is_active' => true,
    ]);

    $response = $this->deleteJson(
        "/api/treatment-categories/{$category->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('treatment_categories', [
        'id' => $category->id,
        'is_active' => false,
    ]);
});