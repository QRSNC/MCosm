<?php
/* AI filtre modülü: include edildiğinde filter_query() sağlar */

function env_get(string $key): ?string {
    $file = __DIR__.'/.env';
    if (!is_readable($file)) return null;
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
        $line = ltrim($line, "\u{FEFF}");
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$k,$v] = array_map('trim', explode('=', $line, 2));
        if ($k === $key) return $v;
    }
    return null;
}

/**
 * Sorguyu AI modeliyle değerlendirir.
 * DÖNÜŞ: ['banned'=>bool,'query'=>string]
 */
function filter_query(string $query): array {
    $query = trim($query);
    if ($query === '') {
        return ['banned' => false, 'query' => ''];
    }

    $apiKey = env_get('OPENROUTER_API_KEY');
    // Model adını .env dosyasından al, yoksa varsayılanı kullan
    $model  = env_get('OPENROUTER_MODEL') ?: 'x-ai/grok-3-mini'; 
    if (!$apiKey) {
        // API anahtarı yoksa filtrelemeyi atla (fail-open)
        error_log("OpenRouter API anahtarı .env dosyasında bulunamadı.");
        return ['banned' => false, 'query' => $query];
    }

    $prompt = "Sorguyu incele: \"$query\". Eğer savaş, ülkeler arası çekişme, provokatif siyasi içerik, "
            . "barışı bozan söylem veya ülkeleri yarıştıran, ülkeler arası gelişmişlik, en iyi kıyaslamaları, bili ve tekonolojilerini sorgulayan veya bunlardan herhangi birini ima edeceğini düşündüğün veya olasılık verdiğin bir yapı içeriyorsa bile sadece BANNED yaz. "
            . "Normal ise sadece OK yaz.";

    $payload = [
        'model' => $model,
        'messages' => [
            ['role'=>'system','content'=>'Yalnızca BANNED veya OK.'],
            ['role'=>'user','content'=>$prompt]
        ],
        'temperature'=>0.2,
        'max_tokens'=>5
    ];

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $apiKey",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 10
    ]);
    $resp = curl_exec($ch);

    // DÜZELTME 1: cURL hatasını kontrol et
    if (curl_errno($ch)) {
        // Hata varsa logla ve filtreyi atla
        error_log("cURL Hatası: " . curl_error($ch));
        curl_close($ch);
        return ['banned' => false, 'query' => $query];
    }
    curl_close($ch);

    $data = json_decode($resp, true);
    $reply_content = $data['choices'][0]['message']['content'] ?? '';
    $reply = strtoupper(trim($reply_content));

    // DÜZELTME 2: Cevabı daha esnek kontrol et (str_contains ile)
    // Model bazen "BANNED." gibi ek karakterler gönderebilir.
    if (str_contains($reply, 'BANNED')) {
        return ['banned' => true, 'query' => $query];
    }
    
    return ['banned' => false, 'query' => $query];
}

/* Tarayıcıdan direkt çağırılırsa JSON döndür */
if (isset($_GET['query']) && !debug_backtrace()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(filter_query($_GET['query']));
    exit;
}