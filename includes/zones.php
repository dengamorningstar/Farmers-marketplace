<?php
/**
 * Twiga AgroMarket - Zone Configuration
 * NOW POWERED BY DATABASE (SINGLE SOURCE OF TRUTH)
 */

include_once(__DIR__ . "/db.php");

/**
 * Get all active zones
 */
function getZones($conn)
{
    $stmt = $conn->prepare("
        SELECT zone_id, zone_key, zone_label, county
        FROM delivery_zones
        WHERE is_active = 1
        ORDER BY zone_label ASC
    ");

    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get zone label by ID
 */
function getZoneLabel($conn, $zone_id)
{
    $stmt = $conn->prepare("
        SELECT zone_label
        FROM delivery_zones
        WHERE zone_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $zone_id);
    $stmt->execute();

    $res = $stmt->get_result()->fetch_assoc();

    return $res['zone_label'] ?? null;
}

/**
 * Validate zone exists
 */
function isValidZone($conn, $zone_id)
{
    $stmt = $conn->prepare("
        SELECT zone_id
        FROM delivery_zones
        WHERE zone_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $zone_id);
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}
?>