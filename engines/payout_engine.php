<?php

require_once(__DIR__ . "/earnings_engine.php");

class PayoutEngine {

    /* =========================
       VALIDATION GUARD
    ========================== */
    private static function validate($payout) {
        if (!isset(
            $payout['payout_id'],
            $payout['user_id'],
            $payout['role'],
            $payout['status']
        )) {
            throw new Exception("Invalid payout structure");
        }
    }

    /* =========================
       APPROVE PAYOUT
    ========================== */
    public static function approve($conn, $payout) {

        self::validate($payout);

        $conn->begin_transaction();

        try {

            // idempotency + safety check
            $stmt = $conn->prepare("
                UPDATE payout_requests
                SET status = 'approved',
                    updated_at = NOW()
                WHERE payout_id = ?
                  AND status = 'pending'
            ");

            $stmt->bind_param("i", $payout['payout_id']);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                throw new Exception("Payout already processed or invalid state");
            }

            $conn->commit();

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }

    /* =========================
       REJECT PAYOUT
    ========================== */
    public static function reject($conn, $payout) {

        self::validate($payout);

        $conn->begin_transaction();

        try {

            // only pending or approved can be rejected
            $stmt = $conn->prepare("
                UPDATE payout_requests
                SET status = 'rejected',
                    updated_at = NOW()
                WHERE payout_id = ?
                  AND status IN ('pending','approved')
            ");

            $stmt->bind_param("i", $payout['payout_id']);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                throw new Exception("Payout cannot be rejected in current state");
            }

            // unlock earnings safely
            EarningsEngine::unlockEarnings(
                $conn,
                $payout['user_id'],
                $payout['role'],
                $payout['payout_id']
            );

            $conn->commit();

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }

    /* =========================
       PAY (FINAL STEP)
    ========================== */
    public static function pay($conn, $payout) {

        self::validate($payout);

        $conn->begin_transaction();

        try {

            // only approved payouts can be paid
            $stmt = $conn->prepare("
                UPDATE payout_requests
                SET status = 'paid',
                    paid_at = NOW(),
                    updated_at = NOW()
                WHERE payout_id = ?
                  AND status = 'approved'
            ");

            $stmt->bind_param("i", $payout['payout_id']);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                throw new Exception("Payout not eligible for payment");
            }

            // mark earnings as paid_out
            EarningsEngine::markPaid(
                $conn,
                $payout['user_id'],
                $payout['role'],
                $payout['payout_id']
            );

            $conn->commit();

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
}