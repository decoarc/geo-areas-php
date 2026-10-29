<?php
/**
 * Limpeza periódica da demo: remove áreas com mais de AREA_RETENTION_DAYS dias
 * e contadores de rate limit antigos.
 *
 * Só roda pela linha de comando:  php cleanup_areas.php
 *
 * A gravação de áreas já faz essa limpeza, então este script é opcional. Use-o
 * se quiser uma rotina agendada à parte (por exemplo, um cron externo).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Execute apenas pela linha de comando.\n");
}

require __DIR__ . '/connection.php';
require __DIR__ . '/geo_helpers.php';

try {
    $conn->beginTransaction();
    $areas = purgeExpiredAreas($conn);
    $rates = purgeExpiredRateLimits($conn);
    $conn->commit();
} catch (Throwable $e) {
    // Falha no meio não apaga nada: a transação inteira é desfeita.
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    fwrite(STDERR, 'cleanup_areas: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "áreas removidas: {$areas}; contadores de rate limit removidos: {$rates}\n";
