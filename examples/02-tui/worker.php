<?php

/**
 * Worker script for the TUI example.
 *
 * Simulates a multi-stage task: init → process (with progress) → finalize.
 */
file_get_contents('php://stdin'); // consume payload

usleep(400000);
echo json_encode(['type' => 'initialized'])."\n";

$steps = 5;
for ($i = 1; $i <= $steps; ++$i) {
    usleep(500000);
    echo json_encode(['type' => 'processing', 'step' => $i, 'total' => $steps])."\n";
}

usleep(400000);
echo json_encode(['type' => 'finalized'])."\n";

echo json_encode(['type' => 'done'])."\n";
