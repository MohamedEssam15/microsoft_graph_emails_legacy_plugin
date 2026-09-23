<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Azure AD App Registration Credentials
    |--------------------------------------------------------------------------
    |
    | Create an App Registration in Azure AD, add the "Mail.Send" Application
    | permission (not Delegated), and grant admin consent. See the README's
    | "Azure AD Setup" section for a full walkthrough.
    |
    */

    'tenant_id' => env('MS_TENANT_ID'),
    'client_id' => env('MS_CLIENT_ID'),
    'client_secret' => env('MS_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Default Sender Mailbox
    |--------------------------------------------------------------------------
    |
    | The mailbox (UPN or email address) the app will send as. Must be a
    | licensed user mailbox or a shared mailbox in your tenant — not a
    | distribution list or security group. The transport always sends as
    | this configured mailbox, regardless of any "from" address set on a
    | Mailable or in config/mail.php.
    |
    */

    'default_sender' => env('MS_SENDER_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Token Caching
    |--------------------------------------------------------------------------
    |
    | Access tokens are cached to avoid requesting a new one per email.
    | Azure AD tokens are valid for ~3600 seconds; the default TTL here is
    | kept slightly under that as a safety margin.
    |
    */

    'token_cache_ttl' => env('MS_GRAPH_TOKEN_TTL', 3500),
    'token_cache_key' => env('MS_GRAPH_TOKEN_CACHE_KEY', 'ms_graph_token'),

    /*
    |--------------------------------------------------------------------------
    | Save to Sent Items
    |--------------------------------------------------------------------------
    |
    | Whether a copy of each sent message is saved to the sender mailbox's
    | Sent Items folder.
    |
    */

    'save_to_sent_items' => env('MS_GRAPH_SAVE_TO_SENT', false),

];
