<?php
declare(strict_types=1);
require_once __DIR__.'/PrivateDeployment.php';
require_once __DIR__.'/LinuxWebProfile.php';
require_once dirname(__DIR__,2).'/application/libraries/Control_license_verifier.php';
if(!defined('C3_CLEAN_INSTALL_LIBRARY_ONLY'))define('C3_CLEAN_INSTALL_LIBRARY_ONLY',true);
require_once __DIR__.'/clean_install_database.php';

/** Verified code and isolated services. No deletion of releases, uploads or databases. */
final class FinanceInstance
{
    private array $c;
    public function __construct(array $c)
    {
        foreach(['private_dir','runtime_dir','release_root','signed_manifest','trust_file','deployment_file','defaults_extra_file','database_name_file','php_fpm','nginx','user','group','port','tls_certificate','tls_key','mime_types','mode'] as $key)if(!array_key_exists($key,$c))throw new RuntimeException('INSTANCE_CONFIG_INCOMPLETE');
        PrivateDeployment::directory($c['private_dir']);
        LicenseAgentFiles::securePath($c['runtime_dir'],dirname(__DIR__,2),true);
        if (!in_array($c['mode'],['clean_install','upgrade'],true)||realpath(dirname($c['release_root']))!==dirname($c['release_root'])
            ||preg_match('~\A/[A-Za-z0-9_./-]+\z~D',$c['release_root'])!==1||strpos($c['release_root'],'..')!==false
            ||$c['release_root']===$c['runtime_dir']||str_starts_with($c['runtime_dir'].'/',$c['release_root'].'/'))throw new RuntimeException('INSTANCE_BOUNDARY_INVALID');
        LicenseAgentFiles::securePath(dirname($c['release_root']),dirname(__DIR__,2),true);
        foreach(['php_fpm','nginx']as$key)if(realpath($c[$key])!==$c[$key]||!is_executable($c[$key])||fileowner($c[$key])!==0||(fileperms($c[$key])&0022)!==0)throw new RuntimeException('INSTANCE_BINARY_UNSAFE');
        $account=posix_getpwnam($c['user']);$group=posix_getgrnam($c['group']);
        if(!$account||!$group||$account['uid']===0||$group['gid']===0)throw new RuntimeException('INSTANCE_ACCOUNT_INVALID');
        $this->c=$c+['uid'=>$account['uid'],'gid'=>$group['gid']];
    }
    private function descriptor(): string{return $this->c['private_dir'].'/instance.json';}
    private function manifest(): array
    {
        $path=$this->c['signed_manifest'];financeArtifactSignatureRegularFile($path,'MANIFEST_UNSAFE');
        $bytes=(string)file_get_contents($path);$m=ControlReleaseBridge::json($bytes);
        if(substr($path,-13)!=='.release.json'||!is_string($m['artifact']??null)||basename($m['artifact'])!==$m['artifact'])throw new RuntimeException('MANIFEST_INVALID');
        ControlReleaseBridge::verify($bytes,basename($path),ControlReleaseBridge::json((string)file_get_contents(substr($path,0,-13).'.release.sig.json')),
            ControlReleaseBridge::loadKey($this->c['trust_file']),dirname($path).'/'.$m['artifact']);return $m;
    }
    public function stage(): array
    {
        $lock=PrivateDeployment::lock($this->c['private_dir']);
        try{
            if(file_exists($this->descriptor())||is_link($this->descriptor()))throw new RuntimeException('INSTANCE_STATE_EXISTS');
            $m=$this->manifest();$app=$this->c['release_root'];
            if(file_exists($app)||is_link($app))throw new RuntimeException('RELEASE_TARGET_EXISTS');
            mkdir($app,0755);chmod($app,0755);$mask=umask(0022);
            try{PrivateDeployment::run([financeArtifactSignatureTarBinary(),'--extract','--file='.dirname($this->c['signed_manifest']).'/'.$m['artifact'],'--directory='.$app,'--no-same-owner','--no-same-permissions'],$this->c['private_dir'].'/extract.log',120);}finally{umask($mask);}
            $release=a513_validate_release($app,$app.'/RELEASE-MANIFEST.json');
            if($release['manifest_sha256']!==$m['source_manifest_sha256'])throw new RuntimeException('EXTRACTED_SOURCE_MISMATCH');
            $r=['status'=>'STAGED','version'=>$m['version'],'artifact_sha256'=>$m['sha256'],'source_commit'=>$m['source_commit'],'source_files'=>count($release['manifest']['files']),'database_changed'=>false];
            PrivateDeployment::write($this->descriptor(),$r+['config_sha256'=>hash('sha256',json_encode($this->c))]);return $r;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    private function assertDescriptor(): array
    {
        $d=PrivateDeployment::read($this->descriptor());
        if(($d['config_sha256']??'')!==hash('sha256',json_encode($this->c)))throw new RuntimeException('INSTANCE_CONFIGURATION_CHANGED');return $d;
    }
    public function install(): array
    {
        $lock=PrivateDeployment::lock($this->c['private_dir']);
        try{
            $d=$this->assertDescriptor();if($d['status']!=='STAGED')throw new RuntimeException('INSTALL_ALREADY_ATTEMPTED');
            $c=$this->c;$m=$this->manifest();$dbName=a5_read_database_name(dirname(__DIR__,2),$c['database_name_file']);
            $option=a5_assert_apply_security(dirname(__DIR__,2),$c['defaults_extra_file']);
            $settings=Control_license_verifier::deployment_document($c['deployment_file'], $c['release_root']);
            $login=parse_ini_file($option,true,INI_SCANNER_RAW)['client']??[];
            if(($settings['FINANCE_DB_NAME']??'')!==$dbName||($settings['FINANCE_DB_USER']??'')!==($login['user']??null)
                ||!is_string($settings['FINANCE_DB_PASSWORD']??null)||!hash_equals((string)($login['password']??''),$settings['FINANCE_DB_PASSWORD'])
                ||strlen($settings['FINANCE_ENCRYPTION_KEY']??'')<32)throw new RuntimeException('WEB_DATABASE_BINDING_MISMATCH');
            $expectedHost=($login['protocol']??'')==='socket'?'localhost':($login['host']??'localhost');
            if(($settings['FINANCE_DB_HOST']??'')!==$expectedHost)throw new RuntimeException('WEB_DATABASE_ENDPOINT_MISMATCH');
            // Validate all local inputs before the first SQL statement that changes schema.
            if (!isset($c['composer']) || realpath($c['composer'])!==$c['composer'] || !is_file($c['composer']) || fileowner($c['composer'])!==0 || (fileperms($c['composer'])&0022)!==0) throw new RuntimeException('COMPOSER_BINARY_REQUIRED');
            if ($c['mode']==='clean_install') {
                if (!isset($c['owner_file'])) throw new RuntimeException('FIRST_OWNER_FILE_REQUIRED');
                a512_owner_file(dirname(__DIR__,2),$c['owner_file']);
            } elseif (!isset($c['backup_file'],$c['backup_sha256']) || !is_file($c['backup_file']) || is_link($c['backup_file']) || !hash_equals(hash_file('sha256',$c['backup_file']),$c['backup_sha256'])) {
                throw new RuntimeException('VERIFIED_UPGRADE_BACKUP_REQUIRED');
            }
            $previous=null;
            if ($c['mode']==='upgrade') {
                if (!isset($c['previous_config_file'])) throw new RuntimeException('PREVIOUS_INSTANCE_REQUIRED');
                $previous=PrivateDeployment::read($c['previous_config_file']);
                $before=PrivateDeployment::read($previous['private_dir'].'/instance.json');
                $oldSettings=Control_license_verifier::deployment_document($previous['deployment_file'],$previous['release_root']);
                if (($before['status']??'')!=='STOPPED' || ($oldSettings['FINANCE_DB_NAME']??'')===$dbName
                    || ($oldSettings['FINANCE_ENCRYPTION_KEY']??'')!==$settings['FINANCE_ENCRYPTION_KEY']
                    || ($oldSettings['FINANCE_BASE_URL']??'')!==($settings['FINANCE_BASE_URL']??null)) throw new RuntimeException('QUIESCED_DATABASE_COPY_REQUIRED');
            }
            if (filegroup($c['runtime_dir'])!==$c['gid'] || (fileperms($c['runtime_dir'])&0050)!==0050) throw new RuntimeException('RUNTIME_SERVICE_GROUP_REQUIRED');
            foreach (['sessions','logs','cache','tmp'] as $dir) if (file_exists($c['runtime_dir'].'/'.$dir)||is_link($c['runtime_dir'].'/'.$dir)) throw new RuntimeException('FRESH_RUNTIME_DIRECTORY_REQUIRED');
            foreach (['SESSION'=>'sessions','LOG'=>'logs','CACHE'=>'cache'] as $key=>$dir) if (($settings['FINANCE_'.$key.'_PATH']??'')!==$c['runtime_dir'].'/'.$dir) throw new RuntimeException('RUNTIME_DIRECTORY_BINDING_MISMATCH');
            $profile=['app_root'=>$c['release_root'],'state_root'=>$c['runtime_dir'],'deployment_file'=>$c['deployment_file'],'tls_certificate'=>$c['tls_certificate'],'tls_key'=>$c['tls_key'],'mime_types'=>$c['mime_types'],'user'=>$c['user'],'group'=>$c['group'],'port'=>$c['port'],'daemonize'=>true];
            foreach(['lua_root','license_public_dir']as$key)if(isset($c[$key]))$profile[$key]=$c[$key];
            $configs=LinuxWebProfile::render($profile);
            foreach (array_keys($configs) as $name) if (file_exists($c['runtime_dir'].'/'.$name)||is_link($c['runtime_dir'].'/'.$name)) throw new RuntimeException('SERVICE_CONFIG_EXISTS');
            $d['status']='INSTALLING';PrivateDeployment::write($this->descriptor(),$d);
            if($c['mode']==='clean_install'){
                if(!isset($c['owner_file']))throw new RuntimeException('FIRST_OWNER_FILE_REQUIRED');
                $install=c3InstallDatabase(['release-root'=>$c['release_root'],'signed-manifest'=>$c['signed_manifest'],'trust-file'=>$c['trust_file'],'defaults-extra-file'=>$option,'database-name-file'=>$c['database_name_file'],'owner-file'=>$c['owner_file']]);
                $health=$install['health'];
            }else{
                $release=a513_validate_release($c['release_root'],$c['release_root'].'/RELEASE-MANIFEST.json');
                $client=a5_client_open(a5_find_client(),$option,$dbName,microtime(true)+30);
                try{a5_client_send($client,"SELECT CONCAT('__C3_VERSION__\\t',VERSION());");c3InstallDatabaseVersion(a5_client_marker($client,'__C3_VERSION__'),$m['runtime']['database']);}finally{a5_client_close($client,true);}
                a5_apply($release['catalog'],$release['root'],'upgrade',$option,$dbName);
                $health=a513_check_database($release,'upgrade',$option,$dbName);
            }
            foreach(['sessions','logs','cache','tmp']as$dir){$p=$c['runtime_dir'].'/'.$dir;if(file_exists($p)||is_link($p))throw new RuntimeException('FRESH_RUNTIME_DIRECTORY_REQUIRED');mkdir($p,0700);chown($p,$c['uid']);chgrp($p,$c['gid']);}
            foreach(['SESSION'=>'sessions','LOG'=>'logs','CACHE'=>'cache']as$key=>$dir)if(($settings['FINANCE_'.$key.'_PATH']??'')!==$c['runtime_dir'].'/'.$dir)throw new RuntimeException('RUNTIME_DIRECTORY_BINDING_MISMATCH');
            require_once dirname(__DIR__,2).'/application/libraries/Upload_storage_policy.php';
            foreach(array_keys(Upload_storage_policy::DIRECTORIES)as$relative){$p=$c['release_root'];foreach(explode('/',$relative)as$part){$p.='/'.$part;if(is_link($p))throw new RuntimeException('UPLOAD_LINK_REJECTED');if(!is_dir($p)){mkdir($p,0755);chmod($p,0755);}}chown($p,$c['uid']);chgrp($p,$c['gid']);chmod($p,0770);}
            $uploads=[];
            if ($previous!==null) foreach(array_keys(Upload_storage_policy::DIRECTORIES) as $relative) {
                $from=$previous['release_root'].'/'.$relative;$to=$c['release_root'].'/'.$relative;
                if (!is_dir($from)||is_link($from)) throw new RuntimeException('PREVIOUS_UPLOAD_UNSAFE');
                $walk=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
                foreach($walk as $entry){
                    if($entry->isLink())throw new RuntimeException('UPLOAD_LINK_REJECTED');
                    $rel=substr($entry->getPathname(),strlen($from)+1);$dst=$to.'/'.$rel;
                    if($entry->isDir()){if(!is_dir($dst))mkdir($dst,0770);chown($dst,$c['uid']);chgrp($dst,$c['gid']);chmod($dst,0770);continue;}
                    if(!$entry->isFile())throw new RuntimeException('UPLOAD_NOT_REGULAR');
                    $hash=hash_file('sha256',$entry->getPathname());
                    if(file_exists($dst)){if(is_link($dst)||!is_file($dst)||!hash_equals($hash,hash_file('sha256',$dst)))throw new RuntimeException('UPLOAD_COLLIDES_WITH_RELEASE');}
                    else{if(!copy($entry->getPathname(),$dst)||!hash_equals($hash,hash_file('sha256',$dst)))throw new RuntimeException('UPLOAD_COPY_FAILED');chown($dst,$c['uid']);chgrp($dst,$c['gid']);chmod($dst,0660);}
                    $uploads[$relative.'/'.$rel]=$hash;
                }
            }
            PrivateDeployment::write($c['private_dir'].'/preserved-uploads.json',$uploads);
            // Current package has no production Composer dependencies. Generate its autoloader explicitly.
            if(!isset($c['composer'])||!is_file($c['composer'])||fileowner($c['composer'])!==0||(fileperms($c['composer'])&0022)!==0)throw new RuntimeException('COMPOSER_BINARY_REQUIRED');
            PrivateDeployment::run([PHP_BINARY,$c['composer'],'install','--working-dir='.$c['release_root'],'--no-dev','--no-interaction','--no-progress','--no-plugins'],$c['private_dir'].'/composer.log',180);
            foreach($configs as$name=>$bytes){$path=$c['runtime_dir'].'/'.$name;$h=fopen($path,'xb');if(!$h||fwrite($h,$bytes)!==strlen($bytes))throw new RuntimeException('SERVICE_CONFIG_WRITE');fclose($h);chmod($path,0600);}
            $d['status']='PREPARED';$d['health']=$health;PrivateDeployment::write($this->descriptor(),$d);
            return ['status'=>'PREPARED','version'=>$m['version'],'health'=>$health,'web_started'=>false];
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    private function ownPid(string $kind): ?int
    {
        $file=$this->c['runtime_dir'].'/'.$kind.'.pid';if(!is_file($file))return null;
        if(is_link($file)||fileowner($file)!==0)throw new RuntimeException('SERVICE_PID_UNSAFE');$raw=trim((string)file_get_contents($file));
        if(preg_match('/\A[1-9][0-9]{0,8}\z/D',$raw)!==1)throw new RuntimeException('SERVICE_PID_INVALID');$pid=(int)$raw;
        if(!is_dir('/proc/'.$pid))return null;
        $args=(string)file_get_contents('/proc/'.$pid.'/cmdline');$config=$this->c['runtime_dir'].'/'.($kind==='fpm'?'php-fpm.conf':'nginx.conf');
        if(fileowner('/proc/'.$pid)!==0||strpos($args,$config)===false)throw new RuntimeException('SERVICE_PID_NOT_OWNED');return $pid;
    }
    public function start(): array
    {
        $lock=PrivateDeployment::lock($this->c['private_dir']);
        try{
            $d=$this->assertDescriptor();if(!in_array($d['status'],['PREPARED','STOPPED','RUNNING'],true))throw new RuntimeException('INSTANCE_NOT_PREPARED');
            $c=$this->c;$s=$c['runtime_dir'];$log=$c['private_dir'].'/service.log';
            if($this->ownPid('nginx')!==null||$this->ownPid('fpm')!==null)throw new RuntimeException('SERVICE_ALREADY_RUNNING');
            PrivateDeployment::run([$c['php_fpm'],'-t','-y',$s.'/php-fpm.conf'],$log);
            PrivateDeployment::run([$c['nginx'],'-t','-p',$s.'/','-c',$s.'/nginx.conf'],$log);
            PrivateDeployment::run([$c['php_fpm'],'-D','-y',$s.'/php-fpm.conf'],$log);
            try{PrivateDeployment::run([$c['nginx'],'-p',$s.'/','-c',$s.'/nginx.conf'],$log);}catch(Throwable $e){$pid=$this->ownPid('fpm');if($pid!==null)posix_kill($pid,SIGQUIT);throw $e;}
            $d['status']='RUNNING';PrivateDeployment::write($this->descriptor(),$d);return ['status'=>'RUNNING','public_listener'=>false];
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public function stop(): array
    {
        $lock=PrivateDeployment::lock($this->c['private_dir']);
        try{$d=$this->assertDescriptor();foreach(['nginx','fpm']as$kind){$pid=$this->ownPid($kind);if($pid===null)continue;posix_kill($pid,SIGQUIT);$until=microtime(true)+10;while(is_dir('/proc/'.$pid)&&microtime(true)<$until){clearstatcache();usleep(50000);}if(is_dir('/proc/'.$pid))throw new RuntimeException('SERVICE_STOP_TIMEOUT');}$d['status']='STOPPED';PrivateDeployment::write($this->descriptor(),$d);return ['status'=>'STOPPED','files_deleted'=>false];}
        finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public function health(): array
    {
        $this->assertDescriptor();$c=$this->c;$m=$this->manifest();$release=a513_validate_release($c['release_root'],$c['release_root'].'/RELEASE-MANIFEST.json');
        $dbName=a5_read_database_name(dirname(__DIR__,2),$c['database_name_file']);$health=a513_check_database($release,$c['mode'],$c['defaults_extra_file'],$dbName);
        $settings=Control_license_verifier::deployment_document($c['deployment_file'],$c['release_root']);$host=parse_url($settings['FINANCE_BASE_URL']??'',PHP_URL_HOST);
        if(!is_string($host)||preg_match('/\A[A-Za-z0-9.-]+\z/D',$host)!==1)throw new RuntimeException('HEALTH_HOST_INVALID');
        $h=curl_init('https://'.$host.':'.$c['port'].'/login');curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_RESOLVE=>[$host.':'.$c['port'].':127.0.0.1']]);
        if(isset($c['health_ca']))curl_setopt($h,CURLOPT_CAINFO,$c['health_ca']);$body=curl_exec($h);$code=(int)curl_getinfo($h,CURLINFO_RESPONSE_CODE);curl_close($h);
        if($code!==200||!is_string($body)||strpos($body,'name="identifier"')===false)throw new RuntimeException('WEB_HEALTH_FAILED');
        $r=['status'=>'PASS','web_verified'=>true,'health'=>$health,'version'=>$m['version'],'artifact_sha256'=>$m['sha256'],
            'from_schema'=>$c['from_schema']??$m['baseline_schema_version'],'to_schema'=>$m['schema_version'],
            'migration_versions'=>array_column(a5_plan($release['catalog'],$c['mode']),'id'),'backup_sha256'=>$c['backup_sha256']??null,'at'=>date(DATE_ATOM)];
        PrivateDeployment::write($c['private_dir'].'/result.json',$r);return $r;
    }
}
