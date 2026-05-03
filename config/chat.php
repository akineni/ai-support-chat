<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Messages Per Page
    |--------------------------------------------------------------------------
    |
    | The number of messages to load per page when fetching conversation
    | history for the chat UI. Increase this if you want longer scroll
    | history loaded at once, but be mindful of payload size.
    |
    */
    'messages_per_page' => env('CHAT_MESSAGES_PER_PAGE', 50),

    /*
    |--------------------------------------------------------------------------
    | AI History Limit
    |--------------------------------------------------------------------------
    |
    | The number of past messages passed to the AI as conversation context.
    | Too few and the AI loses track of what was discussed; too many and you
    | risk hitting the model's token limit and increasing cost per request.
    | 20 is a safe default for most customer support conversations.
    |
    */
    'ai_history_limit' => env('AI_HISTORY_LIMIT', 20),

];