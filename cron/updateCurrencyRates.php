<?php
/**
 * Updates EUR-normalized chatbot prices from ECB daily reference rates.
 *
 * Suggested cron:
 * 0 3 * * * /usr/bin/php /path/to/MainWebsite/cron/updateCurrencyRates.php
 */

require __DIR__ . '/../inc/bootstrap.php';

const ECB_DAILY_RATES_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

function fetchEcbRates(): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'header' => "User-Agent: ChatbotScoutCurrencyUpdater/1.0\r\n",
        ],
    ]);

    $xmlString = @file_get_contents(ECB_DAILY_RATES_URL, false, $context);
    if ($xmlString === false || trim($xmlString) === '') {
        throw new RuntimeException('Could not download ECB exchange-rate feed.');
    }

    $xml = @simplexml_load_string($xmlString);
    if (!$xml instanceof SimpleXMLElement) {
        throw new RuntimeException('ECB exchange-rate feed is not valid XML.');
    }

    $xml->registerXPathNamespace('gesmes', 'http://www.gesmes.org/xml/2002-08-01');
    $xml->registerXPathNamespace('e', 'http://www.ecb.int/vocabulary/2002-08-01/eurofxref');

    $dateNodes = $xml->xpath('//e:Cube[@time]');
    $rateNodes = $xml->xpath('//e:Cube[@currency][@rate]');
    if (empty($dateNodes) || empty($rateNodes)) {
        throw new RuntimeException('ECB exchange-rate feed is missing rate data.');
    }

    $rateDate = (string)$dateNodes[0]['time'];
    $rates = [
        'EUR' => [
            'rate_to_eur' => 1.0,
            'source_rate' => 1.0,
            'rate_date' => $rateDate,
        ],
    ];

    foreach ($rateNodes as $node) {
        $currency = strtoupper((string)$node['currency']);
        if (!in_array($currency, chatbotSupportedPricingCurrencies(), true)) {
            continue;
        }

        $sourceRate = (float)$node['rate'];
        if ($sourceRate <= 0) {
            continue;
        }

        $rates[$currency] = [
            'rate_to_eur' => 1 / $sourceRate,
            'source_rate' => $sourceRate,
            'rate_date' => $rateDate,
        ];
    }

    foreach (chatbotSupportedPricingCurrencies() as $currency) {
        if (empty($rates[$currency])) {
            throw new RuntimeException("ECB exchange-rate feed is missing {$currency}.");
        }
    }

    return $rates;
}

try {
    $db = Database::getInstance()->getConnection();
    $rates = fetchEcbRates();

    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO currency_rates (
            currency_code, base_currency, rate_to_eur, source_rate, rate_date, source
        ) VALUES (
            :currency_code, 'EUR', :rate_to_eur, :source_rate, :rate_date, 'ecb'
        )
        ON DUPLICATE KEY UPDATE
            base_currency = VALUES(base_currency),
            rate_to_eur = VALUES(rate_to_eur),
            source_rate = VALUES(source_rate),
            rate_date = VALUES(rate_date),
            source = VALUES(source),
            updated_at = CURRENT_TIMESTAMP
    ");

    foreach ($rates as $currency => $rate) {
        $stmt->execute([
            'currency_code' => $currency,
            'rate_to_eur' => number_format((float)$rate['rate_to_eur'], 8, '.', ''),
            'source_rate' => number_format((float)$rate['source_rate'], 8, '.', ''),
            'rate_date' => $rate['rate_date'],
        ]);
    }

    $updated = chatbotRefreshStartingPricesEur($db);
    $db->commit();

    error_log('[updateCurrencyRates] Updated rates and refreshed ' . $updated . ' chatbot EUR prices.');
    exit(0);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log('[updateCurrencyRates] Error: ' . $e->getMessage());
    exit(1);
}
