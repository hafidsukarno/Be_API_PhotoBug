<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TelegramWebhookController extends Controller
{
    private $botToken = '8434442028:AAH6CuTkZOxR4bQn3jCNiyv78FDLkBqNvRA';

    public function handle(Request $request)
    {
        Log::info('Telegram Webhook: ', $request->all());

        // Cek jika bot dimasukkan ke dalam grup (event member baru/pembuatan grup)
        $message = $request->input('message');
        $myChatMember = $request->input('my_chat_member');

        // Otomatis aktif saat bot dimasukkan ke grup
        if (isset($message['new_chat_members']) || isset($message['group_chat_created']) || $myChatMember) {
            $chatId = $message['chat']['id'] ?? $myChatMember['chat']['id'];
            $namaGrup = $message['chat']['title'] ?? $myChatMember['chat']['title'] ?? 'Sistem Laporan';
            
            Storage::put('telegram_group_id.txt', $chatId);
            
            $this->sendMessage(
                $chatId, 
                "✅ *Bot Otomatis Aktif!* \n\nSaya berhasil bergabung di grup *{$namaGrup}*. \nSemua pelaporan hama terbaru dari petani akan otomatis masuk ke grup ini untuk dipantau oleh para penyuluh!"
            );
            return response()->json(['status' => 'ok']);
        }

        if (!$message || !isset($message['text'])) {
            return response()->json(['status' => 'ok']);
        }

        $chatId = $message['chat']['id'];
        $text = $message['text'];

        // Jika ada yang mengetik /start atau /setgrup di dalam grup (maupun DM) sebagai cadangan
        if (str_starts_with($text, '/start') || str_starts_with($text, '/setgrup')) {
            
            // Simpan Chat ID ke sebuah file
            Storage::put('telegram_group_id.txt', $chatId);

            $jenisChat = $message['chat']['type'];
            $namaGrup = $message['chat']['title'] ?? 'Sistem Laporan';

            if ($jenisChat === 'group' || $jenisChat === 'supergroup') {
                $this->sendMessage(
                    $chatId, 
                    "✅ *Bot Berhasil Diaktifkan di Grup!* \n\nGrup *{$namaGrup}* sekarang terhubung dengan Sistem PhotoBug. Seluruh pelaporan hama dari petani akan secara otomatis diteruskan langsung ke grup ini."
                );
            } else {
                $this->sendMessage(
                    $chatId, 
                    "✅ *Bot Berhasil Terhubung!*\n\nMulai sekarang, semua laporan hama dari petani akan dikirimkan ke chat ini."
                );
            }
        } 
        
        return response()->json(['status' => 'ok']);
    }

    private function sendMessage($chatId, $text)
    {
        Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ]);
    }
}
