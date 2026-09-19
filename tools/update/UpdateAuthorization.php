<?php
declare(strict_types=1);

/** PROPOSED Control contract. Verification only; never activates an installation or changes entitlement. */
final class UpdateAuthorization
{
    public const PURPOSE='FINANCE_CUSTOMER_UPDATE_V1';
    public static function releaseBinding(array $context): array
    {
        $out=[];
        foreach(['release_public_id','version','source_commit','artifact_sha256','release_manifest_sha256','source_manifest_sha256','profile_sha256','distribution_profile_version'] as $k) {
            if(!isset($context[$k]))throw new RuntimeException('UPDATE_RELEASE_BINDING_INCOMPLETE');
            $out[$k]=$context[$k];
        }
        return $out;
    }
    public static function verify(array $envelope,array $trust,array $current,array $target,array $identity,int $now): array
    {
        if(($trust['status']??'')!=='ACTIVE'||($trust['algorithm']??'')!=='Ed25519'||($trust['product_code']??'')!=='NAMUA_FINANCE'
            ||isset($trust['secret_key_base64'])||isset($trust['private_key']))throw new RuntimeException('UPDATE_TRUST_INVALID');
        $pk=base64_decode((string)($trust['public_key_base64']??''),true);
        $raw=base64_decode((string)($envelope['payload_base64']??''),true);
        $sig=base64_decode((string)($envelope['signature_base64']??''),true);
        if(!is_string($pk)||strlen($pk)!==32||($trust['public_key_sha256']??'')!==hash('sha256',$pk)
            ||!is_string($raw)||strlen($raw)>16384||!is_string($sig)||strlen($sig)!==64
            ||($envelope['key_id']??null)!==($trust['key_id']??null)
            ||!sodium_crypto_sign_verify_detached($sig,self::PURPOSE."\n".hash('sha256',$raw),$pk))throw new RuntimeException('UPDATE_SIGNATURE_INVALID');
        $p=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if(!is_array($p)||array_diff(array_keys($p),['purpose','product_code','authorization_id','nonce','issued_at','expires_at','identity','machine_fingerprint_sha256','from','to','migration_policy'])
            ||($p['purpose']??null)!==self::PURPOSE||($p['product_code']??null)!=='NAMUA_FINANCE'
            ||!preg_match('/\A[a-f0-9-]{36}\z/D',$p['authorization_id']??'')||!preg_match('/\A[a-f0-9]{64}\z/D',$p['nonce']??'')
            ||!is_int($p['issued_at']??null)||!is_int($p['expires_at']??null)||$p['issued_at']>$now+300||$p['expires_at']<=$p['issued_at'])throw new RuntimeException('UPDATE_AUTHORIZATION_INVALID');
        if($p['expires_at']<=$now)throw new RuntimeException('UPDATE_AUTHORIZATION_EXPIRED_REISSUE_SAME_ATTEMPT');
        // A short-lived update credential is not an installation deadline. Setup policy is unchanged.
        foreach(['instance_id','installation_id','instance_public_key_sha256'] as $k)if(!is_string($identity[$k]??null)||$identity[$k]===''||($p['identity'][$k]??null)!==$identity[$k])throw new RuntimeException('UPDATE_IDENTITY_MISMATCH');
        if(count($p['identity'])!==3||!preg_match('/\A[a-f0-9]{64}\z/D',$identity['instance_public_key_sha256']))throw new RuntimeException('UPDATE_IDENTITY_MISMATCH');
        if(($p['machine_fingerprint_sha256']??null)!==($current['machine_fingerprint_sha256']??null)
            ||!is_string($p['machine_fingerprint_sha256'])||!preg_match('/\A[a-f0-9]{64}\z/D',$p['machine_fingerprint_sha256']))throw new RuntimeException('UPDATE_FINGERPRINT_MISMATCH');
        if(($p['from']??null)!==self::releaseBinding($current)||($p['to']??null)!==self::releaseBinding($target)
            ||($p['migration_policy']??null)!=='upgrade')throw new RuntimeException('UPDATE_RELEASE_MISMATCH');
        if($current['release_manifest_sha256']===$target['release_manifest_sha256']||version_compare($target['version'],$current['version'],'<='))throw new RuntimeException('UPDATE_FORWARD_VERSION_REQUIRED');
        return $p;
    }
}
