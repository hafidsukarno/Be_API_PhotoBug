<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username|max:255',
            'email' => 'required|string|email|unique:users,email|max:255',
            'password' => 'required|string|min:6',
            'no_hp' => 'nullable|string|max:20',
            'village_id' => 'nullable|integer|exists:villages,id'
        ]);

        $petaniRole = Role::where('name', 'petani')->first();

        if (!$petaniRole) {
            return response()->json(['message' => 'Role petani tidak ditemukan'], 500);
        }

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'no_hp' => $request->no_hp,
            'role_id' => $petaniRole->id,
            'village_id' => $request->village_id,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registrasi Petani Berhasil',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer'
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string'
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Username atau Password yang diinputkan salah.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login Berhasil',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer'
        ]);
    }
    
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'message' => 'Logout Berhasil'
        ]);
    }

    // Step 1: Request OTP untuk reset password
    public function requestOtpForReset(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email'
            ]);

            $user = User::with('role')->where('email', $request->email)->first();

            // Cek apakah user adalah Petani
            if ($user->role->name !== 'petani') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Email tidak terdaftar sebagai akun Petani.'
                ], 403);
            }

            // Generate OTP 6 digit random
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Hapus OTP lama yang belum expired
            DB::table('otp_tokens')
                ->where('email', $request->email)
                ->where('used', false)
                ->delete();

            // Simpan OTP baru (berlaku 10 menit)
            DB::table('otp_tokens')->insert([
                'email' => $request->email,
                'otp' => $otp,
                'expires_at' => Carbon::now()->addMinutes(10),
                'used' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            // TODO: Kirim OTP ke email user
            // Untuk saat ini hanya di-log (development mode)
            // \Log::info("OTP untuk {$request->email}: {$otp}");

            return response()->json([
                'status' => 'success',
                'message' => 'OTP sudah dikirim ke email Anda. Berlaku 10 menit.',
                'data' => [
                    'email' => $request->email,
                    'name' => $user->name
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal request OTP: ' . $e->getMessage()
            ], 500);
        }
    }

    // Step 2: Verify OTP
    public function verifyOtp(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|digits:6'
            ]);

            // Cek OTP di database
            $otpRecord = DB::table('otp_tokens')
                ->where('email', $request->email)
                ->where('otp', $request->otp)
                ->where('used', false)
                ->where('expires_at', '>', Carbon::now())
                ->first();

            if (!$otpRecord) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'OTP tidak valid atau sudah kadaluarsa'
                ], 400);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'OTP valid. Silakan reset password Anda.',
                'data' => [
                    'email' => $request->email
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        }
    }

    // Step 3: Reset password dengan OTP
    public function resetPasswordWithOtp(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|digits:6',
                'password' => 'required|min:6|confirmed'
            ]);

            // Verify OTP
            $otpRecord = DB::table('otp_tokens')
                ->where('email', $request->email)
                ->where('otp', $request->otp)
                ->where('used', false)
                ->where('expires_at', '>', Carbon::now())
                ->first();

            if (!$otpRecord) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'OTP tidak valid atau sudah kadaluarsa'
                ], 400);
            }

            $user = User::with('role')->where('email', $request->email)->first();

            // Double check user adalah Petani
            if ($user->role->name !== 'petani') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akses ditolak'
                ], 403);
            }

            // Update password
            $user->update([
                'password' => Hash::make($request->password)
            ]);

            // Mark OTP sebagai sudah dipakai
            DB::table('otp_tokens')
                ->where('id', $otpRecord->id)
                ->update(['used' => true, 'updated_at' => Carbon::now()]);

            return response()->json([
                'status' => 'success',
                'message' => 'Password berhasil diubah. Silakan login dengan password baru.'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal reset password: ' . $e->getMessage()
            ], 500);
        }
    }

    // (Deprecated) Verify email for reset - untuk backward compatibility
    public function verifyEmailForReset(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $user = User::with('role')->where('email', $request->email)->first();

        if ($user->role->name !== 'petani') {
            return response()->json([
                'status' => 'error',
                'message' => 'Email tidak terdaftar sebagai akun Petani.'
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Email terverifikasi. Silakan masuk ke halaman reset password.',
            'data' => [
                'email' => $user->email,
                'name' => $user->name
            ]
        ]);
    }

    // (Deprecated) Reset password tanpa OTP - untuk backward compatibility
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::with('role')->where('email', $request->email)->first();

        if ($user->role->name !== 'petani') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ganti password secara langsung hanya tersedia untuk akun Petani.'
            ], 403);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Password berhasil diubah. Silakan login dengan password baru.'
        ]);
    }
}
