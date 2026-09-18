<?php
declare(strict_types=1);
require_once __DIR__.'/PortableStore.php';

final class SetupUi
{
    public static function message(string $code): string
    {
        return [
            'SERVER_REQUIREMENTS_MISSING'=>'Kebutuhan server belum lengkap. Minta admin menjalankan pemeriksaan pemasang.',
            'SETUP_PERMISSION_EXPIRED'=>'Izin sementara sudah kedaluwarsa, bukan hak pembelian Anda. Minta izin pengganti dari Control; jangan mengulang database.',
            'SETUP_REQUEST_UNAUTHORIZED'=>'Kode pemasangan tidak cocok. Gunakan kode dari tautan pengiriman Control, bukan password akun Anda.',
            'SETUP_ALREADY_COMPLETE'=>'Pemasangan selesai. Halaman setup sudah dikunci.',
            'DATABASE_CONNECTION_FAILED'=>'Database belum dapat dihubungi. Periksa host, port, nama database, username, dan password. Admin dapat membuka kembali formulir jika SQL belum dimulai.',
            'DATABASE_NOT_EMPTY'=>'Database sudah berisi. Buat database kosong untuk instalasi baru. Jangan hapus data lama.',
            'DATABASE_PARTIAL_REVIEW_REQUIRED'=>'Proses SQL sebelumnya terputus. Bukti disimpan; admin harus memeriksa langkah terakhir sebelum melanjutkan. Tidak ada SQL yang diulang otomatis.',
            'INSTANCE_LIMIT_EXCEEDED'=>'Jumlah server aktif sudah mencapai kuota. Periksa slot instalasi di Control. Server lain tetap aktif.',
            'ACTIVATION_CREDENTIAL_REJECTED'=>'Kode aktivasi kedaluwarsa atau tidak berlaku. Minta pengganti melalui Control; identitas instalasi tetap dipertahankan.',
            'WEB_HEALTH_FAILED'=>'Database selesai, tetapi halaman login HTTPS belum lolos pemeriksaan. Periksa URL, sertifikat HTTPS, dan root website public/.',
            'CONTROL_ACK_REQUIRED'=>'Laporan hasil belum diterima Control. Data pemasangan tetap tersimpan; proses pendamping akan mencoba pengiriman ulang.',
            'PACKAGE_CORE_MODIFIED'=>'Isi paket berbeda dari paket terverifikasi. Jangan lanjutkan sebelum administrator memeriksa berkas.',
            'WAITING_FOR_CUSTOMER'=>'Siap menerima pengaturan Anda.',
            'SETUP_REQUEST_EXISTS'=>'Pengaturan sudah dikirim. Tunggu proses pendamping; jangan kirim ulang.',
        ][$code]??'Langkah ini belum berhasil. Simpan kode pemeriksaan di bawah dan minta administrator memeriksanya. Jangan hapus folder atau database.';
    }
    public static function authorize(string $root,string $secret): array
    {
        if(strlen($secret)<32||strlen($secret)>256)throw new RuntimeException('SETUP_REQUEST_UNAUTHORIZED');
        $b=CustomerPlatform::document($root,$root.'/storage/setup/browser.json');
        if(!hash_equals($b['secret_sha256']??'',hash('sha256',$secret)))throw new RuntimeException('SETUP_REQUEST_UNAUTHORIZED');
        return $b;
    }
    public static function request(string $root,array $input): array
    {
        $b=self::authorize($root,(string)($input['secret']??''));
        if(is_file($root.'/storage/setup/closed.json'))throw new RuntimeException('SETUP_ALREADY_COMPLETE');
        if(($b['expires_at']??0)<=time())throw new RuntimeException('SETUP_PERMISSION_EXPIRED');
        if(!is_array($input['config']??null)||!is_array($input['owner']??null))throw new RuntimeException('SETUP_REQUEST_INVALID');
        $key=base64_decode($b['public_key']??'',true);if(!is_string($key)||strlen($key)!==32)throw new RuntimeException('SETUP_REQUEST_INVALID');
        $packet=['sealed'=>base64_encode(sodium_crypto_box_seal(json_encode(['permit_id'=>$b['permit_id'],'secret'=>$input['secret'],
            'config'=>$input['config'],'owner'=>$input['owner']],JSON_THROW_ON_ERROR),$key))];
        $path=$root.'/storage/inbox/request.json';
        // The only web-writable installer location contains sealed credentials, never executable code.
        if(is_link(dirname($path))||str_replace('\\','/',(string)realpath(dirname($path)))!==dirname($path))throw new RuntimeException('INBOX_UNSAFE');
        $mask=umask(0007);try{$h=@fopen($path,'xb');}finally{umask($mask);}
        if(!$h)throw new RuntimeException('SETUP_REQUEST_EXISTS');
        try{$bytes=json_encode($packet,JSON_THROW_ON_ERROR);if(fwrite($h,$bytes)!==strlen($bytes)||!fflush($h)||!fsync($h))throw new RuntimeException('SETUP_REQUEST_WRITE_FAILED');}
        finally{fclose($h);}
        return ['phase'=>'QUEUED','percent'=>15,'code'=>''];
    }
}
