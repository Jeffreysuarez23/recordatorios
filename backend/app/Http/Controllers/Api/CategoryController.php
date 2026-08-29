<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    public function index()
    {
        $user_id = Auth::id();
        $categories = Category::where('user_id', $user_id)
            ->orWhere('es_predeterminada', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
            'icono' => 'nullable|string|max:50',
        ]);

        $category = Category::create(array_merge($validated, [
            'user_id' => Auth::id(),
            'es_predeterminada' => false
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Categoría creada',
            'data' => $category
        ], 201);
    }

    public function show($id)
    {
        $category = Category::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    public function update(Request $request, $id)
    {
        $category = Category::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        
        $validated = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'color' => 'nullable|string|max:7',
            'icono' => 'nullable|string|max:50',
        ]);

        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Categoría actualizada',
            'data' => $category
        ]);
    }

    public function destroy($id)
    {
        $category = Category::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Categoría eliminada'
        ]);
    }
}
