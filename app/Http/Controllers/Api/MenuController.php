<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\Menu;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Post;

class MenuController extends Controller
{
    // Lấy danh sách danh mục và món ăn
    #[Get('/menu/categories')]
    public function categories()
    {
        $menu = Menu::where('is_active', true)->first();
        if (!$menu) {
            return response()->json([
                'status' => 'error',
                'message' => 'No active menu found',
                'data' => []
            ], 404);
        }

        $categories = DishCategory::with(['dishes' => function ($query) use ($menu) {
            $query->where('is_active', true)
                  ->whereHas('menuItems', function ($q) use ($menu) {
                      $q->where('menu_id', $menu->id);
                  })
                  ->withAvg('reviews', 'rating')
                  ->take(4);
        }])
        ->whereHas('dishes', function ($query) use ($menu) {
            $query->where('is_active', true)
                  ->whereHas('menuItems', function ($q) use ($menu) {
                      $q->where('menu_id', $menu->id);
                  });
        })
        ->get()
        ->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->desc,
                'dishes' => $category->dishes->map(function ($dish) {
                    return [
                        'id' => $dish->id,
                        'name' => $dish->name,
                        'description' => $dish->desc,
                        'price' => $dish->menuItems->first()->price ?? $dish->price,
                        'image' => $dish->image,
                        'is_active' => $dish->is_active,
                        'reviews_avg_rating' => $dish->reviews_avg_rating
                    ];
                })
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Menu categories retrieved successfully',
            'data' => $categories
        ]);
    }

    // Lấy menu đặc biệt (Noel, Tết, ...)
    #[Get('/menu/special')]
    public function specialMenu()
    {
        $menu = Menu::where('is_active', true)
            ->with(['menuItems.dish' => function ($query) {
                $query->where('is_active', true)
                      ->withAvg('reviews', 'rating');
            }])
            ->first();

        if (!$menu) {
            return response()->json([
                'status' => 'success',
                'message' => 'No special menu found',
                'data' => null
            ]);
        }

        $specialMenuData = [
            'id' => $menu->id,
            'name' => $menu->name,
            'description' => $menu->description,
            'is_active' => $menu->is_active,
            'dishes' => $menu->menuItems->map(function ($menuItem) {
                $dish = $menuItem->dish;
                return [
                    'id' => $dish->id,
                    'name' => $dish->name,
                    'description' => $dish->desc,
                    'price' => $menuItem->price ?? $dish->price,
                    'image' => $dish->image,
                    'is_active' => $dish->is_active,
                    'reviews_avg_rating' => $dish->reviews_avg_rating
                ];
            })
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Special menu retrieved successfully',
            'data' => $specialMenuData
        ]);
    }

    // Tìm kiếm và lọc món ăn
    #[Post('/menu/search-filter')]
    public function searchFilter(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'category_id' => 'nullable|string|exists:dish_categories,id',
            'price_sort' => 'nullable|in:asc,desc',
            'status' => 'nullable|in:active,inactive',
        ]);

        $query = Dish::where('is_active', true)
            ->whereHas('menuItems', function ($q) {
                $q->whereHas('menu', function ($menuQuery) {
                    $menuQuery->where('is_active', true);
                });
            });

        // Tìm kiếm theo tên món
        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Lọc theo danh mục
        if ($request->has('category_id') && !empty($request->category_id)) {
            $query->where('category_id', $request->category_id);
        }

        // Lọc theo giá
        if ($request->has('price_sort')) {
            $query->orderBy('price', $request->price_sort);
        }

        // Lọc theo trạng thái
        if ($request->has('status')) {
            $query->where('is_active', $request->status === 'active' ? true : false);
        }

        $dishes = $query->with('category')
            ->withAvg('reviews', 'rating')
            ->get()
            ->map(function ($dish) {
                return [
                    'id' => $dish->id,
                    'name' => $dish->name,
                    'description' => $dish->desc,
                    'price' => $dish->menuItems->first()->price ?? $dish->price,
                    'image' => $dish->image,
                    'is_active' => $dish->is_active,
                    'reviews_avg_rating' => $dish->reviews_avg_rating,
                    'category' => $dish->category ? [
                        'id' => $dish->category->id,
                        'name' => $dish->category->name
                    ] : null
                ];
            });

        return response()->json([
            'status' => 'success',
            'message' => 'Dishes retrieved successfully',
            'data' => $dishes
        ]);
    }

    // Lấy món bán chạy
    #[Get('/menu/popular-dishes')]
    public function popularDishes()
    {
        $dishes = Dish::where('is_active', true)
            ->whereHas('menuItems', function ($q) {
                $q->whereHas('menu', function ($menuQuery) {
                    $menuQuery->where('is_active', true);
                });
            })
            ->with('category')
            ->withAvg('reviews', 'rating')
            ->orderByDesc('reviews_avg_rating')
            ->take(3)
            ->get()
            ->map(function ($dish) {
                return [
                    'id' => $dish->id,
                    'name' => $dish->name,
                    'description' => $dish->desc,
                    'price' => $dish->menuItems->first()->price ?? $dish->price,
                    'image' => $dish->image,
                    'is_active' => $dish->is_active,
                    'reviews_avg_rating' => $dish->reviews_avg_rating,
                    'category' => $dish->category ? [
                        'id' => $dish->category->id,
                        'name' => $dish->category->name
                    ] : null
                ];
            });

        return response()->json([
            'status' => 'success',
            'message' => 'Popular dishes retrieved successfully',
            'data' => $dishes
        ]);
    }
}