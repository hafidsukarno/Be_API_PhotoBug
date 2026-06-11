<?php

namespace App\Http\Controllers\Petani;

use App\Http\Controllers\Controller;
use App\Models\Detection;
use App\Models\DetectionResult;
use App\Models\Recommendation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Storage;

class DetectionController extends Controller
{
    // Upload gambar hama
    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // Max 5MB
            'description' => 'nullable|string',
        ]);

        $user = $request->user();

        // Simpan gambar ke storage
        $imagePath = $request->file('image')->store('detections', 'public');

        $detection = Detection::create([
            'user_id' => $user->id,
            'image_path' => $imagePath,
            'detected_at' => now(),
            'status' => 'pending',
            'description' => $request->description,
        ]);

        // Hubungkan ke Python API FastAPI (YOLOv11)
        $aiResults = [];
        try {
            // Kita kirimkan file gambar ke endpoint Python (misal namanya /predict)
            // Ganti '/predict' sesuai dengan nama fungsi di Python Anda (bisa /detect, /analyze, dsb)
            $response = Http::attach(
                'file', file_get_contents($request->file('image')->path()), 'image.jpg'
            )->post('http://127.0.0.1:9000/predict'); // <-- Base URL dapet dari Uvicorn Python

            if ($response->successful()) {
                // Di sini asumsikan Python mengembalikan JSON berbentuk array data deteksi
                $pythonData = $response->json();
                
                // Mengambil list prediksi dari dalam "data" (sesuai format FastAPI YOLO)
                $detectionsList = [];
                if (isset($pythonData['success']) && $pythonData['success'] === true && isset($pythonData['data'])) {
                    $detectionsList = $pythonData['data'];
                }

                if (count($detectionsList) > 0) {
                    foreach ($detectionsList as $item) {
                        $rawPestName = $item['class_name'] ?? 'Hama Tidak Diketahui';
                        $cleanPestName = str_replace('_', ' ', $rawPestName);

                        $aiResults[] = [
                            // Pakai nama yang sudah dihilangkan underscore-nya
                            'pest_name' => $cleanPestName,
                            // Confidence dari Python (misal 0.89) langsung diubah ke 89
                            'confidence' => isset($item['confidence']) ? ($item['confidence'] * 100) : 50 
                        ];
                    }
                }
            } else {
                Log::error('Python AI Error: HTTP Status ' . $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Gagal terhubung ke Python AI: ' . $e->getMessage());
        }

        // Kalau gagal atau hama tidak ditemukan, pasang nilai default aman
        if (empty($aiResults)) {
            $aiResults = [
                ['pest_name' => 'Tidak Terdeteksi (Silakan Tunggu Penyuluh)', 'confidence' => 0]
            ];
        }

        // Simpan hasil deteksi ke tabel database
        foreach ($aiResults as $result) {
            DetectionResult::create([
                'detection_id' => $detection->id,
                'pest_name' => $result['pest_name'],
                'confidence' => $result['confidence'] / 100 // Dibagi kembalikan ke float DB (0-1)
            ]);
        }

        // Kamus rekomendasi berdasarkan standar pertanian (BPP) (Gunakan lowercase untuk keys)
        $dummyRecommendations = [
            'wereng coklat' => '1. Keringkan lahan sawah secara berkala (jangan digenangi terus) untuk mengurangi kelembapan yang disukai hama ini.' . "\n" . 
                               '2. Segera gunakan insektisida berbahan aktif Buprofezin, Pimetrozin, atau BPMC jika jumlah wereng >10 ekor/rumpun. Arahkan semprotan langsung ke pangkal batang bawah.' . "\n" . 
                               '3. Untuk musim berikutnya, terapkan sistem tanam Jajar Legowo dan gunakan varietas tahan wereng (VUTW).',
            'wereng cokelat' => '1. Keringkan lahan sawah secara berkala (jangan digenangi terus) untuk mengurangi kelembapan yang disukai hama ini.' . "\n" . 
                                '2. Segera gunakan insektisida berbahan aktif Buprofezin, Pimetrozin, atau BPMC jika jumlah wereng >10 ekor/rumpun. Arahkan semprotan langsung ke pangkal batang bawah.' . "\n" . 
                                '3. Untuk musim berikutnya, terapkan sistem tanam Jajar Legowo dan gunakan varietas tahan wereng (VUTW).',
            'wereng hijau' => '1. Waspadai penularan penyakit virus Tungro (daun padi berubah kuning-oranye dan kerdil) yang dibawa oleh Wereng Hijau.' . "\n" . 
                              '2. Bersihkan gulma di sekitar lahan karena bisa menjadi inang virus.' . "\n" . 
                              '3. Jika serangan parah, lakukan penyemprotan pestisida berbahan aktif Lamdasilahotrin atau Tiametoksam sesuai dosis yang dianjurkan.',
        ];

        // Buat rekomendasi awal dari AI
        $topResult = $aiResults[0];
        
        if ($topResult['confidence'] > 0) {
            $pestName = $topResult['pest_name'];
            $lookupName = strtolower($pestName); // Pastikan lowercase agar cocok dengan kamus
            $aiAdvice = $dummyRecommendations[$lookupName] ?? 'Silakan konsultasikan dengan penyuluh untuk penanganan lebih lanjut.';
            
            $recommendationText = "Tanaman terdeteksi terkena {$pestName} dengan akurasi {$topResult['confidence']}%. Rekomendasi Penanganan: {$aiAdvice}";
        } else {
            $recommendationText = "Hama tidak atau gagal dikenali AI. Mohon tunggu penyuluh memberikan analisis manual berdasarkan foto dan detail Anda.";
        }

        Recommendation::create([
            'detection_id' => $detection->id,
            'recommendation_text' => $recommendationText,
            'source' => 'AI'
        ]);

        // ==========================================
        // 🟢 KIRIM NOTIFIKASI KE TELEGRAM PENYULUH 
        // ==========================================
        // Hanya kirim ke Telegram jika ada hama yang terdeteksi dengan akurasi >= 50%
        $shouldSendTelegram = false;
        foreach ($aiResults as $result) {
            if ($result['pest_name'] !== 'Tidak Terdeteksi (Silakan Tunggu Penyuluh)' && $result['confidence'] >= 50) {
                $shouldSendTelegram = true;
                break;
            }
        }

        if ($shouldSendTelegram) {
            try {
                $this->sendTelegramNotification($detection, $aiResults, $recommendationText);
            } catch (\Exception $e) {
                Log::error('Gagal mengirim Telegram Webhook: ' . $e->getMessage());
            }
        } else {
            Log::info('Telegram tidak dikirim: Hama tidak terdeteksi atau akurasi < 50%');
        }

        // Load relationships
        $detection->load('detectionResults', 'recommendations', 'user.village.penyuluh');
        
        // Get penyuluh name
        $penyuluhName = null;
        if ($detection->user && $detection->user->village && $detection->user->village->penyuluh) {
            $penyuluhName = $detection->user->village->penyuluh->name;
        }

        // Count total pests
        $totalPests = $detection->detectionResults->count();

        return response()->json([
            'message' => 'Deteksi berhasil disimpan',
            'detection' => $detection,
            'penyuluh_name' => $penyuluhName,
            'total_pests' => $totalPests
        ], 201);
    }

    private function sendTelegramNotification($detection, $aiResults, $recommendationText)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        
        $petani = clone $detection->user;
        $petani->load('village.penyuluh');
        $namaDesa = $petani->village ? $petani->village->village_name : 'Tidak diketahui';

        // 1. Kumpulkan data hama (misal jika ada lebih dari 1 temuan hama yang sama)
        $pestCounts = [];
        foreach ($aiResults as $res) {
            // Ganti underscore dengan spasi supaya teks lebih rapi dan tidak merusak parsing Markdown Telegram
            $name = ucwords(str_replace('_', ' ', $res['pest_name']));
            
            if (!isset($pestCounts[$name])) {
                $pestCounts[$name] = [
                    'count' => 0, 
                    'confidences' => []
                ];
            }
            $pestCounts[$name]['count']++;
            
            // Simpan akurasi dengan dibulatkan 1 angka di belakang koma
            $pestCounts[$name]['confidences'][] = round(floatval($res['confidence']), 1);
        }

        $hasilDeteksiLists = "";
        $highestPest = "Tidak terdeteksi";
        $highestConfidence = 0;

        foreach ($pestCounts as $pest => $data) {
            $count = $data['count'];
            $confidences = $data['confidences'];
            
            // Urutkan nilai akurasi dari yang terbesar ke terkecil
            rsort($confidences);
            $topConfidenceForPest = $confidences[0];

            if ($topConfidenceForPest > $highestConfidence) {
                $highestConfidence = $topConfidenceForPest;
                $highestPest = $pest;
            }
            
            $hasilDeteksiLists .= "▸ {$pest} — {$topConfidenceForPest}% ✅\n     jumlah - {$count}\n";
        }

        // 2. Cari Penyuluh yang Menangani Desa ini (Untuk disebutkan namanya di laporan)
        $penyuluhName = 'Belum Ditugaskan';
        if ($petani->village && $petani->village->penyuluh) {
            $penyuluhName = $petani->village->penyuluh->name;
        }

        // 3. Ambil Chat ID Grup dari penyimpanan teks
        if (Storage::exists('telegram_group_id.txt')) {
            $chatId = Storage::get('telegram_group_id.txt');

            // Teks Pesan Telegram (Markdown)
            $catatan = $detection->description ? $detection->description : '-';
            $waktu = \Carbon\Carbon::parse($detection->detected_at)->translatedFormat('d M Y, H:i');

            $caption = "🌾 *PhotoBug — BPP Karang Tengah*\n"
                     . "_Laporan Deteksi Hama Baru_\n"
                     . "━━━━━━━━━━━━━━━━\n\n"
                     . "⚠️ *Terdeteksi: {$highestPest}* (Akurasi {$highestConfidence}%)\n\n"
                     . "👤 Petani      : *{$petani->name}*\n"
                     . "📍 Lokasi      : *{$namaDesa}*\n"
                     . "🧑‍🌾 Penyuluh PJ  : *{$penyuluhName}*\n"
                     . "🕐 Waktu       : *{$waktu}*\n\n"
                     . "📊 *Hasil Deteksi AI:*\n"
                     . "{$hasilDeteksiLists}\n"
                     . "💡 *Rekomendasi Penanganan AI:*\n"
                     . "_{$recommendationText}_\n\n"
                     . "📋 _Catatan: {$catatan}_\n"
                     . "━━━━━━━━━━━━━━━━\n"
                     . "🔔 Mohon *Penyuluh {$penyuluhName}* segera melakukan validasi dan memberikan rekomendasi kepada petani *{$petani->name}* melalui aplikasi PhotoBug.";

            // Menggabungkan public storage url untuk image
            $imageLocalPath = storage_path('app/public/' . $detection->image_path);

            if (file_exists($imageLocalPath)) { // Jika file gambar valid diupload ke storage lokal
                Http::attach('photo', file_get_contents($imageLocalPath), 'deteksi.jpg')
                    ->post("https://api.telegram.org/bot{$botToken}/sendPhoto", [
                        'chat_id' => trim($chatId),
                        'caption' => $caption,
                        'parse_mode' => 'Markdown'
                    ]);
            } else { // Jika entah kenapa gambar gagal dibuat, kirim versi teks-only
                Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => trim($chatId),
                    'text' => $caption,
                    'parse_mode' => 'Markdown'
                ]);
            }
        }
    }

    // Lihat detail deteksi tertentu
    public function show($id)
    {
        $detection = Detection::with('detectionResults', 'recommendations.createdBy', 'user.village.penyuluh')->findOrFail($id);
        
        // Verifikasi bahwa petani hanya bisa melihat milik sendiri
        if ($detection->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get penyuluh name
        $penyuluhName = null;
        if ($detection->user && $detection->user->village && $detection->user->village->penyuluh) {
            $penyuluhName = $detection->user->village->penyuluh->name;
        }

        // Count total pests
        $totalPests = $detection->detectionResults->count();

        return response()->json([
            'message' => 'Detail Deteksi',
            'detection' => $detection,
            'penyuluh_name' => $penyuluhName,
            'total_pests' => $totalPests
        ]);
    }

    // Status laporan untuk petani
    public function reportStatus(Request $request)
    {
        $user = $request->user();
        
        // Total laporan dikirim
        $totalSent = Detection::where('user_id', $user->id)->count();
        
        // Laporan menunggu (pending - belum diverifikasi penyuluh)
        $waiting = Detection::where('user_id', $user->id)
                            ->where('status', 'pending')
                            ->count();
        
        // Laporan diverifikasi (completed - sudah diverifikasi penyuluh)
        $verified = Detection::where('user_id', $user->id)
                            ->where('status', 'completed')
                            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_sent' => $totalSent,
                'waiting_verification' => $waiting,
                'verified' => $verified
            ]
        ]);
    }
}
