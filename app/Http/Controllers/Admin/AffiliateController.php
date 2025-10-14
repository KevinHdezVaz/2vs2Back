<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AffiliateController extends Controller
{
    /**
     * Muestra una lista de todos los afiliados.
     */
    public function index()
    {
        $affiliates = Affiliate::latest()->paginate(10);
        return view('admin.affiliates.index', compact('affiliates'));
    }

    /**
     * Muestra el formulario para crear un nuevo afiliado.
     */
    public function create()
    {
        return view('admin.affiliates.create');
    }

    /**
     * Guarda un nuevo afiliado en la base de datos.
     */
   // En AffiliateController.php

public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'contact_email' => 'nullable|email|max:255',
        // ▼▼▼ CAMBIO: Se añade la validación para el código personalizado ▼▼▼
        'referral_code' => 'required|string|max:255|unique:affiliates,referral_code',
        'discount_percentage' => 'required|numeric|min:0|max:100',
        'commission_rate' => 'required|numeric|min:0|max:100',
    ]);

    Affiliate::create([
        'name' => $request->name,
        'contact_email' => $request->contact_email,
        // ▼▼▼ CAMBIO: Se toma el código del formulario ▼▼▼
        'referral_code' => $request->referral_code,
        'discount_percentage' => $request->discount_percentage,
        'commission_rate' => $request->commission_rate,
        'status' => 'active',
    ]);

    return redirect()->route('affiliates.index')->with('success', 'Afiliado creado con éxito.');
}

    /**
     * Muestra el formulario para editar un afiliado existente.
     */
    public function edit(Affiliate $affiliate)
    {
        return view('admin.affiliates.edit', compact('affiliate'));
    }

    /**
     * Actualiza un afiliado en la base de datos.
     */
    public function update(Request $request, Affiliate $affiliate)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'status' => 'required|string|in:active,inactive',
        ]);

        $affiliate->update($request->all());

        return redirect()->route('affiliates.index')->with('success', 'Afiliado actualizado con éxito.');
    }

    /**
     * Elimina un afiliado de la base de datos.
     */
    public function destroy(Affiliate $affiliate)
    {
        $affiliate->delete();

        return redirect()->route('affiliates.index')->with('success', 'Afiliado eliminado con éxito.');
    }
}