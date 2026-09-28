<?php

class Inventory
{
    private PDO $pdo;

    private const TRACKED_STATUSES = [
        'available',
        'pending',
        'requested',
        'picked_up',
        'completed',
        'cancelled',
        'expired'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getSummary(): array
    {
        $summary = [
            'totals' => $this->initializeStatusTotals(),
            'by_category' => [],
            'by_city' => [],
            'expiring_soon' => [],
            'last_updated' => gmdate('c')
        ];

        $summary['totals'] = $this->getTotals();
        $summary['by_category'] = $this->getCategoryBreakdown();
        $summary['by_city'] = $this->getCityBreakdown();
        $summary['expiring_soon'] = $this->getExpiringSoon();

        $summary['totals']['approx_available_units'] = array_reduce(
            $summary['by_category'],
            static function ($carry, $categoryRow) {
                return $carry + ($categoryRow['approx_units_available'] ?? 0);
            },
            0.0
        );

        $summary['totals']['approx_available_units'] = round($summary['totals']['approx_available_units'], 2);

        return $summary;
    }

    private function initializeStatusTotals(): array
    {
        $totals = [];
        foreach (self::TRACKED_STATUSES as $status) {
            $totals[$status] = 0;
        }

        $totals['total_active'] = 0;
        $totals['approx_available_units'] = 0.0;
        return $totals;
    }

    private function getTotals(): array
    {
        $totals = $this->initializeStatusTotals();

        $stmt = $this->pdo->query(
            "SELECT status, COUNT(*) AS donation_count\n            FROM food_donations\n            GROUP BY status"
        );

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $status = $row['status'];
            if (isset($totals[$status])) {
                $totals[$status] = (int) $row['donation_count'];
            }
        }

        $totals['total_active'] = array_sum([
            $totals['available'],
            $totals['pending'],
            $totals['requested'],
            $totals['picked_up']
        ]);

        return $totals;
    }

    private function getCategoryBreakdown(): array
    {
        $stmt = $this->pdo->query(
            "SELECT category, status, quantity\n            FROM food_donations\n            WHERE status IN ('available', 'pending', 'requested', 'picked_up')"
        );

        $breakdown = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $category = $row['category'];
            if (!isset($breakdown[$category])) {
                $breakdown[$category] = [
                    'category' => $category,
                    'available' => 0,
                    'pending' => 0,
                    'requested' => 0,
                    'picked_up' => 0,
                    'approx_units_available' => 0.0
                ];
            }

            $status = $row['status'];
            if (isset($breakdown[$category][$status])) {
                $breakdown[$category][$status]++;
            }

            if ($status === 'available') {
                $breakdown[$category]['approx_units_available'] += $this->extractNumericQuantity($row['quantity']);
            }
        }

        usort($breakdown, static function ($a, $b) {
            return $b['available'] <=> $a['available'];
        });

        return array_map(
            static function ($row) {
                $row['approx_units_available'] = round($row['approx_units_available'], 2);
                return $row;
            },
            array_values($breakdown)
        );
    }

    private function getCityBreakdown(): array
    {
        $stmt = $this->pdo->query(
            "SELECT pickup_city, status\n            FROM food_donations\n            WHERE status IN ('available', 'pending', 'requested', 'picked_up')"
        );

        $breakdown = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $city = $row['pickup_city'] ?: 'Unknown';
            if (!isset($breakdown[$city])) {
                $breakdown[$city] = [
                    'city' => $city,
                    'available' => 0,
                    'pending' => 0,
                    'requested' => 0,
                    'picked_up' => 0
                ];
            }

            $status = $row['status'];
            if (isset($breakdown[$city][$status])) {
                $breakdown[$city][$status]++;
            }
        }

        usort($breakdown, static function ($a, $b) {
            return $b['available'] <=> $a['available'];
        });

        return array_values($breakdown);
    }

    private function getExpiringSoon(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, title, quantity, expiration_date, pickup_city,\n                    GREATEST(DATEDIFF(expiration_date, CURDATE()), 0) AS days_left\n            FROM food_donations\n            WHERE status IN ('available', 'pending', 'requested')\n              AND expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)\n            ORDER BY expiration_date ASC\n            LIMIT 5"
        );

        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'quantity' => $row['quantity'],
                'expiration_date' => $row['expiration_date'],
                'city' => $row['pickup_city'] ?: 'Unknown',
                'days_until_expiry' => (int) $row['days_left']
            ];
        }

        return $items;
    }

    private function extractNumericQuantity(?string $quantity): float
    {
        if ($quantity === null) {
            return 0.0;
        }

        if (preg_match('/(\d+(?:[\.,]\d+)?)/', $quantity, $matches)) {
            $normalized = str_replace(',', '.', $matches[1]);
            return (float) $normalized;
        }

        return 0.0;
    }
}