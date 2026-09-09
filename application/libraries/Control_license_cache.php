<?php
declare(strict_types=1);
require_once __DIR__ . '/Control_license_verifier.php';

/** Pure cache transitions; the agent owns storage, the web process only reads it. */
final class Control_license_cache
{
    public static function initial(array $identity): array
    {
        return ['schema'=>1, 'purpose'=>'FINANCE_CONTROL_CACHE', 'identity'=>$identity,
            'last_seen_at'=>0, 'synced_at'=>0, 'issued_at'=>0, 'payload_sha256'=>'', 'revoked_at'=>0,
            'connection'=>'UNACTIVATED', 'envelope'=>null];
    }

    public static function assertState(array $cache, array $identity, int $now): void
    {
        if (($cache['schema'] ?? null) !== 1 || ($cache['purpose'] ?? '') !== 'FINANCE_CONTROL_CACHE'
            || ($cache['identity'] ?? null) !== $identity) throw new RuntimeException('CACHE_IDENTITY_INVALID');
        foreach (['last_seen_at','synced_at','issued_at','revoked_at'] as $field) {
            if (!is_int($cache[$field] ?? null) || $cache[$field] < 0) throw new RuntimeException('CACHE_STATE_INVALID');
        }
        if ($now + 300 < $cache['last_seen_at']) throw new RuntimeException('CLOCK_ROLLBACK');
    }

    public static function transition(array $cache, int $http, array $response, array $trust, array $identity, int $now): array
    {
        self::assertState($cache, $identity, $now);
        $next = $cache;
        $next['last_seen_at'] = max($now, $cache['last_seen_at']);
        if ($http === 403 && ($response['code'] ?? '') === 'license_revoked') {
            $next['revoked_at'] = max($cache['revoked_at'], $now);
            $next['connection'] = 'REVOKED';
            return $next; // Retain evidence, never delete the last signed document.
        }
        if ($http === 202 && ($response['status'] ?? '') === 'PENDING') {
            $next['connection'] = 'PENDING';
            return $next;
        }
        if ($http !== 200) {
            $next['connection'] = 'SYNC_UNAVAILABLE';
            return $next; // Offline/auth/server failures do not erase licensed rights.
        }
        $envelope = $response['license'] ?? null;
        if (!is_array($envelope)) throw new RuntimeException('LICENSE_RESPONSE_INVALID');
        // Control hashes this canonical envelope, not the decoded payload alone.
        $envelope = ['schema'=>$envelope['schema'] ?? null,'algorithm'=>$envelope['algorithm'] ?? null,
            'key_id'=>$envelope['key_id'] ?? null,'payload_base64'=>$envelope['payload_base64'] ?? null,
            'signature_base64'=>$envelope['signature_base64'] ?? null];
        $hash = hash('sha256', json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        if (!is_string($response['token_sha256'] ?? null) || !hash_equals($hash, $response['token_sha256'])) throw new RuntimeException('TOKEN_HASH_MISMATCH');
        $verified = Control_license_verifier::verify($envelope, $trust, $identity, $now);
        if (empty($verified['verified'])) throw new RuntimeException('LICENSE_DOCUMENT_INVALID');
        $issued = (new DateTimeImmutable($verified['payload']['issued_at']))->getTimestamp();
        if ($issued < $cache['issued_at']) throw new RuntimeException('LEASE_REPLAY');
        if ($issued === $cache['issued_at'] && $cache['payload_sha256'] !== ''
            && !hash_equals($cache['payload_sha256'], $verified['payload_sha256'])) throw new RuntimeException('LEASE_SEQUENCE_CONFLICT');
        if ($cache['revoked_at'] > 0 && $issued <= $cache['revoked_at']) throw new RuntimeException('REVOKED_LEASE_REPLAY');
        $next['issued_at'] = $issued;
        $next['payload_sha256'] = $verified['payload_sha256'];
        $next['envelope'] = $envelope;
        $next['connection'] = 'SYNCED';
        $next['synced_at'] = $now;
        $next['revoked_at'] = 0;
        return $next;
    }

    public static function verification(array $cache, array $trust, array $identity, ?int $now = null): array
    {
        try {
            $now = $now ?? time();
            self::assertState($cache, $identity, $now);
            if ($cache['revoked_at'] > 0) return ['verified'=>false,'status'=>'REVOKED','code'=>'CONTROL_REVOKED'];
            if (!is_array($cache['envelope'] ?? null)) return ['verified'=>false,'status'=>'UNACTIVATED','code'=>'NO_VERIFIED_DOCUMENT'];
            $v = Control_license_verifier::verify($cache['envelope'], $trust, $identity, $now);
            if (!empty($v['verified']) && (!is_string($cache['payload_sha256'] ?? null)
                || !hash_equals($v['payload_sha256'], $cache['payload_sha256'])
                || (new DateTimeImmutable($v['payload']['issued_at']))->getTimestamp() !== $cache['issued_at'])) throw new RuntimeException('CACHE_WATERMARK_MISMATCH');
            return $v;
        } catch (Throwable $e) {
            return ['verified'=>false,'status'=>'RESTRICTED','code'=>$e instanceof RuntimeException ? $e->getMessage() : 'CACHE_INVALID'];
        }
    }
}
