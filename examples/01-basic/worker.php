<?php

/**
 * Worker script for the basic example.
 *
 * Receives a JSON payload on stdin, simulates work,
 * and outputs JSON progress events on stdout.
 */

/** @var array{steps?: int} $params */
$params = json_decode((string) file_get_contents('php://stdin'), true) ?? [];
$steps = $params['steps'] ?? 4;

for ($i = 1; $i <= $steps; ++$i) {
    sleep(1);
    echo json_encode([
        'type' => 'progress',
        'step' => $i,
        'total' => $steps,
        'label' => sprintf('Completed step %d of %d', $i, $steps),
    ])."\n";
}

echo json_encode(['type' => 'done'])."\n";
