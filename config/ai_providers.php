<?php
/**
 * Multi-AI Provider System
 * Supports: Gemini (free), Groq (free), Ollama (free/local), OpenAI (paid), Claude (paid)
 */

class AIProvider {

    public static function getProviders(): array {
        return [
            // Free Providers
            'gemini' => [
                'name'    => 'Google Gemini',
                'badge'   => 'مجاني',
                'badge_en'=> 'Free',
                'icon'    => '✨',
                'models'  => [
                    'gemini-2.0-flash'       => 'Gemini 2.0 Flash (Fast)',
                    'gemini-1.5-flash'       => 'Gemini 1.5 Flash',
                    'gemini-1.5-flash-8b'    => 'Gemini 1.5 Flash-8B (Fastest)',
                ],
                'vision'  => true,
                'key_field' => 'gemini_key',
                'free'    => true,
                'note'    => 'مفتاح مجاني من aistudio.google.com',
            ],
            'groq' => [
                'name'    => 'Groq',
                'badge'   => 'مجاني',
                'badge_en'=> 'Free',
                'icon'    => '⚡',
                'models'  => [
                    'llama-3.3-70b-versatile'    => 'Llama 3.3 70B (Best Free)',
                    'llama-3.1-8b-instant'       => 'Llama 3.1 8B (Fastest)',
                    'mixtral-8x7b-32768'         => 'Mixtral 8x7B',
                    'gemma2-9b-it'               => 'Gemma 2 9B',
                ],
                'vision'  => false,
                'key_field' => 'groq_key',
                'free'    => true,
                'note'    => 'مفتاح مجاني من console.groq.com',
            ],
            'ollama' => [
                'name'    => 'Ollama (محلي)',
                'badge'   => 'مجاني تماماً',
                'badge_en'=> 'Fully Free',
                'icon'    => '🖥️',
                'models'  => [
                    'llama3'       => 'Llama 3 (8B)',
                    'llama3:70b'   => 'Llama 3 (70B)',
                    'mistral'      => 'Mistral 7B',
                    'gemma2'       => 'Gemma 2',
                    'llava'        => 'LLaVA (Vision)',
                    'phi3'         => 'Phi-3',
                ],
                'vision'  => true, // only with llava
                'key_field' => null,
                'free'    => true,
                'note'    => 'يحتاج Ollama مثبت على نفس السيرفر',
            ],
            // Paid Providers
            'openai' => [
                'name'    => 'OpenAI',
                'badge'   => 'مدفوع',
                'badge_en'=> 'Paid',
                'icon'    => '🤖',
                'models'  => [
                    'gpt-4o'       => 'GPT-4o (Best)',
                    'gpt-4o-mini'  => 'GPT-4o Mini (Affordable)',
                    'gpt-4-turbo'  => 'GPT-4 Turbo',
                    'gpt-3.5-turbo'=> 'GPT-3.5 Turbo (Cheap)',
                ],
                'vision'  => true,
                'key_field' => 'openai_key',
                'free'    => false,
                'note'    => 'مفتاح من platform.openai.com',
            ],
            'claude' => [
                'name'    => 'Anthropic Claude',
                'badge'   => 'مدفوع',
                'badge_en'=> 'Paid',
                'icon'    => '🧠',
                'models'  => [
                    'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Best)',
                    'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku (Fast)',
                    'claude-3-opus-20240229'     => 'Claude 3 Opus',
                ],
                'vision'  => true,
                'key_field' => 'claude_key',
                'free'    => false,
                'note'    => 'مفتاح من console.anthropic.com',
            ],
            'gemini_paid' => [
                'name'    => 'Google Gemini Pro',
                'badge'   => 'مدفوع',
                'badge_en'=> 'Paid',
                'icon'    => '💎',
                'models'  => [
                    'gemini-1.5-pro'       => 'Gemini 1.5 Pro (Best)',
                    'gemini-2.0-flash-exp' => 'Gemini 2.0 Flash Exp',
                ],
                'vision'  => true,
                'key_field' => 'gemini_key',
                'free'    => false,
                'note'    => 'نفس مفتاح Gemini لكن حصة مدفوعة',
            ],
        ];
    }

    /**
     * Call AI with text prompt (+ optional image for vision models)
     */
    public static function call(
        string $prompt,
        ?string $imageBase64 = null,
        ?string $mimeType = null,
        ?Database $db = null
    ): ?string {
        if (!$db) $db = Database::getInstance();

        $provider = $db->getSetting('ai_provider', 'gemini');
        $model    = $db->getSetting('ai_model', 'gemini-2.0-flash');

        // If image is provided but provider doesn't support vision, fallback
        if ($imageBase64) {
            $providers = self::getProviders();
            $pInfo = $providers[$provider] ?? null;
            if ($pInfo && !$pInfo['vision']) {
                // Try gemini as fallback for vision
                return self::callGemini($prompt, $imageBase64, $mimeType, $db->getSetting('gemini_key'));
            }
        }

        return match($provider) {
            'gemini', 'gemini_paid' => self::callGemini($prompt, $imageBase64, $mimeType, $db->getSetting('gemini_key'), $model),
            'groq'                  => self::callGroq($prompt, $db->getSetting('groq_key'), $model),
            'ollama'                => self::callOllama($prompt, $imageBase64, $db->getSetting('ollama_url', 'http://localhost:11434'), $model),
            'openai'                => self::callOpenAI($prompt, $imageBase64, $mimeType, $db->getSetting('openai_key'), $model),
            'claude'                => self::callClaude($prompt, $imageBase64, $mimeType, $db->getSetting('claude_key'), $model),
            default                 => self::callGemini($prompt, $imageBase64, $mimeType, $db->getSetting('gemini_key')),
        };
    }

    // ── Gemini ──────────────────────────────────────────────────────────
    private static function callGemini(string $prompt, ?string $img, ?string $mime, string $key, string $model = 'gemini-2.0-flash'): ?string {
        if (!$key) return null;

        $parts = [['text' => $prompt]];
        if ($img && $mime) {
            $b64 = strpos($img, 'base64,') !== false ? explode('base64,', $img)[1] : $img;
            $parts[] = ['inline_data' => ['mime_type' => $mime, 'data' => $b64]];
        }

        $payload = json_encode([
            'contents'         => [['parts' => $parts]],
            'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 2048],
        ]);

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
        $res = self::httpPost($url, $payload, ['Content-Type: application/json']);
        if (!$res) return null;
        $data = json_decode($res, true);
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }

    // ── Groq ─────────────────────────────────────────────────────────────
    private static function callGroq(string $prompt, string $key, string $model = 'llama-3.3-70b-versatile'): ?string {
        if (!$key) return null;

        $payload = json_encode([
            'model'    => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 2048,
            'temperature' => 0.7,
        ]);

        $res = self::httpPost(
            'https://api.groq.com/openai/v1/chat/completions',
            $payload,
            ['Content-Type: application/json', "Authorization: Bearer {$key}"]
        );
        if (!$res) return null;
        $data = json_decode($res, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    // ── Ollama ───────────────────────────────────────────────────────────
    private static function callOllama(string $prompt, ?string $img, string $baseUrl, string $model = 'llama3'): ?string {
        $payload = ['model' => $model, 'prompt' => $prompt, 'stream' => false];
        if ($img) {
            $b64 = strpos($img, 'base64,') !== false ? explode('base64,', $img)[1] : $img;
            $payload['images'] = [$b64];
        }

        $res = self::httpPost(
            rtrim($baseUrl, '/') . '/api/generate',
            json_encode($payload),
            ['Content-Type: application/json']
        );
        if (!$res) return null;
        $data = json_decode($res, true);
        return $data['response'] ?? null;
    }

    // ── OpenAI ───────────────────────────────────────────────────────────
    private static function callOpenAI(string $prompt, ?string $img, ?string $mime, string $key, string $model = 'gpt-4o'): ?string {
        if (!$key) return null;

        $content = [['type' => 'text', 'text' => $prompt]];
        if ($img && $mime) {
            $b64 = strpos($img, 'base64,') !== false ? $img : "data:{$mime};base64,{$img}";
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => $b64]];
        }

        $payload = json_encode([
            'model'      => $model,
            'messages'   => [['role' => 'user', 'content' => $content]],
            'max_tokens' => 2048,
        ]);

        $res = self::httpPost(
            'https://api.openai.com/v1/chat/completions',
            $payload,
            ['Content-Type: application/json', "Authorization: Bearer {$key}"]
        );
        if (!$res) return null;
        $data = json_decode($res, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    // ── Claude ───────────────────────────────────────────────────────────
    private static function callClaude(string $prompt, ?string $img, ?string $mime, string $key, string $model = 'claude-3-5-sonnet-20241022'): ?string {
        if (!$key) return null;

        $content = [['type' => 'text', 'text' => $prompt]];
        if ($img && $mime) {
            $b64 = strpos($img, 'base64,') !== false ? explode('base64,', $img)[1] : $img;
            $content = [
                ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $b64]],
                ['type' => 'text',  'text' => $prompt],
            ];
        }

        $payload = json_encode([
            'model'      => $model,
            'max_tokens' => 2048,
            'messages'   => [['role' => 'user', 'content' => $content]],
        ]);

        $res = self::httpPost(
            'https://api.anthropic.com/v1/messages',
            $payload,
            [
                'Content-Type: application/json',
                "x-api-key: {$key}",
                'anthropic-version: 2023-06-01',
            ]
        );
        if (!$res) return null;
        $data = json_decode($res, true);
        return $data['content'][0]['text'] ?? null;
    }

    // ── HTTP helper ──────────────────────────────────────────────────────
    private static function httpPost(string $url, string $payload, array $headers): ?string {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($code >= 200 && $code < 300) ? $res : null;
    }

    /**
     * Test if a provider is reachable with current settings
     */
    public static function test(Database $db): array {
        $provider = $db->getSetting('ai_provider', 'gemini');
        $model    = $db->getSetting('ai_model', 'gemini-2.0-flash');
        $prompt   = 'Say "OK" in one word only.';

        $result = self::call($prompt, null, null, $db);

        return [
            'provider' => $provider,
            'model'    => $model,
            'success'  => $result !== null,
            'response' => $result,
        ];
    }
}
