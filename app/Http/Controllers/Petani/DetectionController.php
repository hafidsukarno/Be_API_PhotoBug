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
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric'
        ]);

        $user = $request->user();

        // Simpan gambar ke storage
        $imagePath = $request->file('image')->store('detections', 'public');

        // Buat record deteksi
        $location = null;
        if ($request->latitude && $request->longitude) {
            $location = $request->latitude . ',' . $request->longitude;
        }

        $detection = Detection::create([
            'user_id' => $user->id,
            'image_path' => $imagePath,
            'detected_at' => now(),
            'status' => 'pending',
            'description' => $request->description,
            'location' => $location,
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

        // Kamus rekomendasi dummy berdasarkan jenis hama
        $dummyRecommendations = [
            'Wereng Cokelat' => 'Gunakan insektisida berbahan aktif buprofezin atau pymetrozin. Jaga jarak tanam jangan terlalu rapat dan gunakan varietas padi yang tahan wereng cokelat.',
            'Wereng Hijau' => 'Segera lakukan penyemprotan insektisida berbahan aktif imidakloprid atau BPMC. Bersihkan gulma di sekitar area persawahan yang bisa menjadi sarang inang alternatif.',
        ];

        // Buat rekomendasi awal dari AI
        $topResult = $aiResults[0];
        
        if ($topResult['confidence'] > 0) {
            $pestName = $topResult['pest_name'];
            $aiAdvice = $dummyRecommendations[$pestName] ?? 'Silakan konsultasikan dengan penyuluh untuk penanganan lebih lanjut.';
            
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
        try {
            $this->sendTelegramNotification($detection, $aiResults, $recommendationText);
        } catch (\Exception $e) {
            Log::error('Gagal mengirim Telegram Webhook: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Deteksi berhasil disimpan',
            'detection' => $detection->load('detectionResults', 'recommendations')
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
        $no = 1;
        foreach ($pestCounts as $pest => $data) {
            $count = $data['count'];
            $confidences = $data['confidences'];
            
            // Urutkan nilai akurasi dari yang terbesar ke terkecil
            rsort($confidences);
            
            // Format array akurasi menjadi string yang rapi dengan simbol %
            $confString = implode(', ', array_map(function($val) { return $val . '%'; }, $confidences));
            
            $hasilDeteksiLists .= "{$no}. *{$pest}* ({$count} Temuan)\n     Akurasi: {$confString}\n";
            $no++;
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
            $catatan = $detection->description ? $detection->description : 'Tidak ada catatan khusus.';
            $caption = "� *LAPORAN DETEKSI HAMA BPP KARANG TENGAH*\n"
                     . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                     . "📋 *DATA PELAPOR*\n"
                     . "• NAMA PETANI : {$petani->name}\n"
                     . "• LOKASI DESA : {$namaDesa}\n"
                     . "• PENYULUH PJ : {$penyuluhName}\n"
                     . "• WAKTU FOTO  : {$detection->detected_at}\n"
                     . "• CATATAN     : {$catatan}\n\n"
                     . "🔬 *HASIL IDENTIFIKASI AI*\n"
                     . "{$hasilDeteksiLists}\n"
                     . "💡 *REKOMENDASI SEMENTARA AI*\n"
                     . "{$recommendationText}\n"
                     . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                     . "Mohon kepada Penyuluh PJ untuk segera melakukan validasi dan memberikan rekomendasi tindakan kepada *{$petani->name}* melalui Aplikasi PhotoBug.";

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
        $detection = Detection::with('detectionResults', 'recommendations.createdBy')->findOrFail($id);
        
        // Verifikasi bahwa petani hanya bisa melihat milik sendiri
        if ($detection->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($detection);
    }
}
