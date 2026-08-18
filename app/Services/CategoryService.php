<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class CategoryService
{
    public function getAll(array $filters = [])
    {
        return Category::query()
            ->when(
                $filters['search'] ?? null,
                fn($q, $search) =>
                $q->where('name', 'like', "%{$search}%")
            )
            ->when(
                isset($filters['is_active']),
                fn($q) =>
                $q->where('is_active', $filters['is_active'])
            )
            ->latest()
            ->paginate(15);
    }

    public function create(array $data): Category
    {
        return DB::transaction(function () use ($data) {

            $data['slug'] = $this->uniqueSlug(
                $data['slug'] ?? null,
                $data['name']
            );

            if (array_key_exists('icon', $data)) {
                $data['icon'] = $this->normalizeIcon($data['icon']);
            }

            return Category::create($data);
        });
    }

    public function update(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data) {

            if (array_key_exists('slug', $data) || array_key_exists('name', $data)) {
                $data['slug'] = $this->uniqueSlug(
                    $data['slug'] ?? null,
                    $data['name'] ?? $category->name,
                    $category->id
                );
            }

            if (array_key_exists('icon', $data)) {
                $data['icon'] = $this->normalizeIcon($data['icon'], $category->icon);
            }

            $category->update($data);

            return $category->refresh();
        });
    }

    public function delete(Category $category): void
    {
        if ($category->courses()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Cannot delete a category that has linked courses.',
            ]);
        }

        if ($category->icon) {
            Storage::disk('public')->delete($category->icon);
        }

        $category->delete();
    }

    private function uniqueSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug(blank($slug) ? $name : $slug);
        if (blank($baseSlug)) {
            $baseSlug = 'category';
        }

        $candidate = $baseSlug;
        $index = 2;

        while (
            Category::query()
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $candidate = "{$baseSlug}-{$index}";
            $index++;
        }

        return $candidate;
    }

    private function normalizeIcon(mixed $icon, ?string $currentIcon = null): ?string
    {
        if ($icon instanceof UploadedFile) {
            if ($currentIcon) {
                Storage::disk('public')->delete($currentIcon);
            }

            return $icon->store('categories', 'public');
        }

        if ($icon === null || $icon === '') {
            return $currentIcon;
        }

        return (string) $icon;
    }
}
