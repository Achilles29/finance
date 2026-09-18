<?php
declare(strict_types=1);
// Test helper only, never bundled or used with Control live credentials.
$f=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR);$root=$f['root'];
require $root.'/tools/install/portable/PortableInstaller.php';
$transport=static function(string $origin,string $path,string $body,array $headers)use($f,$root):array{
    if($origin!=='https://control.example.invalid')throw new RuntimeException('TEST_ORIGIN_INVALID');
    $p=json_decode($body,true);
    if($path===ControlLicenseProtocol::REQUEST_PATH){
        if(!empty($f['quota_denied']))return ['http'=>409,'json'=>['code'=>'instance_limit_exceeded']];
        return ['http'=>202,'json'=>['status'=>'PENDING','activation_id'=>'00000000-0000-4000-8000-000000000099','poll_token'=>'nlp_'.str_repeat('a',48)]];
    }
    if($path===ControlLicenseProtocol::POLL_PATH) {
        $agent=(new PortableStore($root,'private/agent'))->read('agent.json');$id=$agent['identity'];
        $issued=$f['issued'];
        $payload=$id+['schema'=>1,'license_id'=>'disposable-portable','key_id'=>$f['trust']['key_id'],'product'=>'NAMUA_FINANCE',
            'machine_fingerprint_sha256'=>CustomerPlatform::fingerprint(),'edition'=>'STARTER_POS','metric'=>'SERVER_INSTANCE',
            'rights_model'=>'PERPETUAL','entitlements'=>['POS_CORE'=>true],'issued_at'=>gmdate(DATE_ATOM,$issued),
            'expires_at'=>gmdate(DATE_ATOM,$issued+3600),'grace_until'=>gmdate(DATE_ATOM,$issued+7200),'maintenance_ends_at'=>gmdate(DATE_ATOM,$issued-86400)];
        $raw=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $e=['schema'=>1,'algorithm'=>'Ed25519','key_id'=>$f['trust']['key_id'],'payload_base64'=>base64_encode($raw),
            'signature_base64'=>base64_encode(sodium_crypto_sign_detached("NAMUA_LICENSE_V1\n".hash('sha256',$raw),base64_decode($f['issuer'],true)))];
        return ['http'=>200,'json'=>['status'=>'ACTIVE','license'=>$e,'token_sha256'=>hash('sha256',json_encode($e,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))]];
    }
    if(in_array($path,['/api/v1/deployment-receipts','/api/v1/heartbeats'],true)) {
        $head=[];foreach($headers as $h){[$k,$v]=explode(': ',$h,2);$head[$k]=$v;}
        $c=implode("\n",['POST',$path,$p['instance_id'],$head['X-Namua-Key-ID'],$head['X-Namua-Timestamp'],$head['X-Namua-Nonce'],$head['Idempotency-Key'],hash('sha256',$body)]);
        if(!hash_equals(hash_hmac('sha256',$c,$f['monitoring_secret']),$head['X-Namua-Signature']))throw new RuntimeException('TEST_HMAC_INVALID');
        if($path==='/api/v1/deployment-receipts' && ($p['status']??'')!=='SUCCEEDED')throw new RuntimeException('TEST_RECEIPT_INVALID');
        if($path==='/api/v1/heartbeats' && (($p['runtime']['primary_domain']??'')!=='127.0.0.1'||$p['health']!=='OK'))throw new RuntimeException('TEST_HEARTBEAT_INVALID');
        return ['http'=>202,'json'=>['status'=>'accepted']];
    }
    throw new RuntimeException('TEST_UNKNOWN_CONTROL_PATH');
};
try {
    $i=new PortableInstaller($root,$transport);$mode=$argv[2];
    if($mode==='config')$r=DeploymentConfig::forRoot($root)->validateProductionSecretContract();
    elseif($mode==='verify')$r=PortablePackage::verify($root)['version'];
    elseif($mode==='permit')$r=PortablePackage::permit($root,(new PortableStore($root,'private'))->read('delivery-state.json')['context'])['permit_id'];
    elseif($mode==='guard'){$v=Control_license_cache::customer_verification($root,$root.'/storage/customer-installation.json');$r=['verified'=>$v['verified'],'status'=>$v['status'],'code'=>$v['code']];}
    elseif($mode==='db')$r=PortableDatabase::install($root,$f['database'],$f['owner'],$f['release_hash']);
    elseif($mode==='interrupt')$r=PortableDatabase::install($root,$f['database'],$f['owner'],$f['release_hash'],static function(int $n):void{if($n===1){echo json_encode(['ok'=>true,'result'=>'PROCESS_EXIT_AT_DURABLE_CHECKPOINT'])."\n";exit(0);}});
    else $r=$i->$mode();
    echo json_encode(['ok'=>true,'result'=>$r],JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){echo json_encode(['ok'=>false,'code'=>preg_match('/\A[A-Z_]+\z/D',$e->getMessage())?$e->getMessage():'FIXTURE_FAILURE','class'=>get_class($e)])."\n";exit(1);}
