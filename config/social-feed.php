<?php

declare(strict_types=1);

return [
    'table_prefix' => getenv('SOCIAL_FEED_TABLE_PREFIX') ?: (getenv('PW_TABLE_PREFIX') ?: 'pw_'),

    'headless' => [
        'business_id' => getenv('SOCIAL_FEED_HEADLESS_BUSINESS_ID') ?: '',
    ],
];
