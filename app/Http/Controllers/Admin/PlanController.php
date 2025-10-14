<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::all();
        return view('admin.plans.index', compact('plans'));
    }

    /**
     * Muestra el formulario para editar un plan.
     * La variable ($plane) DEBE COINCIDIR con el parámetro en la ruta {plane}.
     */
    public function edit(Plan $plane)
    {
        return view('admin.plans.edit', compact('plane'));
    }

    /**
     * Actualiza un plan en la base de datos.
     * La variable ($plane) también debe coincidir aquí.
     */
    public function update(Request $request, Plan $plane)
    {
        $request->validate([
            'price' => 'required|numeric|min:0',
        ]);

        $plane->update([
            'price' => $request->price,
        ]);

        return redirect()->route('planes.index')->with('success', 'Precio del plan actualizado exitosamente.');
    }
}