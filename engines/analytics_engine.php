<?php

/* =========================
   SAFE QUERY HELPER
========================= */
function safeFetch($conn, $sql) {

    $res = $conn->query($sql);
    if (!$res) return [];

    return $res->fetch_assoc() ?: [];
}

/* =========================
   REVENUE KPI (GLOBAL - unchanged)
========================= */
function getRevenueKPI($conn) {

    $data = safeFetch($conn, "
        SELECT
            COUNT(*) AS total_orders,
            COALESCE(SUM(amount),0) AS revenue
        FROM payments
        WHERE payment_status='paid'
    ");

    $orders = intval($data['total_orders'] ?? 0);
    $revenue = floatval($data['revenue'] ?? 0);

    $avg = $orders > 0 ? $revenue / $orders : 0;

    return [
        "total_revenue" => round($revenue,2),
        "total_orders" => $orders,
        "avg_order_value" => round($avg,2)
    ];
}

/* =========================
   CONVERSION KPI (GLOBAL)
========================= */
function getConversionKPI($conn) {

    $data = safeFetch($conn, "
        SELECT
            (SELECT COUNT(*) FROM orders) AS total_orders,
            (SELECT COUNT(*) FROM payments WHERE payment_status='paid') AS paid_orders
    ");

    $total = intval($data['total_orders'] ?? 0);
    $paid = intval($data['paid_orders'] ?? 0);

    $rate = $total > 0 ? ($paid / $total) * 100 : 0;

    return [
        "conversion_rate" => round($rate,2),
        "total_orders" => $total,
        "paid_orders" => $paid
    ];
}

/* =========================
   DELIVERY KPI (NEW SYSTEM ONLY)
========================= */
function getDeliveryKPI($conn, $startDate, $endDate) {

    $data = safeFetch($conn, "
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status='assigned' THEN 1 ELSE 0 END) AS assigned,
            SUM(CASE WHEN status='accepted' THEN 1 ELSE 0 END) AS accepted,
            SUM(CASE WHEN status='picked_up' THEN 1 ELSE 0 END) AS picked,
            SUM(CASE WHEN status='in_transit' THEN 1 ELSE 0 END) AS in_transit,
            SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) AS delivered
        FROM delivery_assignments
        WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate'
    ");

    $total = intval($data['total'] ?? 0);
    $delivered = intval($data['delivered'] ?? 0);

    $success = $total > 0 ? ($delivered / $total) * 100 : 0;

    return [
        "total_deliveries" => $total,
        "assigned" => intval($data['assigned'] ?? 0),
        "accepted" => intval($data['accepted'] ?? 0),
        "picked" => intval($data['picked'] ?? 0),
        "in_transit" => intval($data['in_transit'] ?? 0),
        "delivered" => $delivered,
        "success_rate" => round($success,2)
    ];
}

/* =========================
   RIDER KPI
========================= */
function getRiderKPI($conn) {

    $data = safeFetch($conn, "
        SELECT
            COUNT(DISTINCT delivery_person_id) AS riders,
            COUNT(*) AS deliveries
        FROM delivery_assignments
    ");

    return [
        "active_riders" => intval($data['riders'] ?? 0),
        "total_deliveries" => intval($data['deliveries'] ?? 0)
    ];
}

/* =========================
   FARMER KPI
========================= */
function getFarmerKPI($conn) {

    $data = safeFetch($conn, "
        SELECT
            COUNT(DISTINCT p.farmer_id) AS farmers,
            COALESCE(SUM(oi.price * oi.quantity),0) AS revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
    ");

    return [
        "active_farmers" => intval($data['farmers'] ?? 0),
        "potential_revenue" => round(floatval($data['revenue'] ?? 0),2)
    ];
}

/* =========================
   TOP PRODUCTS
========================= */
function getTopProducts($conn) {

    $res = $conn->query("
        SELECT
            p.name,
            SUM(oi.quantity) AS value
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        GROUP BY oi.product_id
        ORDER BY value DESC
        LIMIT 5
    ");

    $out = [];
    while ($res && $r = $res->fetch_assoc()) {
        $out[] = $r;
    }

    return $out;
}

/* =========================
   ZONE PERFORMANCE (NEW SYSTEM ONLY)
========================= */
function getZonePerformance($conn, $startDate, $endDate) {

    $res = $conn->query("
        SELECT
            dz.zone_label,
            COUNT(*) AS deliveries,
            SUM(CASE WHEN da.status='delivered' THEN 1 ELSE 0 END) AS completed
        FROM delivery_assignments da
        LEFT JOIN delivery_zones dz ON da.zone_id = dz.zone_id
        WHERE DATE(da.created_at) BETWEEN '$startDate' AND '$endDate'
        GROUP BY da.zone_id
        ORDER BY deliveries DESC
    ");

    $out = [];
    while ($res && $r = $res->fetch_assoc()) {
        $out[] = $r;
    }

    return $out;
}

/* =========================
   REVENUE TREND
========================= */
function getRevenueTrend($conn, $startDate, $endDate) {

    $res = $conn->query("
        SELECT
            DATE(created_at) AS day,
            COALESCE(SUM(amount),0) AS revenue
        FROM payments
        WHERE payment_status='paid'
        AND DATE(created_at) BETWEEN '$startDate' AND '$endDate'
        GROUP BY DATE(created_at)
        ORDER BY day ASC
    ");

    $out = [];
    while ($res && $r = $res->fetch_assoc()) {
        $out[] = $r;
    }

    return $out;
}

/* =========================
   ORDERS TREND
========================= */
function getOrdersTrend($conn, $startDate, $endDate) {

    $res = $conn->query("
        SELECT
            DATE(created_at) AS day,
            COUNT(*) AS total
        FROM orders
        WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate'
        GROUP BY DATE(created_at)
        ORDER BY day ASC
    ");

    $out = [];
    while ($res && $r = $res->fetch_assoc()) {
        $out[] = $r;
    }

    return $out;
}

/* =========================
   DELIVERY BREAKDOWN
========================= */
function getDeliveryBreakdown($conn, $startDate, $endDate) {

    $res = $conn->query("
        SELECT
            status,
            COUNT(*) AS total
        FROM delivery_assignments
        WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate'
        GROUP BY status
    ");

    $out = [];
    while ($res && $r = $res->fetch_assoc()) {
        $out[] = $r;
    }

    return $out;
}

/* =========================
   TOP FARMERS
========================= */
function getTopFarmers($conn) {

    $res = $conn->query("
        SELECT
            u.name,
            COALESCE(SUM(e.net_amount),0) AS revenue
        FROM earnings e
        INNER JOIN users u ON e.farmer_id = u.user_id
        WHERE e.status IN ('active','locked','paid')
        GROUP BY e.farmer_id
        ORDER BY revenue DESC
        LIMIT 5
    ");

    $out = [];
    while ($res && $r = $res->fetch_assoc()) {
        $out[] = $r;
    }

    return $out;
}
?>