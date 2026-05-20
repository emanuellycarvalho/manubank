<?php

declare(strict_types=1);

/**
 * Taxa CDI anualizada via API pública, com cache em ficheiro (24 h).
 */
final class CdiService
{
    private const CACHE_TTL_SECONDS = 86400;

    private const BRAPI_URL = 'https://brapi.dev/api/v2/prime-rate';

    /** Série SGS 4392 — CDI acumulado no mês em termos anuais (base 252). */
    private const BCB_CDI_ANNUAL_URL = 'https://api.bcb.gov.br/dados/serie/bcdata.sgs.4392/dados/ultimos/1?formato=json';

    public function __construct(
        private readonly ?string $cacheFilePath = null,
    ) {
    }

    /**
     * CDI anual em percentual (ex.: 10.5 para 10,5 % a.a.).
     *
     * @throws \RuntimeException Se não for possível obter nem ler cache válido
     */
    public function getAnnualCdiRate(bool $forceRefresh = false): float
    {
        if (!$forceRefresh) {
            $cached = $this->readCache();
            if ($cached !== null) {
                return $cached;
            }
        }

        $rate = $this->fetchFromBcb() ?? $this->fetchFromBrapi();

        if ($rate === null) {
            throw new \RuntimeException('Não foi possível obter a taxa CDI das fontes configuradas.');
        }

        $this->writeCache($rate);

        return $rate;
    }

    /**
     * Metadados do cache (para a API).
     *
     * @return array{rate: float, fetched_at: string, source: string, from_cache: bool}|null
     */
    public function getCachedMetadata(): ?array
    {
        $path = $this->resolveCachePath();

        if (!is_readable($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['rate'], $data['fetched_at'])) {
            return null;
        }

        if ($this->isCacheExpired((int) ($data['fetched_at_unix'] ?? 0))) {
            return null;
        }

        return [
            'rate'       => round((float) $data['rate'], 4),
            'fetched_at' => (string) $data['fetched_at'],
            'source'     => (string) ($data['source'] ?? 'cache'),
            'from_cache' => true,
        ];
    }

    private function readCache(): ?float
    {
        $meta = $this->getCachedMetadata();

        return $meta !== null ? $meta['rate'] : null;
    }

    private function writeCache(float $rate, string $source = 'live'): void
    {
        $path = $this->resolveCachePath();
        $dir  = dirname($path);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Não foi possível criar o diretório de cache: {$dir}");
        }

        $payload = [
            'rate'            => round($rate, 4),
            'fetched_at'      => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
            'fetched_at_unix' => time(),
            'source'          => $source,
        ];

        $written = file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        if ($written === false) {
            throw new \RuntimeException("Falha ao gravar cache CDI em {$path}");
        }
    }

    private function isCacheExpired(int $fetchedAtUnix): bool
    {
        if ($fetchedAtUnix <= 0) {
            return true;
        }

        return (time() - $fetchedAtUnix) >= self::CACHE_TTL_SECONDS;
    }

    private function resolveCachePath(): string
    {
        if ($this->cacheFilePath !== null) {
            return $this->cacheFilePath;
        }

        return dirname(__DIR__) . '/db/cache/cdi_rate.json';
    }

    private function fetchFromBrapi(): ?float
    {
        $token = getenv('BRAPI_TOKEN') ?: '';
        if ($token === '') {
            return null;
        }

        $url  = self::BRAPI_URL . '?token=' . rawurlencode($token);
        $json = $this->httpGet($url);
        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        // Formato: { "prime-rate": [ { "value": 10.5, "epochDate": ... }, ... ] }
        $series = $data['prime-rate'] ?? $data['primeRate'] ?? $data['data'] ?? null;

        if (is_array($series) && $series !== []) {
            $latest = $series[0];
            if (is_array($latest) && isset($latest['value'])) {
                return $this->normalizeRate((float) $latest['value']);
            }
        }

        if (isset($data['value'])) {
            return $this->normalizeRate((float) $data['value']);
        }

        return null;
    }

    private function fetchFromBcb(): ?float
    {
        $json = $this->httpGet(self::BCB_CDI_ANNUAL_URL);
        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        $rows = $data['data'] ?? $data;
        if (!is_array($rows) || $rows === []) {
            return null;
        }

        $last = $rows[array_key_last($rows)];
        if (!is_array($last)) {
            return null;
        }

        $valor = $last['valor'] ?? $last['value'] ?? null;
        if ($valor === null || $valor === '') {
            return null;
        }

        return $this->normalizeRate((float) str_replace(',', '.', (string) $valor));
    }

    private function normalizeRate(float $value): float
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException('Taxa CDI inválida (<= 0).');
        }

        // Valores fraccionários (0.105) → percentual (10.5)
        if ($value > 0 && $value < 1) {
            $value *= 100;
        }

        return round($value, 4);
    }

    private function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_USERAGENT      => 'ManuBank/1.0',
            ]);

            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body === false || $code < 200 || $code >= 300) {
                return null;
            }

            return (string) $body;
        }

        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => 15,
                'header'        => "Accept: application/json\r\nUser-Agent: ManuBank/1.0\r\n",
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        return $body !== false ? $body : null;
    }
}
