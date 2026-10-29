<?php
/**
 * Testes automatizados do Geo Areas.
 *
 * Uso (banco de TESTE, nunca o banco de produção):
 *   DB_HOST=localhost DB_PORT=55432 DB_USER=postgres DB_PASS=... DB_NAME=geo_test php tests/run.php
 *
 * O nome do banco precisa conter "test"; o script se recusa a rodar de outra forma
 * e apaga o conteúdo das tabelas `areas` e `rate_limits` antes dos testes de integração.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/geo_helpers.php';

$failures = 0;
$passes = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $failures, $passes;
    if ($ok) {
        $passes++;
        echo "  ok    {$name}\n";
    } else {
        $failures++;
        echo "  FAIL  {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

function near(float $actual, float $expected, float $relTol): bool
{
    return abs($actual - $expected) <= abs($expected) * $relTol;
}

// ---------------------------------------------------------------------------
// Unitários (sem banco)
// ---------------------------------------------------------------------------
echo "Unitários\n";

$square = [
    ['lat' => 0.0, 'lng' => 0.0],
    ['lat' => 0.0, 'lng' => 0.1],
    ['lat' => 0.1, 'lng' => 0.1],
    ['lat' => 0.1, 'lng' => 0.0],
];

check('validação aceita quadrado válido', validateAreaInput('a', '', $square) === null);

$closed = array_merge($square, [$square[0]]);
check('anel fechado é normalizado', count(normalizeRing($closed)) === 4);
check(
    'WKT de anel fechado não duplica o ponto de fechamento',
    substr_count(coordsToWKT($closed), ',') === 4,
    coordsToWKT($closed)
);
check('WKT → coords → WKT preserva os vértices', wktToCoords(coordsToWKT($square)) === $square);

$expectCode = static function (string $label, ?array $result, string $code): void {
    check($label, $result !== null && $result[0] === $code, $result ? $result[0] : 'sem erro');
};

$expectCode('poucos pontos', validateAreaInput('a', '', array_slice($square, 0, 2)), 'too_few_points');
$expectCode(
    'A-B-A (degenerado) é rejeitado',
    validateAreaInput('a', '', [['lat' => 0, 'lng' => 0], ['lat' => 0, 'lng' => 1], ['lat' => 0, 'lng' => 0]]),
    'too_few_points'
);
$expectCode(
    'lat > 90',
    validateAreaInput('a', '', [['lat' => 91, 'lng' => 0], ['lat' => 0, 'lng' => 0], ['lat' => 1, 'lng' => 1]]),
    'coordinate_out_of_bounds'
);
$expectCode(
    'coordenada não numérica',
    validateAreaInput('a', '', [['lat' => 'x', 'lng' => 0], ['lat' => 0, 'lng' => 0], ['lat' => 1, 'lng' => 1]]),
    'invalid_coordinate'
);
$expectCode(
    '501 pontos',
    validateAreaInput('a', '', array_fill(0, 501, ['lat' => 1, 'lng' => 1])),
    'too_many_points'
);
$expectCode('nome com 256 caracteres', validateAreaInput(str_repeat('á', 256), '', $square), 'name_too_long');
$expectCode('descrição com 2001 caracteres', validateAreaInput('a', str_repeat('x', 2001), $square), 'description_too_long');
check('nome com 255 caracteres multibyte é aceito', validateAreaInput(str_repeat('á', 255), '', $square) === null);

// Conteúdo: caracteres do nome e termos bloqueados
$termsFile = tempnam(sys_get_temp_dir(), 'geo_terms_');
file_put_contents($termsFile, "bloqueado\n# comentário ignorado\npalavra\n");

$expectCode('nome com HTML é rejeitado', validateContent('<script>x</script>', '', $termsFile), 'name_invalid_characters');
$expectCode('nome com quebra de linha é rejeitado', validateContent("a\nb", '', $termsFile), 'name_invalid_characters');
check('nome com acentos, # e parênteses é aceito', validateContent('Área São Paulo (teste) #1', 'ok', $termsFile) === null);
$expectCode('termo bloqueado no nome (com pontuação)', validateContent('Bloqueado!', '', $termsFile), 'content_rejected');
$expectCode('termo bloqueado na descrição', validateContent('Área', 'texto bloqueado aqui', $termsFile), 'content_rejected');
$expectCode('grafia com letras separadas', validateContent('Área', 'p.a.l.a.v.r.a', $termsFile), 'content_rejected');
check('palavra que apenas contém o termo não é bloqueada', validateContent('Espalavrada', '', $termsFile) === null);
check('comentário no arquivo de termos não é tratado como termo', validateContent('comentário', '', $termsFile) === null);
check('normalização remove acentos e caixa', normalizeForModeration('Ação-Ótima!') === ' acao otima ');

$envFile = tempnam(sys_get_temp_dir(), 'geo_env_');
putenv('AREA_LIMIT');
check('AREA_LIMIT padrão é 300 sem configuração', areaLimit($envFile) === 300 && DEFAULT_AREA_LIMIT === 300);
file_put_contents($envFile, "AREA_LIMIT=7\n");
check('AREA_LIMIT lido do .env', areaLimit($envFile) === 7);
file_put_contents($envFile, "AREA_LIMIT=abc\n");
check('AREA_LIMIT inválido cai no padrão', areaLimit($envFile) === DEFAULT_AREA_LIMIT);
putenv('AREA_LIMIT=12');
check('AREA_LIMIT do ambiente tem prioridade sobre .env', areaLimit($envFile) === 12);
putenv('AREA_LIMIT');
unlink($envFile);

// Todo código de erro precisa de tradução PT e EN
$i18n = file_get_contents($root . '/i18n.js');
$missing = [];
foreach (array_keys(API_ERRORS) as $code) {
    if (substr_count($i18n, "\"api.{$code}\":") < 2) {
        $missing[] = $code;
    }
}
check('todo error_code do backend tem tradução PT e EN', $missing === [], implode(', ', $missing));

// ---------------------------------------------------------------------------
// Integração (PostgreSQL/PostGIS de teste + servidor PHP embutido)
// ---------------------------------------------------------------------------
echo "\nIntegração\n";

$dbName = getenv('DB_NAME') ?: '';
$dbHost = getenv('DB_HOST') ?: 'localhost';
if (stripos($dbName, 'test') === false) {
    echo "  Banco '{$dbName}' não parece ser de teste. Defina DB_NAME com 'test'. Integração ignorada.\n";
    echo "\nResultado: {$passes} ok, {$failures} falhas\n";
    exit($failures > 0 ? 1 : 0);
}

$pdo = new PDO(
    sprintf('pgsql:host=%s;port=%s;dbname=%s', $dbHost, getenv('DB_PORT') ?: '5432', $dbName),
    getenv('DB_USER') ?: 'postgres',
    getenv('DB_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec(file_get_contents($root . '/docker/init.sql'));

// Migração 002 em banco "antigo" (sem rate_limits), executada duas vezes
$pdo->exec('DROP TABLE IF EXISTS rate_limits');
$migration = file_get_contents($root . '/migrations/002_demo_protection.sql');
$migrationError = '';
try {
    $pdo->exec($migration);
    $pdo->exec($migration);
} catch (PDOException $e) {
    $migrationError = $e->getMessage();
}
$indexes = (int) $pdo->query("SELECT COUNT(*) FROM pg_indexes WHERE indexname IN ('idx_rate_limits_window', 'idx_areas_created_at')")->fetchColumn();
check('migração 002 é idempotente (roda duas vezes) e cria os dois índices', $migrationError === '' && $indexes === 2, $migrationError);

function resetAreas(PDO $pdo): void
{
    $pdo->exec('TRUNCATE areas RESTART IDENTITY');
    $pdo->exec('TRUNCATE rate_limits');
}

function http(string $method, string $url, ?string $body = null, array $headers = []): array
{
    $headerLines = "Content-Type: application/json\r\n";
    foreach ($headers as $name => $value) {
        $headerLines .= "{$name}: {$value}\r\n";
    }
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => $headerLines,
        'content' => $body ?? '',
        'ignore_errors' => true,
        'timeout' => 15,
    ]]);
    $raw = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+ (\d{3})#', $header, $m)) {
            $status = (int) $m[1];
        }
    }
    return [$status, json_decode((string) $raw, true), (string) $raw];
}

$termsPath = $termsFile;

/** Sobe um servidor PHP embutido com configuração de teste padrão. */
function startServer(string $root, int $port, array $extraEnv, string $termsPath): array
{
    $defaults = [
        'AREA_LIMIT' => '300',
        'AREA_RETENTION_DAYS' => '7',
        'RATE_LIMIT_WRITES_PER_HOUR' => '100000',
        'RATE_LIMIT_READS_PER_MINUTE' => '100000',
        'RATE_LIMIT_SECRET' => 'test-secret',
        'TRUST_PROXY' => '0',
        'MODERATION_TERMS_FILE' => $termsPath,
    ];
    $env = array_merge(getenv(), $defaults, $extraEnv);
    $log = tempnam(sys_get_temp_dir(), 'geo_srv_');
    $process = proc_open(
        ['php', '-S', "127.0.0.1:{$port}", '-t', $root],
        [1 => ['file', $log, 'w'], 2 => ['file', $log, 'w']],
        $pipes,
        $root,
        $env
    );
    for ($i = 0; $i < 50; $i++) {
        $socket = @fsockopen('127.0.0.1', $port);
        if ($socket) {
            fclose($socket);
            return [$process, $log];
        }
        usleep(100000);
    }
    throw new RuntimeException("servidor na porta {$port} não subiu");
}

function stopServer(array $server): void
{
    [$process, $log] = $server;
    proc_terminate($process);
    proc_close($process);
    @unlink($log);
}

$port = 8765;
$base = "http://127.0.0.1:{$port}";
$server = startServer($root, $port, [], $termsPath);

try {
    resetAreas($pdo);
    echo "Endpoint save_area.php\n";

    $square_json = json_encode(['name' => 'Quadrado', 'description' => 'teste', 'coords' => $square]);
    [$status, $data] = http('POST', "{$base}/save_area.php", $square_json);
    check('salva quadrado válido', $status === 200 && ($data['success'] ?? false) === true, $status . ' ' . json_encode($data));
    $areaId = $data['id'] ?? 0;
    check('área ≈ 123 km² no equador (0,1° x 0,1°)', near((float) ($data['area_km2'] ?? 0), 123.1, 0.01), (string) ($data['area_km2'] ?? ''));

    [$status, $data] = http('POST', "{$base}/save_perimeter.php", json_encode(['id' => $areaId]));
    check('perímetro ≈ 44,4 km pelo id', $status === 200 && near((float) ($data['perimeter_km'] ?? 0), 44.38, 0.01), json_encode($data));

    [$status, $data] = http('POST', "{$base}/save_perimeter.php", json_encode(['coords' => $square]));
    check('perímetro ≈ 44,4 km por coordenadas', $status === 200 && near((float) ($data['perimeter_km'] ?? 0), 44.38, 0.01), json_encode($data));

    [, $list] = http('GET', "{$base}/get_areas.php");
    check('get_areas devolve a área com 4 vértices', count(json_decode($list[0]['coords'], true)) === 4);

    [$status, $data] = http('POST', "{$base}/save_area.php", json_encode([
        'name' => 'Fechado', 'coords' => array_merge($square, [$square[0]]),
    ]));
    check('anel fechado é aceito', $status === 200 && ($data['success'] ?? false) === true);
    $closedId = $data['id'] ?? 0;
    $stored = $pdo->query("SELECT ST_NPoints(coords) FROM areas WHERE id = {$closedId}")->fetchColumn();
    check('anel fechado é salvo sem ponto duplicado (5 pontos WKT)', (int) $stored === 5, (string) $stored);

    $errorCases = [
        'JSON inválido' => ['{', 400, 'invalid_json'],
        'payload não é objeto' => ['"abc"', 400, 'invalid_json'],
        'nome como array' => [json_encode(['name' => ['x'], 'coords' => $square]), 400, 'text_required'],
        'poucos pontos' => [json_encode(['coords' => array_slice($square, 0, 2)]), 400, 'too_few_points'],
        'A-B-A degenerado' => [json_encode(['coords' => [
            ['lat' => 0, 'lng' => 0], ['lat' => 0, 'lng' => 1], ['lat' => 0, 'lng' => 0],
        ]]), 400, 'too_few_points'],
        'lat fora dos limites' => [json_encode(['coords' => [
            ['lat' => 91, 'lng' => 0], ['lat' => 0, 'lng' => 0], ['lat' => 1, 'lng' => 1],
        ]]), 400, 'coordinate_out_of_bounds'],
        'coordenada não numérica' => [json_encode(['coords' => [
            ['lat' => 'abc', 'lng' => 0], ['lat' => 0, 'lng' => 0], ['lat' => 1, 'lng' => 1],
        ]]), 400, 'invalid_coordinate'],
        '501 pontos' => [json_encode(['coords' => array_fill(0, 501, ['lat' => 1, 'lng' => 1])]), 400, 'too_many_points'],
        'nome longo' => [json_encode(['name' => str_repeat('a', 256), 'coords' => $square]), 400, 'name_too_long'],
        'descrição longa' => [json_encode(['description' => str_repeat('a', 2001), 'coords' => $square]), 400, 'description_too_long'],
        'nome com HTML' => [json_encode(['name' => '<img src=x>', 'coords' => $square]), 400, 'name_invalid_characters'],
        'termo bloqueado no nome' => [json_encode(['name' => 'Bloqueado', 'coords' => $square]), 400, 'content_rejected'],
        'termo bloqueado na descrição' => [json_encode(['name' => 'ok', 'description' => 'um palavra aqui', 'coords' => $square]), 400, 'content_rejected'],
    ];
    foreach ($errorCases as $label => [$body, $expectedStatus, $expectedCode]) {
        [$status, $data] = http('POST', "{$base}/save_area.php", $body);
        check(
            "rejeita: {$label}",
            $status === $expectedStatus && ($data['error_code'] ?? '') === $expectedCode && is_string($data['error'] ?? null),
            "{$status} " . json_encode($data)
        );
    }

    [$status, $data] = http('POST', "{$base}/save_area.php", json_encode(['name' => 'Área São Paulo (teste) #1', 'coords' => $square]));
    check('nome com acento, parênteses e # é aceito', $status === 200 && ($data['success'] ?? false) === true, json_encode($data));

    [$status, $data] = http('GET', "{$base}/save_area.php");
    check('GET em save_area é 405', $status === 405 && ($data['error_code'] ?? '') === 'method_not_allowed');

    echo "Retenção e limpeza\n";
    $pdo->exec("INSERT INTO areas (name, coords, created_at) VALUES ('antiga-gravacao', ST_GeomFromText('POLYGON((0 0,0 1,1 1,0 0))', 4326), now() - interval '8 days')");
    $pdo->exec("INSERT INTO areas (name, coords, created_at) VALUES ('recente', ST_GeomFromText('POLYGON((0 0,0 1,1 1,0 0))', 4326), now() - interval '2 days')");
    http('POST', "{$base}/save_area.php", json_encode(['name' => 'gatilho', 'coords' => $square]));
    $names = $pdo->query("SELECT name FROM areas ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    check('gravação remove áreas com mais de 7 dias', !in_array('antiga-gravacao', $names, true), implode(',', $names));
    check('gravação mantém áreas recentes', in_array('recente', $names, true));

    $pdo->exec("INSERT INTO areas (name, coords, created_at) VALUES ('antiga-cli', ST_GeomFromText('POLYGON((0 0,0 1,1 1,0 0))', 4326), now() - interval '9 days')");
    exec('php ' . escapeshellarg($root . '/cleanup_areas.php') . ' 2>&1', $cliOut, $cliCode);
    $left = (int) $pdo->query("SELECT COUNT(*) FROM areas WHERE name = 'antiga-cli'")->fetchColumn();
    check('cleanup_areas.php remove áreas expiradas', $cliCode === 0 && $left === 0, implode(' ', $cliOut));
    check('cleanup_areas.php mantém áreas recentes', (int) $pdo->query("SELECT COUNT(*) FROM areas WHERE name = 'recente'")->fetchColumn() === 1);

    $pdo->exec("INSERT INTO areas (name, coords, created_at) VALUES ('sem-data', ST_GeomFromText('POLYGON((0 0,0 1,1 1,0 0))', 4326), NULL)");
    http('POST', "{$base}/save_area.php", json_encode(['name' => 'gatilho2', 'coords' => $square]));
    $nullLeft = (int) $pdo->query("SELECT COUNT(*) FROM areas WHERE name = 'sem-data'")->fetchColumn();
    check('área sem created_at também é removida pela retenção', $nullLeft === 0);

    $ipCols = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'rate_limits' AND column_name LIKE '%ip%' AND column_name <> 'ip_hash'")->fetchColumn();
    check('rate_limits não tem coluna de IP em texto puro', (int) $ipCols === 0);
    $hashes = $pdo->query('SELECT ip_hash FROM rate_limits')->fetchAll(PDO::FETCH_COLUMN);
    check('rate_limits guarda só HMAC (64 hex), nunca 127.0.0.1', $hashes !== [] && array_reduce($hashes, fn ($ok, $h) => $ok && strlen($h) === 64 && $h !== '127.0.0.1', true));

    echo "Endpoint save_perimeter.php\n";
    [$status, $data] = http('POST', "{$base}/save_perimeter.php", json_encode(['id' => 999999]));
    check('id inexistente → 404 area_not_found', $status === 404 && ($data['error_code'] ?? '') === 'area_not_found');
    [$status, $data] = http('POST', "{$base}/save_perimeter.php", json_encode([]));
    check('sem id nem coords → perimeter_input_required', ($data['error_code'] ?? '') === 'perimeter_input_required');
    [$status, $data] = http('POST', "{$base}/save_perimeter.php", json_encode(['coords' => [['lat' => 0, 'lng' => 0]]]));
    check('coords insuficientes → perimeter_input_required', ($data['error_code'] ?? '') === 'perimeter_input_required', json_encode($data));
    [$status, $data] = http('POST', "{$base}/save_perimeter.php", json_encode(['coords' => [
        ['lat' => 95, 'lng' => 0], ['lat' => 0, 'lng' => 0], ['lat' => 1, 'lng' => 1],
    ]]));
    check('coords fora dos limites → coordinate_out_of_bounds', ($data['error_code'] ?? '') === 'coordinate_out_of_bounds');

    echo "Endpoint coordinate_converter.php\n";
    $conv = "{$base}/coordinate_converter.php";
    $saoPaulo = ['lat' => -23.55052, 'lng' => -46.633308];

    [$status, $data] = http('POST', $conv, json_encode(['action' => 'utm'] + $saoPaulo));
    check('utm: zona 23 S', $status === 200 && $data['zone'] === 23 && $data['hemisphere'] === 'S', json_encode($data));
    check(
        'utm: coordenadas ≈ E 333 000 / N 7 394 000',
        abs($data['easting'] - 333000) < 1500 && abs($data['northing'] - 7394000) < 3000,
        json_encode($data)
    );

    [, $back] = http('POST', $conv, json_encode([
        'action' => 'from_utm', 'easting' => $data['easting'], 'northing' => $data['northing'],
        'zone' => $data['zone'], 'hemisphere' => $data['hemisphere'],
    ]));
    check(
        'utm → lat/lng ida e volta (≤ 0,0001°)',
        abs($back['lat'] - $saoPaulo['lat']) < 0.0001 && abs($back['lng'] - $saoPaulo['lng']) < 0.0001,
        json_encode($back)
    );

    [$status, $data] = http('POST', $conv, json_encode(['action' => 'gms', 'lat' => -23.55052, 'lng' => -46.633308]));
    check('gms: formato de saída', $status === 200 && str_contains($data['lat'], 'S') && str_contains($data['lng'], 'W'), json_encode($data));

    [$status, $data] = http('POST', $conv, json_encode([
        'action' => 'from_gms', 'gms_lat' => "23°33'1.92\"S", 'gms_lng' => "46°37'59.91\"W",
    ]));
    check('from_gms converte para decimal', $status === 200 && abs($data['lat'] + 23.550533) < 0.00001 && abs($data['lng'] + 46.633308) < 0.00001, json_encode($data));

    [$status, $data] = http('POST', $conv, json_encode(['action' => 'from_gms', 'gms_lat' => 'abc', 'gms_lng' => '1']));
    check('from_gms inválido → gms_format com valor', $status === 400 && $data['error_code'] === 'gms_format' && str_contains($data['error'], 'abc'));

    [$status, $data] = http('POST', $conv, json_encode(['action' => 'utm', 'lat' => 1]));
    check('campos faltando → missing_fields', $status === 400 && $data['error_code'] === 'missing_fields' && str_contains($data['error'], 'lng'));

    [$status, $data] = http('POST', $conv, json_encode(['action' => 'nope']));
    check('ação inválida → invalid_action', $status === 400 && $data['error_code'] === 'invalid_action');

    [$status, $data] = http('POST', $conv, json_encode(['lat' => 1]));
    check('sem action → missing_action', $status === 400 && $data['error_code'] === 'missing_action');

    [$status, $data] = http('POST', $conv, json_encode(['action' => 'gms', 'lat' => 95, 'lng' => 0]));
    check('lat 95 → coordinate_out_of_bounds', $status === 400 && $data['error_code'] === 'coordinate_out_of_bounds');

    [$status, $data] = http('POST', $conv, json_encode(['action' => 'from_utm', 'easting' => 1, 'northing' => 1, 'zone' => 61, 'hemisphere' => 'S']));
    check('zona 61 → invalid_coordinate', $status === 400 && $data['error_code'] === 'invalid_coordinate');

    [$status, $data] = http('GET', $conv);
    check('GET no conversor → 405', $status === 405 && $data['error_code'] === 'method_not_allowed');

    stopServer($server);
    $server = null;

    echo "FIFO e teto de áreas (AREA_LIMIT)\n";
    $fifoPort = 8766;
    $fifoBase = "http://127.0.0.1:{$fifoPort}";
    $server = startServer($root, $fifoPort, ['AREA_LIMIT' => '3'], $termsPath);
    resetAreas($pdo);
    foreach (['n1', 'n2', 'n3', 'n4'] as $n) {
        [$status] = http('POST', "{$fifoBase}/save_area.php", json_encode(['name' => $n, 'coords' => $square]));
        if ($status !== 200) {
            check("gravação {$n} com teto 3 retorna 200", false, (string) $status);
        }
    }
    $names = $pdo->query('SELECT name FROM areas ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    check('quarta gravação é aceita e remove a mais antiga (FIFO)', $names === ['n2', 'n3', 'n4'], implode(',', $names));

    echo "Falha no meio da gravação não perde dados\n";
    // Trigger que falha no INSERT: a poda FIFO feita antes precisa ser desfeita.
    $pdo->exec("CREATE OR REPLACE FUNCTION test_fail_insert() RETURNS trigger AS \$\$
        BEGIN IF NEW.name = 'boom' THEN RAISE EXCEPTION 'falha simulada'; END IF; RETURN NEW; END \$\$ LANGUAGE plpgsql");
    $pdo->exec('CREATE TRIGGER test_fail BEFORE INSERT ON areas FOR EACH ROW EXECUTE FUNCTION test_fail_insert()');
    [$status, $data] = http('POST', "{$fifoBase}/save_area.php", json_encode(['name' => 'boom', 'coords' => $square]));
    $namesAfterFail = $pdo->query('SELECT name FROM areas ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    check('gravação que falha retorna save_failed', $status === 500 && ($data['error_code'] ?? '') === 'save_failed', json_encode($data));
    check('falha no INSERT desfaz a poda FIFO (nenhuma área perdida)', $namesAfterFail === ['n2', 'n3', 'n4'], implode(',', $namesAfterFail));
    $pdo->exec('DROP TRIGGER test_fail ON areas');
    $pdo->exec('DROP FUNCTION test_fail_insert()');
    stopServer($server);
    $server = null;

    echo "Limite de áreas sob concorrência\n";
    $ports = range(8771, 8778);
    $runConcurrent = static function (int $limit, int $attempts) use ($ports, $root, $pdo, $square, $termsPath): array {
        $servers = [];
        foreach ($ports as $p) {
            $servers[] = startServer($root, $p, ['AREA_LIMIT' => (string) $limit], $termsPath);
        }
        resetAreas($pdo);

        $multi = curl_multi_init();
        $handles = [];
        for ($i = 0; $i < $attempts; $i++) {
            $ch = curl_init('http://127.0.0.1:' . $ports[$i % count($ports)] . '/save_area.php');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['name' => "c{$i}", 'coords' => $square]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[] = $ch;
        }
        do {
            $status = curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running > 0 && $status === CURLM_OK);

        $successes = 0;
        $others = 0;
        foreach ($handles as $ch) {
            $json = json_decode((string) curl_multi_getcontent($ch), true);
            ($json['success'] ?? false) === true ? $successes++ : $others++;
            curl_multi_remove_handle($multi, $ch);
        }
        curl_multi_close($multi);
        foreach ($servers as $s) {
            stopServer($s);
        }

        $rows = (int) $pdo->query('SELECT COUNT(*) FROM areas')->fetchColumn();
        return ['rows' => $rows, 'successes' => $successes, 'others' => $others];
    };

    $r = $runConcurrent(5, 80);
    check('80 gravações simultâneas com teto 5 deixam exatamente 5 áreas', $r['rows'] === 5, "linhas: {$r['rows']}");
    check('todas as 80 gravações são aceitas (poda FIFO)', $r['successes'] === 80 && $r['others'] === 0, json_encode($r));

    $r = $runConcurrent(300, 330);
    check('330 gravações simultâneas com teto padrão 300 não ultrapassam 300', $r['rows'] === 300, "linhas: {$r['rows']}");
    check('todas as 330 gravações são aceitas (poda FIFO)', $r['successes'] === 330 && $r['others'] === 0, json_encode($r));

    echo "Rate limit (429)\n";
    resetAreas($pdo);
    $rlPort = 8780;
    $rlBase = "http://127.0.0.1:{$rlPort}";
    $server = startServer($root, $rlPort, [
        'RATE_LIMIT_WRITES_PER_HOUR' => '3',
        'RATE_LIMIT_READS_PER_MINUTE' => '5',
        'TRUST_PROXY' => '0',
    ], $termsPath);
    $writeBody = json_encode(['name' => 'rl', 'coords' => $square]);
    $writes = [];
    for ($i = 0; $i < 4; $i++) {
        $writes[] = http('POST', "{$rlBase}/save_area.php", $writeBody)[0];
    }
    check('três gravações por hora passam e a quarta recebe 429', $writes === [200, 200, 200, 429], implode(',', $writes));
    [$status, $data] = http('POST', "{$rlBase}/save_area.php", $writeBody);
    check('429 traz error_code rate_limited', $status === 429 && ($data['error_code'] ?? '') === 'rate_limited', json_encode($data));

    $reads = [];
    for ($i = 0; $i < 6; $i++) {
        $reads[] = http('GET', "{$rlBase}/get_areas.php")[0];
    }
    check('cinco leituras por minuto passam e a sexta recebe 429', $reads === [200, 200, 200, 200, 200, 429], implode(',', $reads));
    stopServer($server);
    $server = null;

    echo "Proxy confiável (X-Forwarded-For)\n";
    resetAreas($pdo);
    $proxyPort = 8781;
    $proxyBase = "http://127.0.0.1:{$proxyPort}";
    $server = startServer($root, $proxyPort, ['RATE_LIMIT_WRITES_PER_HOUR' => '2', 'TRUST_PROXY' => '1'], $termsPath);
    $fromA = ['X-Forwarded-For' => '198.51.100.7'];
    $fromB = ['X-Forwarded-For' => '203.0.113.9'];
    $a = [];
    for ($i = 0; $i < 3; $i++) {
        $a[] = http('POST', "{$proxyBase}/save_area.php", $writeBody, $fromA)[0];
    }
    $b = http('POST', "{$proxyBase}/save_area.php", $writeBody, $fromB)[0];
    check('cliente A atinge o limite e recebe 429', $a === [200, 200, 429], implode(',', $a));
    check('cliente B, com outro IP no X-Forwarded-For, não é afetado', $b === 200, (string) $b);

    // Valor à esquerda é controlado pelo cliente: só o último (anexado pelo proxy) conta.
    $spoof = [];
    for ($i = 0; $i < 3; $i++) {
        $spoof[] = http('POST', "{$proxyBase}/save_area.php", $writeBody, [
            'X-Forwarded-For' => "10.0.0.{$i}, 198.51.100.7",
        ])[0];
    }
    check('forjar IP à esquerda não contorna o limite do cliente real', $spoof === [429, 429, 429], implode(',', $spoof));
    stopServer($server);
    $server = null;
} finally {
    if (isset($server) && is_array($server)) {
        stopServer($server);
    }
    resetAreas($pdo);
    @unlink($termsPath);
}

echo "\nResultado: {$passes} ok, {$failures} falhas\n";
exit($failures > 0 ? 1 : 0);
