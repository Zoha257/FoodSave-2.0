<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/classes/Inventory.php';

class InventoryController
{
    public function summary(): void
    {
        try {
            $pdo = getDBConnection();
            $inventory = new Inventory($pdo);
            $summary = $inventory->getSummary();

            echo json_encode([
                'success' => true,
                'data' => $summary
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Unable to load inventory summary.',
                'details' => $e->getMessage()
            ]);
        }
    }
}
