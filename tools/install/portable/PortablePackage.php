<?php
declare(strict_types=1);
require_once __DIR__.'/PortableStore.php';
require_once dirname(__DIR__,3).'/application/libraries/Control_license_cache.php';
require_once dirname(__DIR__,2).'/release/CustomerReleaseProfile.php';

final class PortablePackage
{
    /** Public trust is supplied through an administrator-verified Control handoff, NEVER a web upload. */
    public static function verify(string $root): array
    {
        $dir=$root.'/private/delivery';
        $trust=CustomerPlatform::document($root,$dir.'/release-trust.json');
        if(array_diff(array_keys($trust),['schema','product_code','algorithm','status','key_id','public_key_base64','public_key_sha256','created_at']))throw new RuntimeException('PUBLIC_RELEASE_TRUST_ONLY');
        $sig=CustomerPlatform::document($root,$dir.'/release.sig.json');
        CustomerPlatform::path($root,$dir.'/release.json',true);
        $raw=(string)file_get_contents($dir.'/release.json');
        if(strlen($raw)>100000)throw new RuntimeException('RELEASE_OVERSIZE');
        $m=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if(!is_array($m)||($m['distribution_profile_version']??null)!==6)throw new RuntimeException('PROFILE_V6_REQUIRED');
        $c=['schema'=>1,'purpose'=>'FINANCE_CUSTOMER_INSTALLATION','product_code'=>'NAMUA_FINANCE','release_root'=>$root,
            'release_public_id'=>$m['release_public_id']??'','version'=>$m['version']??'','source_commit'=>$m['source_commit']??'',
            'artifact_sha256'=>$m['sha256']??'','release_manifest_sha256'=>hash('sha256',$raw),
            'app_manifest_sha256'=>$m['source_manifest_sha256']??'',
            'source_manifest_sha256'=>$m['customer_content_audit']['source_manifest_sha256']??'',
            'distribution_profile'=>'CUSTOMER_CLEAN','distribution_profile_version'=>6,
            'profile_sha256'=>$m['customer_content_audit']['profile_sha256']??'',
            'machine_fingerprint_sha256'=>CustomerPlatform::fingerprint(),'customer_runtime_guard'=>'FINANCE_CUSTOMER_SERVER_V1',
            'release_manifest_base64'=>base64_encode($raw),'release_signature'=>$sig,'release_trust'=>$trust];
        Control_license_cache::customer_release_proof($c);
        foreach(['source_clean','security_scan','install_test','backup_restore','customer_data_scan','secrets_scan','clean_install','source_untouched'] as $gate) {
            if(($m['verification'][$gate]['status']??'')!=='PASS'||!preg_match('/\A[a-f0-9]{64}\z/D',$m['verification'][$gate]['evidence_sha256']??''))throw new RuntimeException('RELEASE_GATE_MISSING');
        }
        // The immutable original TAR remains evidence inside the same parent folder.
        CustomerPlatform::path($root,$dir.'/package.tar',true);
        if(filesize($dir.'/package.tar')!==($m['size_bytes']??null) || !hash_equals($c['artifact_sha256'],hash_file('sha256',$dir.'/package.tar')))throw new RuntimeException('ARTIFACT_HASH_INVALID');
        CustomerPlatform::path($root,$root.'/RELEASE-MANIFEST.json');
        if(!hash_equals($c['source_manifest_sha256'],hash_file('sha256',$root.'/RELEASE-MANIFEST.json'))
            || !hash_equals($c['app_manifest_sha256'],hash_file('sha256',$root.'/app-manifest.json')))throw new RuntimeException('PACKAGE_MANIFEST_MISMATCH');
        $inner=json_decode(file_get_contents($root.'/RELEASE-MANIFEST.json'),true,64,JSON_THROW_ON_ERROR);
        $profile=CustomerReleaseProfile::fromRoot($root);
        if($profile->version()!==6 || !hash_equals($profile->digest(),$c['profile_sha256']))throw new RuntimeException('PACKAGE_PROFILE_MISMATCH');
        $profile->audit($inner['files']??[]);
        $seen=[];
        foreach($inner['files'] as $entry) {
            $path=$entry['path'];
            if(!ReleasePackagePolicy::relativePathValid($path)||isset($seen[$path]))throw new RuntimeException('PACKAGE_INVENTORY_INVALID');
            CustomerPlatform::path($root,$root.'/'.$path);
            if(!is_file($root.'/'.$path)||filesize($root.'/'.$path)!==$entry['size']||!hash_equals($entry['sha256'],hash_file('sha256',$root.'/'.$path)))throw new RuntimeException('PACKAGE_CORE_MODIFIED');
            $seen[$path]=$entry['sha256'];
        }
        // Runtime state is confined to dedicated non-web directories. No arbitrary source/config exemption.
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
        foreach($it as $file) {
            $rel=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
            if($file->isLink())throw new RuntimeException('PACKAGE_SYMLINK_REJECTED');
            if(!$file->isFile())continue;
            if(isset($seen[$rel])||$rel==='RELEASE-MANIFEST.json'||$rel==='config/customer.json'
                ||str_starts_with($rel,'private/')||str_starts_with($rel,'storage/'))continue;
            // No upload files may precede first installation.
            throw new RuntimeException('PACKAGE_EXTRA_FILE');
        }
        $c['core_sha256']=[];
        foreach(Control_license_cache::customer_core_files(6) as $p)$c['core_sha256'][$p]=$seen[$p]??'';
        foreach(['trust','identity','runtime'] as $p)$c['license_'.($p==='runtime'?'cache':$p).'_file']=$root.'/storage/license/'.$p.'.json';
        return $c;
    }

    /** New delivery envelope contract: Control must implement issuance before live use. No invented API. */
    public static function permit(string $root,array $context, bool $checkExpiry=true): array
    {
        $env=CustomerPlatform::document($root,$root.'/private/delivery/permit.json');
        $raw=base64_decode((string)($env['payload_base64']??''),true);
        $sig=base64_decode((string)($env['signature_base64']??''),true);
        $pk=base64_decode((string)$context['release_trust']['public_key_base64'],true);
        if(!is_string($raw)||strlen($raw)>8192||!is_string($sig)||strlen($sig)!==64
            ||($env['key_id']??'')!==$context['release_trust']['key_id']
            ||!sodium_crypto_sign_verify_detached($sig,"NAMUA_FINANCE_SETUP_V1\n".hash('sha256',$raw),$pk))throw new RuntimeException('SETUP_PERMISSION_INVALID');
        $p=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
        if(($p['purpose']??'')!=='NAMUA_FINANCE_SETUP_V1'||($p['product_code']??'')!=='NAMUA_FINANCE'
            ||($p['release_manifest_sha256']??'')!==$context['release_manifest_sha256']
            ||($p['artifact_sha256']??'')!==$context['artifact_sha256']
            ||($p['profile_sha256']??'')!==$context['profile_sha256']
            ||($p['source_commit']??'')!==$context['source_commit']
            ||($p['release_public_id']??'')!==$context['release_public_id']
            ||($p['profile_version']??null)!==6
            ||!preg_match('/\A[a-z0-9][a-z0-9_-]{2,79}\z/D',$p['instance_id']??'')
            ||!preg_match('/\A[a-f0-9-]{36}\z/D',$p['deployment_id']??'')
            ||!preg_match('/\A[a-f0-9]{64}\z/D',$p['plan_sha256']??'')
            ||!preg_match('/\A[a-f0-9]{64}\z/D',$p['setup_secret_sha256']??'')
            ||!preg_match('/\A[a-f0-9-]{36}\z/D',$p['permit_id']??'')
            ||!in_array($p['environment']??'',['STAGING','DEMO','PRODUCTION'],true)
            ||!is_int($p['issued_at']??null)||!is_int($p['expires_at']??null)||$p['expires_at']<=$p['issued_at']
            ||$p['issued_at']>time()+300)throw new RuntimeException('SETUP_PERMISSION_BINDING_INVALID');
        if($checkExpiry && $p['expires_at']<=time())throw new RuntimeException('SETUP_PERMISSION_EXPIRED');
        return $p;
    }
}
