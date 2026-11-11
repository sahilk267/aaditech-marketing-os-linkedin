<?php

return [
    'linkedin'=>[
        'client_id'=>env('LINKEDIN_CLIENT_ID'),
        'client_secret'=>env('LINKEDIN_CLIENT_SECRET'),
        'redirect_uri'=>env('LINKEDIN_REDIRECT_URI'),
        'version'=>env('LINKEDIN_API_VERSION'),
    ],
    'telegram'=>['token'=>env('TELEGRAM_BOT_TOKEN'),'chat_id'=>env('TELEGRAM_CHAT_ID')],
    'alerts'=>['queue_backlog_threshold'=>(int)env('AADI_ALERT_BACKLOG_THRESHOLD',50)],
];
