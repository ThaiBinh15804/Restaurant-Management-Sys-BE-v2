<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UploadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;

#[Prefix('auth/uploads')]
#[Middleware('auth:api')]
class UploadController extends Controller
{
    protected UploadService $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * @OA\Post(
     *     path="/api/auth/uploads/image-user",
     *     tags={"Uploads"},
     *     summary="Upload ảnh người dùng",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file","user_id"},
     *                 @OA\Property(property="file", type="string", format="binary"),
     *                 @OA\Property(property="user_id", type="string", example="123")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="User image uploaded successfully")
     * )
     */
    #[Post('/image-user', middleware: ['permission:table-sessions.create'])]
    public function uploadUserImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
            'user_id' => 'required|string'
        ]);

        $path = "users/{$validated['user_id']}/avatar";
        $url = $this->uploadService->uploadImage($validated['file'], $path);

        return $this->successResponse(['url' => $url], 'User image uploaded successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/auth/uploads/image-dish",
     *     tags={"Uploads"},
     *     summary="Upload ảnh món ăn",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file","dish_id"},
     *                 @OA\Property(property="file", type="string", format="binary"),
     *                 @OA\Property(property="dish_id", type="string", example="45")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Dish image uploaded successfully")
     * )
     */
    #[Post('/image-dish', middleware: ['permission:table-sessions.create'])]
    public function uploadDishImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
            'dish_id' => 'required|string'
        ]);

        $path = "dishes/{$validated['dish_id']}/images";
        $url = $this->uploadService->uploadImage($validated['file'], $path);

        return $this->successResponse(['url' => $url], 'Dish image uploaded successfully');
    }
}
