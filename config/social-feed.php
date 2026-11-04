<?php

declare(strict_types=1);

return [
    'table_prefix' => getenv('SOCIAL_FEED_TABLE_PREFIX') ?: (getenv('PW_TABLE_PREFIX') ?: 'pw_'),

    'headless' => [
        'business_id' => getenv('SOCIAL_FEED_HEADLESS_BUSINESS_ID') ?: '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Groq — sinh slug AI cho feed post
    |--------------------------------------------------------------------------
    | Worker `social-feed:generate-slugs` quét post slug_generated = false
    | (order by retries ASC) và gọi Groq để sinh slug.
    |
    | Không có api_key thì tính năng tắt: post giữ slug UUID, không gọi API.
    | Tham số Groq không hỗ trợ (logprobs / logit_bias / top_logprobs /
    | messages[].name) không được gửi — xem Services/GroqClient.php.
    */
    'groq' => [
        'api_key' => getenv('GROQ_API_KEY') ?: '',
        'model' => getenv('GROQ_MODEL') ?: '',
        'base_url' => getenv('GROQ_BASE_URL') ?: '',
        'timeout' => (int) (getenv('GROQ_TIMEOUT') ?: 30),
    ],
];
