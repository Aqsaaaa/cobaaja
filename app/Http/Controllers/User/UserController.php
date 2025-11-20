<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Lapangan;
use App\Models\Reservasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function dashboard()
    {
        $user = Auth::user();
        
        $totalReservasi = Reservasi::where('user_id_232112', $user->user_id_232112)->count();
        $pendingReservasi = Reservasi::where('user_id_232112', $user->user_id_232112)
            ->where('status_reservasi_232112', 'pending')
            ->count();
        $confirmedReservasi = Reservasi::where('user_id_232112', $user->user_id_232112)
            ->where('status_reservasi_232112', 'confirmed')
            ->count();

        $recentReservasi = Reservasi::where('user_id_232112', $user->user_id_232112)
            ->with('lapangan')
            ->orderBy('created_at_232112', 'desc')
            ->take(5)
            ->get();

        return view('user.dashboard', compact(
            'totalReservasi',
            'pendingReservasi',
            'confirmedReservasi',
            'recentReservasi'
        ));
    }

    public function lapanganIndex(Request $request)
    {
        $query = Lapangan::where('status_232112', 'active');

        if ($request->has('jenis') && $request->jenis != '') {
            $query->where('jenis_lapangan_232112', $request->jenis);
        }

        $lapangan = $query->paginate(9);
        
        return view('user.lapangan.index', compact('lapangan'));
    }

    public function lapanganShow($id)
    {
        $lapangan = Lapangan::findOrFail($id);
        return view('user.lapangan.show', compact('lapangan'));
    }

    public function reservasiCreate($lapanganId)
    {
        $lapangan = Lapangan::findOrFail($lapanganId);
        return view('user.reservasi.create', compact('lapangan'));
    }

    public function reservasiStore(Request $request)
    {
        $request->validate([
            'lapangan_id' => 'required|exists:lapangan_232112,lapangan_id_232112',
            'tanggal' => 'required|date|after_or_equal:today',
            'waktu_mulai' => 'required|date_format:H:i',
            'waktu_selesai' => 'required|date_format:H:i',
            'catatan' => 'nullable|string',
        ]);

        $lapangan = Lapangan::findOrFail($request->lapangan_id);

        // Hitung durasi dan total harga (with better precision)
        $waktuMulai = Carbon::parse($request->tanggal . ' ' . $request->waktu_mulai);
        $waktuSelesai = Carbon::parse($request->tanggal . ' ' . $request->waktu_selesai);

        // Validate that end time is after start time
        if ($waktuSelesai->lte($waktuMulai)) {
            return back()->withErrors(['waktu_selesai' => 'Waktu selesai harus lebih lama dari waktu mulai.']);
        }

        // Calculate duration in hours using interval to avoid negative issues
        $interval = $waktuMulai->diff($waktuSelesai);
        $durasi = ($interval->h + ($interval->days * 24)) + ($interval->i / 60) + ($interval->s / 3600);

        // Ensure the hourly rate is positive before calculation
        $hargaPerJam = max(0, (float) $lapangan->harga_per_jam_232112);
        $totalHarga = ceil($durasi) * $hargaPerJam; // Use ceiling to round up to next hour

        // Ensure total price is never negative
        $totalHarga = max(0, $totalHarga);

        Reservasi::create([
            'user_id_232112' => Auth::id(),
            'lapangan_id_232112' => $request->lapangan_id,
            'tanggal_reservasi_232112' => $request->tanggal,
            'waktu_mulai_232112' => $request->waktu_mulai,
            'waktu_selesai_232112' => $request->waktu_selesai,
            'total_harga_232112' => $totalHarga,
            'status_reservasi_232112' => 'pending',
            'catatan_232112' => $request->catatan,
        ]);

        return redirect()->route('user.reservasi.index')
            ->with('success', 'Reservasi berhasil dibuat. Menunggu konfirmasi admin.');
    }

    public function reservasiIndex()
    {
        $reservasi = Reservasi::where('user_id_232112', Auth::id())
            ->with('lapangan')
            ->orderBy('created_at_232112', 'desc')
            ->paginate(10);

        return view('user.reservasi.index', compact('reservasi'));
    }
}