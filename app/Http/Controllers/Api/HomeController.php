<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\Promotion;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\Menu;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Post;

class HomeController extends Controller
{
    #[Get('/home/statistics')]
    public function statistics()
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Statistics retrieved successfully',
            'data' => [
                'restaurants' => 6,
                'new_dishes' => Dish::where('is_active', true)->count(),
                'years_experience' => 36,
            ]
        ]);
    }

    #[Get('/home/popular-dishes')]
    public function popularDishes()
    {
        $dishes = Dish::where('is_active', true)
            ->with('category')
            ->withAvg('reviews', 'rating')
            ->orderByDesc('reviews_avg_rating')
            ->take(3)
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Popular dishes retrieved successfully',
            'data' => $dishes
        ]);
    }

    #[Get('/home/menu-categories')]
    public function menuCategories()
    {
        $menu = Menu::where('is_active', true)->first();
        $categories = DishCategory::with(['dishes' => function ($query) use ($menu) {
            $query->where('is_active', true)
                  ->whereHas('menuItems', function ($q) use ($menu) {
                      $q->where('menu_id', $menu->id);
                  })
                  ->take(4);
        }])->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Menu categories retrieved successfully',
            'data' => $categories
        ]);
    }

    #[Get('/home/promotions')]
    public function promotions()
    {
        $promotions = Promotion::where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Promotions retrieved successfully',
            'data' => $promotions
        ]);
    }

    #[Get('/home/chefs')]
    public function chefs()
    {
        $chefs = Employee::where('is_active', 1)
            ->whereHas('user', function ($query) {
                $query->whereHas('role', function ($q) {
                    $q->where('name', 'Kitchen Staff');
                });
            })
            ->select('id', 'full_name as name')
            ->take(1)
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Chefs retrieved successfully',
            'data' => $chefs
        ]);
    }

    #[Post('/home/available-tables', middleware: ['auth:api'])]
    public function availableTables(Request $request)
    {
        $request->validate([
            'reserved_at' => 'required|date|after:'.now()->addHour()->toDateTimeString(),
            'number_of_people' => 'required|integer|min:1',
        ]);

        $reservedAt = $request->input('reserved_at');
        $numberOfPeople = $request->input('number_of_people');

        $reservedTableIds = Reservation::where('reserved_at', $reservedAt)
            ->whereIn('status', [0, 1])
            ->pluck('id');

        $tables = DiningTable::where('is_active', true)
            ->where('capacity', '>=', $numberOfPeople)
            ->whereNotIn('id', function ($query) use ($reservedTableIds) {
                $query->select('dining_table_id')
                    ->from('table_session_dining_tables')
                    ->whereIn('reservation_id', $reservedTableIds);
            })
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Available tables retrieved successfully',
            'data' => $tables
        ]);
    }
}