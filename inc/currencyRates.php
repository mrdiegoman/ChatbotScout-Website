<?php
/**
 * Currency conversion helpers for approximate chatbot price filtering.
 *
 * Chatbot cards keep their original listed currency. Filtering/sorting uses a
 * denormalized EUR value so directory queries stay fast and do not call any
 * external service during user interactions.
 */

const CHATBOT_PRICE_BASE_CURRENCY = 'EUR';

function chatbotSupportedPricingCurrencies(): array
{
    return ['EUR', 'USD', 'GBP'];
}

function chatbotNormalizeCurrencyCode(?string $currency): string
{
    $code = strtoupper(trim((string)$currency));
    return in_array($code, chatbotSupportedPricingCurrencies(), true)
        ? $code
        : CHATBOT_PRICE_BASE_CURRENCY;
}

function chatbotCurrencyRateToEur(PDO $db, ?string $currency): float
{
    $code = chatbotNormalizeCurrencyCode($currency);
    if ($code === CHATBOT_PRICE_BASE_CURRENCY) {
        return 1.0;
    }

    static $rates = [];
    if (array_key_exists($code, $rates)) {
        return $rates[$code];
    }

    $stmt = $db->prepare("
        SELECT rate_to_eur
        FROM currency_rates
        WHERE currency_code = :currency_code
        LIMIT 1
    ");
    $stmt->execute(['currency_code' => $code]);
    $rate = $stmt->fetchColumn();

    $rates[$code] = is_numeric($rate) && (float)$rate > 0
        ? (float)$rate
        : 1.0;

    return $rates[$code];
}

function chatbotPriceToEur(PDO $db, $price, ?string $currency): string
{
    $amount = is_numeric($price) ? (float)$price : 0.0;
    if ($amount < 0) {
        $amount = 0.0;
    }

    $converted = $amount * chatbotCurrencyRateToEur($db, $currency);
    return number_format(round($converted, 2), 2, '.', '');
}

function chatbotRefreshStartingPricesEur(PDO $db): int
{
    $stmt = $db->prepare("
        UPDATE chatbots c
        LEFT JOIN currency_rates cr
          ON cr.currency_code = c.pricing_currency
        SET c.starting_price_eur = ROUND(c.starting_price * COALESCE(cr.rate_to_eur, 1), 2)
    ");
    $stmt->execute();

    return $stmt->rowCount();
}
