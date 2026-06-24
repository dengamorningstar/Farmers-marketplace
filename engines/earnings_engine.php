<?php

class EarningsEngine {

    /* ======================================================
       INTERNAL: GET TABLE CONFIG
    ====================================================== */
    private static function getConfig($role) {

        switch ($role) {

            case 'farmer':
                return [
                    'table'   => 'earnings',
                    'userCol' => 'farmer_id',
                    'amount'  => 'net_amount'
                ];

            case 'delivery_person':
                return [
                    'table'   => 'delivery_earnings',
                    'userCol' => 'delivery_person_id',
                    'amount'  => 'amount'
                ];

            default:
                return null;
        }
    }

    /* ======================================================
       AVAILABLE BALANCE (ONLY ACTIVE)
    ====================================================== */
    public static function getAvailableBalance($conn, $user_id, $role) {

        $cfg = self::getConfig($role);
        if (!$cfg) return 0;

        $stmt = $conn->prepare("
            SELECT COALESCE(SUM({$cfg['amount']}),0) AS balance
            FROM {$cfg['table']}
            WHERE {$cfg['userCol']} = ?
              AND status = 'active'
        ");

        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc()['balance'] ?? 0;
    }

    /* ======================================================
       LOCK EARNINGS
    ====================================================== */
    public static function lockEarnings($conn, $user_id, $role, $payout_id) {

        $cfg = self::getConfig($role);
        if (!$cfg) return;

        $stmt = $conn->prepare("
            UPDATE {$cfg['table']}
            SET status = 'locked',
                payout_id = ?
            WHERE {$cfg['userCol']} = ?
              AND status = 'active'
              AND payout_id IS NULL
        ");

        $stmt->bind_param("ii", $payout_id, $user_id);
        $stmt->execute();
    }

    /* ======================================================
       UNLOCK EARNINGS
    ====================================================== */
    public static function unlockEarnings($conn, $user_id, $role, $payout_id) {

        $cfg = self::getConfig($role);
        if (!$cfg) return;

        $stmt = $conn->prepare("
            UPDATE {$cfg['table']}
            SET status = 'active',
                payout_id = NULL
            WHERE {$cfg['userCol']} = ?
              AND payout_id = ?
              AND status = 'locked'
        ");

        $stmt->bind_param("ii", $user_id, $payout_id);
        $stmt->execute();
    }

    /* ======================================================
       MARK AS PAID
    ====================================================== */
    public static function markPaid($conn, $user_id, $role, $payout_id) {

        $cfg = self::getConfig($role);
        if (!$cfg) return;

        $stmt = $conn->prepare("
            UPDATE {$cfg['table']}
            SET status = 'paid',
                paid_at = NOW()
            WHERE {$cfg['userCol']} = ?
              AND payout_id = ?
              AND status = 'locked'
        ");

        $stmt->bind_param("ii", $user_id, $payout_id);
        $stmt->execute();
    }

    /* ======================================================
       WALLET SUMMARY (FIXED PROPERLY)
    ====================================================== */
    public static function getWalletSummary($conn, $user_id, $role) {

        $cfg = self::getConfig($role);

        if (!$cfg) return [
            'available' => 0,
            'locked' => 0,
            'paid' => 0,
            'total' => 0
        ];

        $stmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN status='active' THEN {$cfg['amount']} ELSE 0 END),0) AS available,
                COALESCE(SUM(CASE WHEN status='locked' THEN {$cfg['amount']} ELSE 0 END),0) AS locked,
                COALESCE(SUM(CASE WHEN status='paid' THEN {$cfg['amount']} ELSE 0 END),0) AS paid,
                COALESCE(SUM({$cfg['amount']}),0) AS total
            FROM {$cfg['table']}
            WHERE {$cfg['userCol']} = ?
        ");

        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc();
    }

    /* ======================================================
       DASHBOARD STATS
    ====================================================== */
    public static function getDashboardStats($conn, $role, $user_id = null) {

        if ($role === 'farmer') {

            return [
                'products' => self::count($conn, "products", "farmer_id", $user_id),
                'orders'   => self::countFarmerOrders($conn, $user_id),
            ];
        }

        if ($role === 'delivery_person') {

            return [
                'jobs_total'  => self::count($conn, "delivery_assignments", "delivery_person_id", $user_id),
                'jobs_active' => self::countAssignmentStatus($conn, $user_id, ['assigned','picked','in_transit']),
                'jobs_done'   => self::countAssignmentStatus($conn, $user_id, ['delivered']),
                'earnings'    => self::getAvailableBalance($conn, $user_id, $role)
            ];
        }

        if ($role === 'admin') {

            return [
                'users'    => self::countAll($conn, "users"),
                'products' => self::countAll($conn, "products"),
                'orders'   => self::countAll($conn, "orders"),
                'payments' => self::countWhere($conn, "payments", "payment_status", "paid")
            ];
        }

        return [];
    }

    /* ======================================================
       HELPERS
    ====================================================== */
    private static function countAssignmentStatus($conn, $user_id, $statuses) {

        if (empty($statuses)) return 0;

        $in = "'" . implode("','", $statuses) . "'";

        $stmt = $conn->prepare("
            SELECT COUNT(*) c
            FROM delivery_assignments
            WHERE delivery_person_id = ?
              AND status IN ($in)
        ");

        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc()['c'] ?? 0;
    }

    private static function count($conn, $table, $col, $id) {

        $stmt = $conn->prepare("
            SELECT COUNT(*) AS c
            FROM $table
            WHERE $col = ?
        ");

        $stmt->bind_param("i", $id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc()['c'] ?? 0;
    }

    private static function countAll($conn, $table) {

        $res = $conn->query("SELECT COUNT(*) c FROM $table");
        return $res->fetch_assoc()['c'] ?? 0;
    }

    private static function countWhere($conn, $table, $col, $value) {

        $stmt = $conn->prepare("
            SELECT COUNT(*) c
            FROM $table
            WHERE $col = ?
        ");

        $stmt->bind_param("s", $value);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc()['c'] ?? 0;
    }

    private static function countFarmerOrders($conn, $user_id) {

        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT oi.order_id) AS c
            FROM order_items oi
            INNER JOIN products p ON oi.product_id = p.product_id
            WHERE p.farmer_id = ?
        ");

        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc()['c'] ?? 0;
    }
}
?>