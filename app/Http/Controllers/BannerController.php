<?php

namespace App\Http\Controllers;

use App\Models\CarouselImage;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index()
    {
        $banners = CarouselImage::all();
        return view('laravel-examples.field-listBanner', compact('banners')); // Cambia 'banner.index' por 'field-listBanner'
    }
    public function create()
    {
        return view('laravel-examples.field-addBanner');
    }

    public function store(Request $request)
    {
        $request->validate([
'image' => 'required|image|mimes:jpeg,png,jpg|max:10240', // Máximo 10MB
        ]);

        $imagePath = $request->file('image')->store('banners', 'public');
        $imageUrl = asset('storage/' . $imagePath);

        CarouselImage::create([
            'image_url' => $imageUrl,
        ]);

        return redirect()->route('banner.index')->with('success', 'Banner agregado correctamente');
    }

    public function destroy($id)
    {
        $banner = CarouselImage::findOrFail($id);
        $banner->delete();
        return redirect()->route('banner.index')->with('success', 'Banner eliminado correctamente');
    }
}