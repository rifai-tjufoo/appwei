<?php

namespace App\Services;

use App\Enums\ButtonType;
use App\Enums\MediaType;
use App\Enums\MessageType;
use App\Models\Campaign;
use App\Models\Sender;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhatsAppGatewayService
{
    public function send(Campaign $campaign, string $recipientPhone, Sender $sender): array
    {
        $payload = match ($campaign->message_type) {
            MessageType::Text => $this->buildTextPayload($campaign, $recipientPhone, $sender),
            MessageType::Button => $this->buildButtonPayload($campaign, $recipientPhone, $sender),
            MessageType::Media => $this->buildMediaPayload($campaign, $recipientPhone, $sender),
        };

        $endpoint = match ($campaign->message_type) {
            MessageType::Text => '/send-message',
            MessageType::Button => '/send-button',
            MessageType::Media => '/send-media',
        };

        $response = $this->post($endpoint, $payload);

        return $this->parseResponse($response, $endpoint, $payload);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDevices(): array
    {
        $response = $this->post('/info-device', [
            'api_key' => AppSettings::whatsappApiKey(),
        ]);

        $body = $response->json();

        if (! is_array($body) || ! ($body['status'] ?? false)) {
            return [];
        }

        $info = $body['info'] ?? [];

        return is_array($info) ? $info : [];
    }

    public function isSenderRegistered(string $senderPhone): bool
    {
        $normalized = $this->normalizePhone($senderPhone);

        foreach ($this->getDevices() as $device) {
            if ($this->normalizePhone((string) ($device['body'] ?? '')) === $normalized) {
                return true;
            }
        }

        return false;
    }

    public function isSenderConnected(string $senderPhone): bool
    {
        $normalized = $this->normalizePhone($senderPhone);

        foreach ($this->getDevices() as $device) {
            if ($this->normalizePhone((string) ($device['body'] ?? '')) !== $normalized) {
                continue;
            }

            return strtolower((string) ($device['status'] ?? '')) === 'connected';
        }

        return false;
    }

    /**
     * @return array{success: bool, message: string, body: array<string, mixed>|null}
     */
    public function testConnection(?string $senderPhone = null): array
    {
        if (! AppSettings::whatsappApiKey()) {
            return [
                'success' => false,
                'message' => 'API Key belum diatur. Isi di menu Settings.',
                'body' => null,
            ];
        }

        $devices = $this->getDevices();

        if ($devices === []) {
            return [
                'success' => false,
                'message' => 'Tidak dapat mengambil info device dari gateway. Periksa API URL dan API Key.',
                'body' => null,
            ];
        }

        if ($senderPhone) {
            if (! $this->isSenderRegistered($senderPhone)) {
                return [
                    'success' => false,
                    'message' => "Nomor sender {$senderPhone} tidak terdaftar di gateway. Buat / scan QR device di panel gateway.",
                    'body' => ['devices' => $devices],
                ];
            }

            if (! $this->isSenderConnected($senderPhone)) {
                return [
                    'success' => false,
                    'message' => "Nomor sender {$senderPhone} terdaftar tetapi status bukan Connected. Scan ulang QR di panel gateway.",
                    'body' => ['devices' => $devices],
                ];
            }
        }

        $connectedCount = collect($devices)
            ->filter(fn (array $device): bool => strtolower((string) ($device['status'] ?? '')) === 'connected')
            ->count();

        if ($connectedCount === 0) {
            return [
                'success' => false,
                'message' => 'Tidak ada device dengan status Connected. Scan QR code di panel WhatsApp gateway.',
                'body' => ['devices' => $devices],
            ];
        }

        return [
            'success' => true,
            'message' => "Gateway OK. {$connectedCount} device Connected.",
            'body' => ['devices' => $devices],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function validateCampaignSenders(Sender $sender): array
    {
        $errors = [];

        if (! AppSettings::whatsappApiKey()) {
            $errors[] = 'API Key WhatsApp belum diatur di Settings.';
        }

        if (! $this->isSenderRegistered($sender->phone)) {
            $errors[] = "Sender {$sender->phone} belum terdaftar di gateway. Daftarkan device di panel gateway (Generate QR).";
        } elseif (! $this->isSenderConnected($sender->phone)) {
            $errors[] = "Sender {$sender->phone} tidak Connected. Scan ulang QR code di panel gateway.";
        }

        return $errors;
    }

    protected function post(string $endpoint, array $payload): Response
    {
        $url = AppSettings::whatsappApiUrl().$endpoint;

        Log::debug('WhatsApp API request', [
            'url' => $url,
            'payload' => array_merge($payload, ['api_key' => '[masked]']),
        ]);

        return Http::timeout(60)
            ->acceptJson()
            ->post($url, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, status_code: int, body: array<string, mixed>, error_message: ?string}
     */
    protected function parseResponse(Response $response, string $endpoint, array $payload): array
    {
        $body = $response->json() ?? ['raw' => $response->body()];
        $apiStatus = $body['status'] ?? null;
        $apiMessage = $body['msg'] ?? $body['message'] ?? null;

        $success = $response->successful() && $apiStatus !== false;

        $errorMessage = null;
        if (! $success) {
            $errorMessage = is_string($apiMessage)
                ? $apiMessage
                : ($response->reason() ?: 'API request failed');

            if (str_contains(strtolower($errorMessage), 'connection')) {
                $errorMessage .= ' — Device WhatsApp di gateway perlu di-scan ulang / reconnect di panel provider.';
            }

            Log::warning('WhatsApp API failed', [
                'endpoint' => $endpoint,
                'status_code' => $response->status(),
                'response' => $body,
                'sender' => $payload['sender'] ?? null,
                'number' => $payload['number'] ?? null,
            ]);
        }

        return [
            'success' => $success,
            'status_code' => $response->status(),
            'body' => $body,
            'error_message' => $errorMessage,
        ];
    }

    protected function basePayload(string $recipientPhone, Sender $sender): array
    {
        return [
            'api_key' => AppSettings::whatsappApiKey(),
            'sender' => $this->normalizePhone($sender->phone),
            'number' => $this->normalizePhone($recipientPhone),
        ];
    }

    protected function buildTextPayload(Campaign $campaign, string $recipientPhone, Sender $sender): array
    {
        return array_merge($this->basePayload($recipientPhone, $sender), [
            'message' => $campaign->message,
        ]);
    }

    protected function buildButtonPayload(Campaign $campaign, string $recipientPhone, Sender $sender): array
    {
        $payload = array_merge($this->basePayload($recipientPhone, $sender), [
            'message' => $campaign->message,
            'button' => $this->formatButtons($campaign->buttons ?? []),
        ]);

        if ($campaign->footer) {
            $payload['footer'] = $campaign->footer;
        }

        $imageUrl = $campaign->button_image_url;
        if (! $imageUrl && $campaign->media_path) {
            $imageUrl = $this->publicMediaUrl($campaign->media_path);
        }

        if ($imageUrl) {
            $payload['url'] = $imageUrl;
        }

        return $payload;
    }

    protected function buildMediaPayload(Campaign $campaign, string $recipientPhone, Sender $sender): array
    {
        return array_merge($this->basePayload($recipientPhone, $sender), [
            'media_type' => $campaign->media_type instanceof MediaType
                ? $campaign->media_type->value
                : ($campaign->media_type ?? MediaType::Image->value),
            'url' => $this->publicMediaUrl($campaign->media_path),
            'caption' => $campaign->caption,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $buttons
     * @return array<int, array<string, mixed>>
     */
    protected function formatButtons(array $buttons): array
    {
        $formatted = [];

        foreach (array_slice($buttons, 0, 5) as $button) {
            $type = ButtonType::tryFrom($button['type'] ?? '') ?? ButtonType::Reply;

            $item = [
                'type' => $type->value,
                'displayText' => $button['displayText'] ?? $button['display_text'] ?? '',
            ];

            match ($type) {
                ButtonType::Call => $item['phoneNumber'] = $button['phoneNumber'] ?? $button['phone_number'] ?? '',
                ButtonType::Url => $item['url'] = $button['url'] ?? '',
                ButtonType::Copy => $item['copyCode'] = $button['copyCode'] ?? $button['copy_code'] ?? '',
                default => null,
            };

            $formatted[] = $item;
        }

        return $formatted;
    }

    public function publicMediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    protected function normalizePhone(string $phone): string
    {
        $normalized = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($normalized, '0')) {
            $normalized = '62'.substr($normalized, 1);
        }

        return $normalized;
    }
}
