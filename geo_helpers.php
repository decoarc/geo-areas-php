<?php

// Limites para a demo pública. Todos podem ser definidos no ambiente ou no .env.
const MAX_POLYGON_POINTS = 500;
const MAX_NAME_LENGTH = 255;
const MAX_DESCRIPTION_LENGTH = 2000;
const DEFAULT_AREA_LIMIT = 300;
const DEFAULT_RETENTION_DAYS = 7;
const DEFAULT_WRITES_PER_HOUR = 10;
const DEFAULT_READS_PER_MINUTE = 300;
const DEFAULT_TERMS_FILE = __DIR__ . '/moderation_terms.txt';

// Nome: letras, números, espaços e pontuação comum. Sem HTML, aspas de script ou quebras de linha.
const NAME_PATTERN = '/^[\p{L}\p{N} \-_.,:;()\'"!?&\/°+#]*$/u';

// Caracteres acentuados mapeados para a versão sem acento (usado pela moderação).
const ACCENT_MAP = [
    'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
    'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
    'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
    'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
    'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
    'ç' => 'c', 'ñ' => 'n',
];

// Mensagens em inglês usadas no campo "error" da API.
// O front-end traduz pelo "error_code" (chaves api.<code> em i18n.js).
const API_ERRORS = [
    'invalid_json' => 'Invalid JSON',
    'missing_action' => 'Required field missing: action',
    'missing_fields' => 'Required fields missing: {fields}',
    'method_not_allowed' => 'Method not allowed',
    'invalid_action' => 'Invalid action. Use utm, gms, from_utm or from_gms',
    'text_required' => 'Name and description must be text',
    'too_few_points' => 'At least 3 distinct points are required',
    'too_many_points' => 'Polygon has too many points (maximum {max})',
    'name_too_long' => 'Name is too long (maximum {max} characters)',
    'name_invalid_characters' => 'Name contains characters that are not allowed',
    'description_too_long' => 'Description is too long (maximum {max} characters)',
    'content_rejected' => 'Text contains content that is not allowed',
    'invalid_coordinate' => 'Invalid coordinate',
    'coordinate_out_of_bounds' => 'Coordinate out of bounds',
    'area_limit' => 'Area limit reached ({max}). Try again later.',
    'rate_limited' => 'Too many requests. Try again later.',
    'save_failed' => 'Could not save the area',
    'area_not_found' => 'Area not found',
    'perimeter_failed' => 'Could not calculate the perimeter',
    'perimeter_input_required' => 'Provide an area ID or valid coordinates',
    'gms_format' => 'Invalid GMS format: {value}',
    'conversion_failed' => 'Conversion error',
];

/**
 * Responde com erro em JSON e encerra a requisição.
 */
function jsonError(string $code, array $params = [], int $status = 400): void
{
    $message = API_ERRORS[$code] ?? 'Unknown error';
    foreach ($params as $key => $value) {
        $message = str_replace('{' . $key . '}', (string) $value, $message);
    }

    http_response_code($status);
    echo json_encode([
        'error' => $message,
        'error_code' => $code,
        'params' => (object) $params,
    ]);
    exit;
}

/**
 * Lê uma variável de configuração: primeiro do ambiente, depois do .env.
 */
function configValue(string $key, ?string $envFile = null): ?string
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    $envFile ??= __DIR__ . '/.env';
    if (!is_readable($envFile)) {
        return null;
    }
    $config = parse_ini_file($envFile);
    return isset($config[$key]) ? (string) $config[$key] : null;
}

/**
 * Lê um inteiro não negativo de configuração; valores inválidos usam o padrão.
 */
function configInt(string $key, int $default, ?string $envFile = null): int
{
    $raw = configValue($key, $envFile);
    return $raw !== null && ctype_digit($raw) ? (int) $raw : $default;
}

function areaLimit(?string $envFile = null): int
{
    return configInt('AREA_LIMIT', DEFAULT_AREA_LIMIT, $envFile);
}

/**
 * Remove o ponto de fechamento quando o anel já vem fechado (primeiro == último).
 */
function normalizeRing(array $coords): array
{
    $coords = array_values($coords);
    $count = count($coords);

    if ($count > 1
        && (float) $coords[0]['lat'] === (float) $coords[$count - 1]['lat']
        && (float) $coords[0]['lng'] === (float) $coords[$count - 1]['lng']) {
        array_pop($coords);
    }

    return $coords;
}

/**
 * Valida nome, descrição e pontos de uma área.
 * Retorna [código, parâmetros] para o primeiro erro encontrado, ou null se estiver ok.
 */
function validateAreaInput(string $name, string $desc, array $coords): ?array
{
    if (mb_strlen($name) > MAX_NAME_LENGTH) {
        return ['name_too_long', ['max' => MAX_NAME_LENGTH]];
    }
    if (mb_strlen($desc) > MAX_DESCRIPTION_LENGTH) {
        return ['description_too_long', ['max' => MAX_DESCRIPTION_LENGTH]];
    }
    if (count($coords) > MAX_POLYGON_POINTS) {
        return ['too_many_points', ['max' => MAX_POLYGON_POINTS]];
    }

    foreach ($coords as $coord) {
        if (!is_array($coord) || !isset($coord['lat'], $coord['lng'])
            || !is_numeric($coord['lat']) || !is_numeric($coord['lng'])) {
            return ['invalid_coordinate', []];
        }
        if (abs((float) $coord['lat']) > 90 || abs((float) $coord['lng']) > 180) {
            return ['coordinate_out_of_bounds', []];
        }
    }

    if (count(normalizeRing($coords)) < 3) {
        return ['too_few_points', []];
    }

    return null;
}

/**
 * Valida caracteres do nome e termos bloqueados em nome e descrição.
 * Retorna [código, parâmetros] ou null.
 */
function validateContent(string $name, string $desc, ?string $termsFile = null): ?array
{
    if (preg_match(NAME_PATTERN, $name) !== 1) {
        return ['name_invalid_characters', []];
    }

    $terms = moderationTerms($termsFile);
    foreach ([$name, $desc] as $text) {
        if (containsBlockedTerm($text, $terms)) {
            return ['content_rejected', []];
        }
    }

    return null;
}

/**
 * Carrega os termos bloqueados (um por linha; linhas com # são comentários).
 */
function moderationTerms(?string $file = null): array
{
    static $cache = [];
    $file ??= configValue('MODERATION_TERMS_FILE') ?? DEFAULT_TERMS_FILE;

    if (!isset($cache[$file])) {
        $cache[$file] = [];
        if (is_readable($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
                $line = trim($line);
                if ($line !== '' && $line[0] !== '#') {
                    $cache[$file][] = trim(normalizeForModeration($line));
                }
            }
        }
    }

    return $cache[$file];
}

/**
 * Minúsculas, sem acentos e com pontuação virada em espaço: " palavra outra ".
 */
function normalizeForModeration(string $text): string
{
    $text = strtr(mb_strtolower($text, 'UTF-8'), ACCENT_MAP);
    $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? '';
    return ' ' . trim($text) . ' ';
}

/**
 * Procura um termo como palavra inteira. Também junta sequências de letras soltas
 * separadas por pontuação (ex.: "p.a.l.a.v.r.a" vira "palavra") para pegar grafias
 * disfarçadas, sem casar termos dentro de palavras maiores.
 */
function containsBlockedTerm(string $text, array $terms): bool
{
    $normalized = normalizeForModeration($text);

    $runs = [];
    $run = '';
    foreach (explode(' ', trim($normalized)) as $token) {
        if (strlen($token) === 1) {
            $run .= $token;
            continue;
        }
        if ($run !== '') {
            $runs[] = $run;
            $run = '';
        }
    }
    if ($run !== '') {
        $runs[] = $run;
    }

    foreach ($terms as $term) {
        if (str_contains($normalized, ' ' . $term . ' ')) {
            return true;
        }
        if (strlen($term) >= 5 && !str_contains($term, ' ')) {
            foreach ($runs as $letters) {
                if (str_contains($letters, $term)) {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * Descarta as áreas mais antigas para que, após a inserção, o total não passe de $limit.
 * Deve ser chamada dentro da transação com o lock de limite.
 */
function makeRoomForArea(PDO $conn, int $limit): void
{
    $total = (int) $conn->query('SELECT COUNT(*) FROM areas')->fetchColumn();
    $excess = $total - ($limit - 1);

    if ($excess > 0) {
        $stmt = $conn->prepare(
            'DELETE FROM areas WHERE id IN (
                 SELECT id FROM areas ORDER BY created_at ASC NULLS FIRST, id LIMIT ?
             )'
        );
        $stmt->bindValue(1, $excess, PDO::PARAM_INT);
        $stmt->execute();
    }
}

/**
 * Remove áreas mais antigas que AREA_RETENTION_DAYS. Retorna quantas foram removidas.
 */
function purgeExpiredAreas(PDO $conn): int
{
    $days = configInt('AREA_RETENTION_DAYS', DEFAULT_RETENTION_DAYS);
    $stmt = $conn->prepare(
        'DELETE FROM areas
         WHERE created_at IS NULL
            OR created_at < now() - make_interval(days => CAST(? AS integer))'
    );
    $stmt->execute([$days]);
    return $stmt->rowCount();
}

/**
 * Remove contadores de rate limit com mais de dois dias.
 */
function purgeExpiredRateLimits(PDO $conn): int
{
    $stmt = $conn->prepare('DELETE FROM rate_limits WHERE window_start < ?');
    $stmt->execute([time() - 2 * 86400]);
    return $stmt->rowCount();
}

/**
 * IP do cliente. Atrás do proxy da Render, só use X-Forwarded-For se TRUST_PROXY=1:
 * o último valor é o IP que o proxy confiável viu conectar.
 */
function clientIp(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (configValue('TRUST_PROXY') === '1' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        $last = end($parts);
        if (filter_var($last, FILTER_VALIDATE_IP)) {
            return $last;
        }
    }

    return $remote;
}

/**
 * Conta a requisição na janela atual e responde 429 se passar do limite.
 * Guarda só o HMAC do IP, nunca o IP em texto puro.
 */
function enforceRateLimit(PDO $conn, string $bucket, int $limit, int $windowSeconds): void
{
    $now = time();
    $windowStart = intdiv($now, $windowSeconds) * $windowSeconds;
    $secret = configValue('RATE_LIMIT_SECRET') ?? 'set-RATE_LIMIT_SECRET';
    $ipHash = hash_hmac('sha256', clientIp(), $secret);

    $stmt = $conn->prepare(
        'INSERT INTO rate_limits (ip_hash, bucket, window_start, hits)
         VALUES (?, ?, ?, 1)
         ON CONFLICT (ip_hash, bucket, window_start)
         DO UPDATE SET hits = rate_limits.hits + 1
         RETURNING hits'
    );
    $stmt->execute([$ipHash, $bucket, $windowStart]);
    $hits = (int) $stmt->fetchColumn();

    // Limpeza oportunista: ~1% das requisições removem contadores antigos.
    if (random_int(1, 100) === 1) {
        purgeExpiredRateLimits($conn);
    }

    if ($hits > $limit) {
        header('Retry-After: ' . ($windowStart + $windowSeconds - $now));
        jsonError('rate_limited', [], 429);
    }
}

function coordsToWKT(array $coords): string
{
    $coords = normalizeRing($coords);

    $points = [];
    foreach ($coords as $coord) {
        $lng = floatval($coord['lng']);
        $lat = floatval($coord['lat']);
        $points[] = "{$lng} {$lat}";
    }

    if (count($points) > 0) {
        $points[] = $points[0];
    }

    return 'POLYGON((' . implode(', ', $points) . '))';
}

function wktToCoords(string $wkt): array
{
    $wkt = preg_replace('/^POLYGON\(\(/', '', $wkt);
    $wkt = preg_replace('/\)\)$/', '', $wkt);

    $points = preg_split('/,\s*/', $wkt);
    $coords = [];

    foreach ($points as $point) {
        $parts = preg_split('/\s+/', trim($point));
        if (count($parts) === 2) {
            $coords[] = [
                'lat' => floatval($parts[1]),
                'lng' => floatval($parts[0]),
            ];
        }
    }

    if (
        count($coords) > 0
        && $coords[0]['lat'] == $coords[count($coords) - 1]['lat']
        && $coords[0]['lng'] == $coords[count($coords) - 1]['lng']
    ) {
        array_pop($coords);
    }

    return $coords;
}
