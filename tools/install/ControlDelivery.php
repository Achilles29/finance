<?php
declare(strict_types=1);
require_once __DIR__.'/PrivateDeployment.php';
require_once dirname(__DIR__).'/licensing/ControlLicenseProtocol.php';
require_once dirname(__DIR__).'/release/ControlReleaseBridge.php';

/** Claim/download/receipt client. Does not execute downloaded code or automatically publish a release. */
final class ControlDelivery
{
    private string $dir;
    private array $config;
    public function __construct(string $directory,array $config)
    {
        PrivateDeployment::directory($directory);$this->dir=$directory;$this->config=$config;
        ControlLicenseProtocol::origin($config['control_origin']??'');
        if(preg_match('/\Andi_[A-Za-z0-9_-]{48}\z/D',$config['install_token']??'')!==1
            ||preg_match('/\A[a-z0-9][a-z0-9_-]{2,79}\z/D',$config['instance_id']??'')!==1
            ||preg_match('/\A[a-z0-9][a-z0-9.-]{0,188}[a-z0-9]\z/D',$config['primary_domain']??'')!==1)throw new RuntimeException('DELIVERY_CONFIG_INVALID');
        if(isset($config['ca_file']))LicenseAgentFiles::securePath($config['ca_file'],dirname(__DIR__,2));
    }
    public static function validatePlan(array $p,array $expected): array
    {
        if(($p['status']??'')!=='accepted'||($p['product_code']??'')!=='NAMUA_FINANCE'||($p['instance_id']??'')!==$expected['instance_id']
            ||($p['primary_domain']??'')!==$expected['primary_domain']||!in_array($p['action']??'',['DEPLOY','ROLLBACK'],true)
            ||!in_array($p['environment']??'',['DEMO','STAGING','PRODUCTION'],true)
            ||preg_match('/\A[a-f0-9-]{36}\z/D',$p['deployment_id']??'')!==1||preg_match('/\A[a-f0-9]{64}\z/D',$p['plan_sha256']??'')!==1
            ||!is_array($p['artifacts']??null)||count($p['artifacts'])!==3)throw new RuntimeException('PLAN_BINDING_INVALID');
        foreach(['manifest_sha256','artifact_sha256'] as $key)if(preg_match('/\A[a-f0-9]{64}\z/D',$p['release'][$key]??'')!==1)throw new RuntimeException('PLAN_RELEASE_INVALID');
        if(preg_match('/\A\d+\.\d+\.\d+(?:-[A-Za-z0-9.-]+)?\z/D',$p['release']['version']??'')!==1)throw new RuntimeException('PLAN_RELEASE_INVALID');
        if (array_key_exists('distribution_profile', $p['release'])) {
            if (($p['release']['distribution_profile'] ?? '') !== CustomerReleaseProfile::ID
                || ($p['release']['distribution_profile_version'] ?? null) !== 1
                || ($p['release']['seed_profile'] ?? '') !== 'REFERENCE_ONLY'
                || preg_match('/\A[a-f0-9]{64}\z/D', $p['release']['customer_content_profile_sha256'] ?? '') !== 1) throw new RuntimeException('PLAN_CUSTOMER_PROFILE_INVALID');
        }
        $byType=[];
        foreach($p['artifacts'] as $a){
            if(!is_array($a)||!in_array($a['type']??'',['APPLICATION_PACKAGE','MANIFEST','OTHER'],true)||isset($byType[$a['type']])
                ||preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{1,180}\z/D',$a['filename']??'')!==1||strpos($a['filename'],'..')!==false
                ||preg_match('/\A[a-f0-9]{64}\z/D',$a['sha256']??'')!==1||!is_int($a['size_bytes']??null)||$a['size_bytes']<1
                ||$a['size_bytes']>($a['type']==='APPLICATION_PACKAGE'?ControlReleaseBridge::MAX_BYTES:1048576))throw new RuntimeException('PLAN_ARTIFACT_INVALID');
            $byType[$a['type']]=$a;
        }
        $stem=substr($byType['APPLICATION_PACKAGE']['filename'],0,-4);
        if(substr($byType['APPLICATION_PACKAGE']['filename'],-4)!=='.tar'||$byType['MANIFEST']['filename']!==$stem.'.release.json'||$byType['OTHER']['filename']!==$stem.'.release.sig.json'
            ||$byType['APPLICATION_PACKAGE']['sha256']!==$p['release']['artifact_sha256']||$byType['MANIFEST']['sha256']!==$p['release']['manifest_sha256'])throw new RuntimeException('PLAN_ARTIFACT_BINDING');
        return $byType;
    }
    private function http(string $path,array $payload,array $headers=[],?array $artifact=null): array
    {
        if(!in_array($path,['/api/v1/install-plans/claim','/api/v1/install-plans/artifact','/api/v1/deployment-receipts'],true))throw new RuntimeException('DELIVERY_PATH_INVALID');
        $body=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$response='';$written=0;$h=null;
        $partial=$artifact?$this->dir.'/'.$artifact['filename'].'.partial':null;
        if($partial!==null){if(file_exists($partial)||is_link($partial))throw new RuntimeException('PARTIAL_DOWNLOAD_REQUIRES_REVIEW');$h=fopen($partial,'xb');if(!$h)throw new RuntimeException('DOWNLOAD_FILE_FAILED');chmod($partial,0600);}
        $c=curl_init($this->config['control_origin'].$path);
        curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>$artifact?600:20,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/json','Accept: application/json'], $headers),
            CURLOPT_WRITEFUNCTION=>static function($c,string $chunk)use(&$response,&$written,$h,$artifact):int{
                $n=strlen($chunk);if($written+$n>($artifact?$artifact['size_bytes']:1048576))return 0;
                if($h){if(fwrite($h,$chunk)!==$n)return 0;}else{$response.=$chunk;}$written+=$n;return $n;
            }]);
        if(isset($this->config['ca_file']))curl_setopt($c,CURLOPT_CAINFO,$this->config['ca_file']);
        $ok=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);
        if($h){$flushed=fflush($h)&&fsync($h);fclose($h);if(!$flushed)throw new RuntimeException('DOWNLOAD_FLUSH_FAILED');}
        if($ok===false)throw new RuntimeException('DELIVERY_NETWORK_UNCERTAIN');
        if($status>=300&&$status<400)throw new RuntimeException('DELIVERY_REDIRECT_REJECTED');
        if($artifact){
            if($status!==200||$written!==$artifact['size_bytes']||hash_file('sha256',$partial)!==$artifact['sha256'])throw new RuntimeException('DOWNLOAD_INTEGRITY_FAILED');
            if(!rename($partial,$this->dir.'/'.$artifact['filename']))throw new RuntimeException('DOWNLOAD_PUBLISH_FAILED');
            return ['status'=>'DOWNLOADED'];
        }
        $json=json_decode($response,true,64,JSON_THROW_ON_ERROR);if(!is_array($json))throw new RuntimeException('DELIVERY_RESPONSE_INVALID');
        return ['http'=>$status,'json'=>$json];
    }
    public function fetch(string $trustFile): array
    {
        $lock=PrivateDeployment::lock($this->dir);$file=$this->dir.'/delivery.json';
        try{
            $binding=hash('sha256',json_encode($this->config,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
            $state=is_file($file)?PrivateDeployment::read($file):['schema'=>1,'binding'=>$binding,'phase'=>'NEW','downloads'=>[]];
            if(($state['binding']??'')!==$binding)throw new RuntimeException('DELIVERY_STATE_MISMATCH');
            if($state['phase']==='NEW'){
                $state['phase']='CLAIM_UNCERTAIN';PrivateDeployment::write($file,$state);
                $r=$this->http('/api/v1/install-plans/claim',['install_token'=>$this->config['install_token']]);
                if($r['http']!==200)throw new RuntimeException('CLAIM_REQUIRES_REVIEW');
                self::validatePlan($r['json'],$this->config);$state['plan']=$r['json'];$state['phase']='CLAIMED';PrivateDeployment::write($file,$state);
            }
            if(!isset($state['plan']))throw new RuntimeException('CLAIM_RESPONSE_LOST_REQUIRES_CONTROL_REVIEW');
            $artifacts=self::validatePlan($state['plan'],$this->config);
            foreach(['MANIFEST','OTHER','APPLICATION_PACKAGE'] as $type){$a=$artifacts[$type];$path=$this->dir.'/'.$a['filename'];
                if(is_file($path)&&!is_link($path)&&filesize($path)===$a['size_bytes']&&hash_file('sha256',$path)===$a['sha256']){$state['downloads'][$type]='COMPLETE';continue;}
                if(file_exists($path)||is_link($path)||isset($state['downloads'][$type]))throw new RuntimeException('DOWNLOAD_UNCERTAIN_REQUIRES_CONTROL_REVIEW');
                $state['downloads'][$type]='REQUEST_UNCERTAIN';PrivateDeployment::write($file,$state);
                $this->http('/api/v1/install-plans/artifact',['install_token'=>$this->config['install_token'],'artifact_type'=>$type],[],$a);
                $state['downloads'][$type]='COMPLETE';PrivateDeployment::write($file,$state);
            }
            $manifest=$this->dir.'/'.$artifacts['MANIFEST']['filename'];$bytes=(string)file_get_contents($manifest);
            $verified=ControlReleaseBridge::verify($bytes,basename($manifest),ControlReleaseBridge::json((string)file_get_contents($this->dir.'/'.$artifacts['OTHER']['filename'])),ControlReleaseBridge::loadKey($trustFile),$this->dir.'/'.$artifacts['APPLICATION_PACKAGE']['filename']);
            if($verified['version']!==$state['plan']['release']['version'])throw new RuntimeException('DELIVERED_VERSION_MISMATCH');
            self::validateCustomerBinding($state['plan'], $verified);
            $state['phase']='VERIFIED';$state['verification']=$verified;PrivateDeployment::write($file,$state);
            return ['status'=>'VERIFIED','version'=>$verified['version'],'signed_manifest'=>$manifest,'deployment_id'=>$state['plan']['deployment_id'],'installed'=>false];
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public static function validateCustomerBinding(array $plan, array $verified): void
    {
        $release = $plan['release'] ?? [];
        if (!empty($verified['customer_clean_eligible'])) {
            foreach (['distribution_profile', 'distribution_profile_version', 'seed_profile'] as $key) {
                if (($release[$key] ?? null) !== ($verified[$key] ?? null)) throw new RuntimeException('DELIVERED_CUSTOMER_PROFILE_MISMATCH');
            }
            if (($release['customer_content_profile_sha256'] ?? '') !== ($verified['customer_content_audit']['profile_sha256'] ?? null)) throw new RuntimeException('DELIVERED_CUSTOMER_PROFILE_MISMATCH');
        } elseif (isset($release['distribution_profile'])) throw new RuntimeException('DELIVERED_CUSTOMER_PROFILE_MISMATCH');
    }

    public function receipt(array $identity,array $result): array
    {
        $lock=PrivateDeployment::lock($this->dir);
        try{
            $state=PrivateDeployment::read($this->dir.'/delivery.json');$p=$state['plan']??[];
            self::validatePlan($p,$this->config);
            if(($state['binding']??'')!==hash('sha256',json_encode($this->config,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))
                ||($state['phase']??'')!=='VERIFIED'||($identity['instance_id']??'')!==($p['instance_id']??null)||($identity['environment']??'')!==($p['environment']??null)
                ||preg_match('/\A[a-f0-9-]{36}\z/D',$identity['key_id']??'')!==1||!is_string($identity['secret']??null)||strlen($identity['secret'])<32
                ||($result['status']??'')!=='PASS'||($result['web_verified']??false)!==true||($result['version']??'')!==$p['release']['version']
                ||($result['artifact_sha256']??'')!==$p['release']['artifact_sha256']||($result['health']['status']??'')!=='ok')throw new RuntimeException('RECEIPT_EVIDENCE_REQUIRED');
            $path=$this->dir.'/receipt.json';$from=$result['from_schema']??'';$to=$result['to_schema']??'';
            if(preg_match('/\A[A-Za-z0-9._-]{1,80}\z/D',$from)!==1||preg_match('/\A[A-Za-z0-9._-]{1,80}\z/D',$to)!==1)throw new RuntimeException('RECEIPT_SCHEMA_REQUIRED');
            if(is_file($path))$r=PrivateDeployment::read($path);else{
                $r=['receipt_id'=>PrivateDeployment::uuid(),'instance_id'=>$identity['instance_id'],'occurred_at'=>gmdate(DATE_ATOM),
                    'receipt_type'=>$p['action']==='ROLLBACK'?'RELEASE_ROLLBACK':'RELEASE_ACTIVATION','status'=>'SUCCEEDED','environment'=>$p['environment'],
                    'app_version'=>$p['release']['version'],'from_schema'=>$from,'to_schema'=>$to,'migration_versions'=>$result['migration_versions']??[],
                    'release_manifest_sha256'=>$p['release']['manifest_sha256'],'artifact_sha256'=>$p['release']['artifact_sha256'],'backup_sha256'=>$result['backup_sha256']??null];
                PrivateDeployment::write($path,$r);
            }
            $timestamp=gmdate(DATE_ATOM);$nonce=bin2hex(random_bytes(24));$idempotency='finance-receipt-'.$r['receipt_id'];
            $canonical=implode("\n",['POST','/api/v1/deployment-receipts',$identity['instance_id'],$identity['key_id'],$timestamp,$nonce,$idempotency,hash('sha256',json_encode($r,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))]);
            $answer=$this->http('/api/v1/deployment-receipts',$r,['X-Namua-Instance-ID: '.$identity['instance_id'],'X-Namua-Key-ID: '.$identity['key_id'],
                'X-Namua-Timestamp: '.$timestamp,'X-Namua-Nonce: '.$nonce,'Idempotency-Key: '.$idempotency,'X-Namua-Signature: '.hash_hmac('sha256',$canonical,$identity['secret'])]);
            if(!in_array($answer['http'],[200,202],true)||($answer['json']['status']??'')!=='accepted')throw new RuntimeException('RECEIPT_NOT_ACCEPTED');
            PrivateDeployment::write($this->dir.'/receipt-ack.json',$answer['json']);return ['status'=>'ACKNOWLEDGED','duplicate'=>$answer['json']['duplicate']??false];
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
