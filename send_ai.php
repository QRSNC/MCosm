<?php
/* AI filtre modülü: include edildiğinde filter_query() ve filter_videos() sağlar */

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
    $model  = env_get('OPENROUTER_MODEL') ?: 'x-ai/grok-3-mini';
    if (!$apiKey) {
        error_log("OpenRouter API anahtarı .env dosyasında bulunamadı.");
        return ['banned' => false, 'query' => $query];
    }

    $prompt = "Sorguyu incele: \"$query\". "
            . "Eğer sorgu savaş, siyaset, ırkçılık, toksik tartışmalar, "
            . "aşırı dopamin tetikleyici (beyin çürüten/brainrot) içerikler, anlamsız dramalar, "
            . "veya provokatif tık tuzağı (clickbait) özellikleri barındırıyorsa, veya bunları ima ediyorsa "
            . "sadece BANNED yaz. Normal, faydalı veya zararsız bir sorgu ise sadece OK yaz.";

    $payload = [
        'model' => $model,
        'messages' => [
            ['role'=>'system','content'=>'Yalnızca BANNED veya OK.'],
            ['role'=>'user','content'=>$prompt]
        ],
        'temperature'=>0.1,
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
        CURLOPT_TIMEOUT => 15
    ]);
    $resp = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log("cURL Hatası (filter_query): " . curl_error($ch));
        curl_close($ch);
        return ['banned' => false, 'query' => $query];
    }
    curl_close($ch);

    $data = json_decode($resp, true);
    $reply_content = $data['choices'][0]['message']['content'] ?? '';
    $reply = strtoupper(trim($reply_content));

    if (str_contains($reply, 'BANNED')) {
        return ['banned' => true, 'query' => $query];
    }

    return ['banned' => false, 'query' => $query];
}

/**
 * YouTube API'den dönen videoları topluca Vision AI ile değerlendirir.
 * $videos formatı: [['id'=>'...', 'title'=>'...', 'thumb'=>'...'], ...]
 * DÖNÜŞ: Sadece güvenli ve uygun bulunan videoların ID'lerini içeren dizi.
 */
function filter_videos(array $videos): array {
    if (empty($videos)) return [];

    $apiKey = env_get('OPENROUTER_API_KEY');
    // OpenRouter'da vision destekleyen güçlü ve hızlı bir model (ör. google/gemini-2.5-flash veya anthropic/claude-3-haiku)
    // Eğer env'de yoksa varsayılan olarak vision destekli bir model seçiyoruz.
    $model  = env_get('OPENROUTER_VISION_MODEL') ?: 'google/gemini-2.5-flash';

    if (!$apiKey) {
        error_log("OpenRouter API anahtarı yok, filter_videos atlanıyor.");
        return array_column($videos, 'id'); // Tümünü geçir
    }

    $contentArray = [];
    $contentArray[] = [
        "type" => "text",
        "text" => "Aşağıdaki YouTube videolarını, başlıkları ve küçük resimlerine göre analiz et.\n" .
                  "Şu tarz içerikleri GÜVENLİ DEĞİL (BANNED) olarak işaretle: \n" .
                  "- Clickbait (Tık tuzağı, abartılı oklar, kırmızı daireler, aşırı şaşıran yüzler)\n" .
                  "- Brainrot / Aşırı dopamin tetikleyici (örneğin saçma sapan çocuk içerikleri, anlamsız spam videolar)\n" .
                  "- Toksik, siyasi, savaş veya drama içerikleri.\n\n" .
                  "Her videonun ID'si ve başlığı verilmiştir, bazılarına görsel de eklenmiştir. \n" .
                  "Sadece onayladığın (güvenli, eğitici, sanatsal veya zararsız eğlence) videoların ID'lerini aralarında virgül olacak şekilde tek bir satırda döndür. BAŞKA HİÇBİR ŞEY YAZMA."
    ];

    foreach ($videos as $v) {
        $text = "Video ID: {$v['id']}\nBaşlık: {$v['title']}\n";
        $contentArray[] = ["type" => "text", "text" => $text];

        // Thumbnail URL'sini görsel objesi olarak ekle
        if (!empty($v['thumb'])) {
            $contentArray[] = [
                "type" => "image_url",
                "image_url" => ["url" => $v['thumb']]
            ];
        }
    }

    $payload = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'user',
                'content' => $contentArray
            ]
        ],
        'temperature' => 0.1,
        'max_tokens' => 200
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
        CURLOPT_TIMEOUT => 25
    ]);

    $resp = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log("cURL Hatası (filter_videos): " . curl_error($ch));
        curl_close($ch);
        return array_column($videos, 'id'); // Hata durumunda fail-open
    }
    curl_close($ch);

    $data = json_decode($resp, true);
    $reply_content = $data['choices'][0]['message']['content'] ?? '';

    // Virgüllerle ayrılmış ID listesi gelmesini bekliyoruz
    // Tüm kelimeleri ayır, ID uzunluğu (11 karakter) olanları topla
    preg_match_all('/[a-zA-Z0-9_-]{11}/', $reply_content, $matches);

    if (empty($matches[0])) {
         // Model hiç ID döndürmediyse veya beklenti dışı bir cevap verdiyse
         // Eğer cevap BANNED vb ise hepsi elenmiş olabilir.
         return [];
    }

    return array_unique($matches[0]);
}

/* Tarayıcıdan direkt çağırılırsa JSON döndür */
if (isset($_GET['query']) && !debug_backtrace()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(filter_query($_GET['query']));
    die;
}
