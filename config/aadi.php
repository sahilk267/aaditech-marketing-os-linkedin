<?php

return [
    'linkedin'=>[
        'client_id'=>env('LINKEDIN_CLIENT_ID'),
        'client_secret'=>env('LINKEDIN_CLIENT_SECRET'),
        'redirect_uri'=>env('LINKEDIN_REDIRECT_URI'),
        'version'=>env('LINKEDIN_API_VERSION'),
    ],
    'meta'=>[
        'app_id'=>env('META_APP_ID'),
        'app_secret'=>env('META_APP_SECRET'),
        'redirect_uri'=>env('META_REDIRECT_URI'),
        'graph_version'=>env('META_GRAPH_VERSION','v26.0'),
    ],
    'telegram'=>['token'=>env('TELEGRAM_BOT_TOKEN'),'chat_id'=>env('TELEGRAM_CHAT_ID')],
    'alerts'=>['queue_backlog_threshold'=>(int)env('AADI_ALERT_BACKLOG_THRESHOLD',50)],
];
