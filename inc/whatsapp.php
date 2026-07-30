<?php
declare(strict_types=1);

/**
 * WhatsApp alerts via CallMeBot (https://www.callmebot.com/blog/free-api-whatsapp-messages/) --
 * a free API meant for exactly this "notify myself" use case, no business
 * verification needed. Disabled until both constants below are filled in.
 *
 * To activate: from the phone that should receive alerts, save +34 644 59 71 07
 * as a contact, send it the WhatsApp message "I allow callmebot to send me
 * messages" verbatim, then use the API key it replies with.
 */
const KL_WHATSAPP_PHONE = ''; // international format, digits only, e.g. '972501234567'
const KL_WHATSAPP_APIKEY = ''; // the key CallMeBot replies with after activation

function kl_whatsapp_enabled(): bool
{
    return KL_WHATSAPP_PHONE !== '' && KL_WHATSAPP_APIKEY !== '';
}

function kl_send_whatsapp_alert(string $message): bool
{
    if (!kl_whatsapp_enabled()) {
        return false;
    }

    $url = 'https://api.callmebot.com/whatsapp.php?' . http_build_query([
        'phone' => KL_WHATSAPP_PHONE,
        'text' => $message,
        'apikey' => KL_WHATSAPP_APIKEY,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 200;
}
