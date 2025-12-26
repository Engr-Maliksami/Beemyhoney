<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook_name',
        'url_to_notify',
        'event_trigger',
        'content_type',
        'auth_username',
        'auth_password',
        'custom_parameters',
        'webhook_key',
        'status',
        'last_triggered_at',
        'trigger_count',
    ];

    protected $casts = [
        'last_triggered_at' => 'datetime',
        'trigger_count' => 'integer',
    ];

    /**
     * Get decoded custom parameters
     */
    public function getCustomParametersArrayAttribute()
    {
        return json_decode($this->custom_parameters, true) ?? [];
    }

    /**
     * Trigger the webhook
     */
    public function trigger($payload = [])
    {
        if ($this->status !== 'active') {
            return false;
        }

        try {
            // Prepare headers
            $headers = [
                'Content-Type: ' . $this->content_type,
                'X-Webhook-Key: ' . $this->webhook_key,
                'X-Event-Type: ' . $this->event_trigger,
            ];

            // Add custom parameters to payload
            $customParams = json_decode($this->custom_parameters, true);
            if (!empty($customParams)) {
                foreach ($customParams as $param) {
                    if (!empty($param['name'])) {
                        $payload[$param['name']] = $param['value'];
                    }
                }
            }

            // Initialize cURL
            $ch = curl_init($this->url_to_notify);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            // Add basic auth if provided
            if ($this->auth_username && $this->auth_password) {
                curl_setopt($ch, CURLOPT_USERPWD, $this->auth_username . ':' . $this->auth_password);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Update trigger statistics
            $this->increment('trigger_count');
            $this->update(['last_triggered_at' => now()]);

            return [
                'success' => ($httpCode >= 200 && $httpCode < 300),
                'http_code' => $httpCode,
                'response' => $response,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}