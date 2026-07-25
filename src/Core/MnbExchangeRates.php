<?php

namespace Cloudexus\Core;

use SimpleXMLElement;
use Throwable;

/**
 * MNB közép-árfolyamok lekérése.
 *
 * Az MNB egy SOAP szolgáltatást ad (arfolyamok.asmx), amire itt sima cURL-lel
 * küldünk borítékot: a php-soap kiterjesztés nincs bekapcsolva, viszont a
 * válasz elég egyszerű ahhoz, hogy SimpleXML-lel feldolgozzuk.
 *
 * A válaszban lévő XML escape-elve utazik, a benne lévő árfolyamok pedig
 * mindig forintban értendők, egységgel és tizedesvesszővel:
 *
 *     <Rate unit="1" curr="EUR">395,32</Rate>
 *     <Rate unit="100" curr="JPY">255,60</Rate>
 */
class MnbExchangeRates
{
    /**
     * A szolgáltatás a SOAP POST-ot jelenleg csak sima HTTP-n fogadja: a HTTPS
     * végpont a WSDL-t kiadja, de a POST-ra 404-et ad. Ezért előbb HTTPS-sel
     * próbálkozunk (ha az MNB egyszer javítja, magától azt fogja használni), és
     * csak utána esünk vissza HTTP-re. Nyilvános, közzétett árfolyamadat, nincs
     * benne titok, hitelesítés sincs — de a váltószámokat csak tájékoztatásra
     * használjuk, tárolt összeget nem írnak át.
     */
    private const ENDPOINTS = [
        'https://www.mnb.hu/arfolyamok.asmx',
        'http://www.mnb.hu/arfolyamok.asmx',
    ];

    /** A WSDL szerinti (WCF-stílusú) művelet, és a kérés eleme tempuri.org névtérben. */
    private const SOAP_ACTION = 'http://www.mnb.hu/webservices/MNBArfolyamServiceSoap/GetCurrentExchangeRates';
    private const REQUEST_NS = 'http://tempuri.org/';
    private const USER_AGENT = 'Cloudexus currency sync';
    private const TIMEOUT = 15;

    /**
     * Az aktuális közép-árfolyamok. Hiba esetén null, egyébként:
     *   ['date' => 'YYYY-MM-DD', 'rates' => [pénznemkód => 1 egység ennyi forint]]
     * A rates-ben a HUF mindig 1.0-val szerepel.
     *
     * @return array{date: string, rates: array<string, float>}|null
     */
    public static function fetch(): ?array
    {
        $xml = self::request();
        if ($xml === null) {
            return null;
        }

        return self::parse($xml);
    }

    /** A nyers SOAP válasz az első működő végpontról. */
    private static function request(): ?string
    {
        $envelope = '<?xml version="1.0" encoding="utf-8"?>'
            . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body><GetCurrentExchangeRates xmlns="' . self::REQUEST_NS . '" /></soap:Body>'
            . '</soap:Envelope>';

        $failures = [];

        foreach (self::ENDPOINTS as $endpoint) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $envelope,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: text/xml; charset=utf-8',
                    'SOAPAction: "' . self::SOAP_ACTION . '"',
                ],
                CURLOPT_USERAGENT => self::USER_AGENT,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => self::TIMEOUT,
            ]);
            $response = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response !== false && $status === 200) {
                return $response;
            }

            $failures[] = $endpoint . ': ' . ($response === false ? $curlError : 'HTTP ' . $status);
        }

        Logger::error('MNB árfolyam lekérés sikertelen — ' . implode('; ', $failures));

        return null;
    }

    /**
     * @return array{date: string, rates: array<string, float>}|null
     */
    private static function parse(string $soapResponse): ?array
    {
        try {
            $previous = libxml_use_internal_errors(true);
            $envelope = simplexml_load_string($soapResponse);
            libxml_use_internal_errors($previous);

            if ($envelope === false) {
                Logger::error('MNB válasz nem értelmezhető XML.');
                return null;
            }

            // A hasznos tartalom a GetCurrentExchangeRatesResult elem szöveges
            // értéke, ami maga is egy (escape-elt) XML dokumentum.
            $result = null;
            foreach ($envelope->xpath('//*[local-name()="GetCurrentExchangeRatesResult"]') ?: [] as $node) {
                $result = (string) $node;
                break;
            }

            if ($result === null || trim($result) === '') {
                Logger::error('Az MNB válaszban nincs árfolyam adat.');
                return null;
            }

            $rates = simplexml_load_string($result);
            if ($rates === false) {
                Logger::error('Az MNB árfolyam XML nem értelmezhető.');
                return null;
            }

            return self::collect($rates);
        } catch (Throwable $e) {
            Logger::error('MNB árfolyam feldolgozási hiba: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @return array{date: string, rates: array<string, float>}
     */
    private static function collect(SimpleXMLElement $rates): array
    {
        // A forint önmagához mérve mindig 1, az MNB nem is adja vissza.
        $collected = ['HUF' => 1.0];
        $date = '';

        foreach ($rates->Day ?? [] as $day) {
            $date = trim((string) $day['date']);

            foreach ($day->Rate ?? [] as $rate) {
                $code = strtoupper(trim((string) $rate['curr']));
                $unit = (float) ((string) $rate['unit'] ?: '1');
                // Tizedesvessző, ezres tagolás nélkül.
                $amount = (float) str_replace([' ', ','], ['', '.'], trim((string) $rate));

                if ($code === '' || $unit <= 0 || $amount <= 0) {
                    continue;
                }

                $collected[$code] = $amount / $unit;
            }

            // Csak a legfrissebb nap kell; a szolgáltatás egyet ad, de legyen biztos.
            break;
        }

        return ['date' => $date, 'rates' => $collected];
    }
}
