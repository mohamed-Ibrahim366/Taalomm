<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $service
    ) {}

    public function index(Request $request)
    {
        return CategoryResource::collection(
            $this->service->getAll($request->all())
        );
    }

    public function store(StoreCategoryRequest $request)
    {
        return new CategoryResource(
            $this->service->create($request->validated())
        );
    }

    public function show(Category $category)
    {
        return new CategoryResource($category);
    }

    public function update(
        UpdateCategoryRequest $request,
        Category $category
    ) {
        return new CategoryResource(
            $this->service->update(
                $category,
                $request->validated()
            )
        );
    }

    public function destroy(Category $category)
    {
        $this->service->delete($category);

        return response()->json([
            'message' => 'Category deleted successfully'
        ]);
    }
}