<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    /**
     * Muestra una lista de todos los referidos.
     */
    public function index()
    {
        // Carga los referidos con la información del afiliado y del nuevo usuario
        $referrals = Referral::with(['affiliate', 'newUser'])
                             ->latest()
                             ->paginate(15);

        return view('admin.referrals.index', compact('referrals'));
    }
}