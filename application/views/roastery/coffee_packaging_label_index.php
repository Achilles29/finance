<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$filters = $filters ?? ['q' => '', 'status' => 'ACTIVE'];
$labels = $labels ?? [];
$edit = $edit_row ?? null;
$tableReady = !empty($table_ready);
$formMode = !empty($form_mode);
$autoPrint = !empty($print_auto);
$isEditing = !empty($edit['id']);
$canSave = $isEditing ? !empty($can_edit) : !empty($can_create);
$imagePath = trim((string)($edit['image_path'] ?? ''));
$imageUrl = $imagePath !== '' ? base_url($imagePath) : '';
$logoPath = trim((string)($edit['logo_path'] ?? ''));
$defaultLogoUrl = base_url('assets/uploads/logo.png');
$logoUrl = $logoPath !== '' ? base_url($logoPath) : $defaultLogoUrl;
$canvasWidth = max(40, (int)($edit['canvas_width_mm'] ?? 90));
$canvasHeight = max(60, (int)($edit['canvas_height_mm'] ?? 140));
$designJson = (string)($edit['design_json'] ?? '{}');
$designData = json_decode($designJson, true);
$designMeta = is_array($designData['meta'] ?? null) ? $designData['meta'] : [];
$roastLevelOptions = ['Light', 'Light - Medium', 'Medium', 'Medium - Dark', 'Dark', 'Omni Roast', 'Espresso Roast', 'Filter Roast'];
$bodyLevelOptions = ['Light', 'Light - Medium', 'Medium', 'Medium - Full', 'Full'];
$selectedRoastLevel = (string)($edit['roast_level'] ?? 'Medium');
$selectedBodyLevel = (string)($edit['body_level'] ?? ($designMeta['body_level'] ?? 'Light - Medium'));
if ($selectedRoastLevel !== '' && !in_array($selectedRoastLevel, $roastLevelOptions, true)) {
    $roastLevelOptions[] = $selectedRoastLevel;
}
if ($selectedBodyLevel !== '' && !in_array($selectedBodyLevel, $bodyLevelOptions, true)) {
    $bodyLevelOptions[] = $selectedBodyLevel;
}
$selectedElevation = (string)($edit['elevation_text'] ?? ($designMeta['elevation_text'] ?? ($designMeta['elevation'] ?? '')));
$selectedBeanType = (string)($edit['bean_type'] ?? ($designMeta['bean_type'] ?? 'Whole Bean'));
$selectedFooterNote = (string)($edit['footer_note'] ?? ($designMeta['footer_note'] ?? ''));
$themePreset = (string)($edit['theme_preset'] ?? 'heritage-cream');
$designCanvas = is_array($designData['canvas'] ?? null) ? $designData['canvas'] : [];
$artworkMode = (string)($designCanvas['artworkMode'] ?? 'full');
if (!in_array($artworkMode, ['full', 'rounded', 'circle', 'arch'], true)) {
    $artworkMode = 'full';
}
$artworkGallery = is_array($artwork_gallery ?? null) ? $artwork_gallery : [];
$logoGallery = is_array($logo_gallery ?? null) ? $logo_gallery : [];
$currentStatus = strtoupper((string)($filters['status'] ?? 'ACTIVE'));
$statusTabs = [
    'ACTIVE' => 'Aktif',
    'INACTIVE' => 'Nonaktif',
    'ALL' => 'Semua',
];
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Cormorant+Garamond:wght@600;700&family=Fraunces:opsz,wght@9..144,600;9..144,800;9..144,900&family=Jost:wght@400;600;800&family=Libre+Baskerville:wght@400;700&family=Space+Grotesk:wght@500;700&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">

<style>
.coffee-label-page{--red:#a70f25;--gold:#d6a84d;--brown:#432115}
.coffee-hero{border:0;border-radius:28px;overflow:hidden;color:#fff;background:radial-gradient(circle at 12% 14%,rgba(255,255,255,.22),transparent 24%),radial-gradient(circle at 90% 20%,rgba(214,168,77,.38),transparent 28%),linear-gradient(135deg,#2b140c,#8e1827 58%,#b66e2f);box-shadow:0 22px 45px rgba(60,28,16,.2)}
.coffee-hero h3{font-family:'Playfair Display',serif;font-weight:900}.hero-kicker{letter-spacing:.23em;text-transform:uppercase;color:#ffe3a1;font-size:.72rem;font-weight:800}
.coffee-chip{border-radius:999px;padding:.45rem .75rem;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.25);font-size:.8rem;font-weight:700}
.label-workbench{display:grid;grid-template-columns:minmax(370px,1fr) minmax(420px,540px);gap:1.1rem;align-items:start}
.label-panel{border:1px solid rgba(167,15,37,.12);border-radius:22px;background:rgba(255,255,255,.94);box-shadow:0 16px 40px rgba(70,40,25,.1);overflow:hidden}
.label-panel-head{padding:1rem 1.15rem;border-bottom:1px solid rgba(167,15,37,.12);background:linear-gradient(135deg,#fff,#fff7eb);display:flex;justify-content:space-between;align-items:center;gap:1rem}.label-panel-head h5{margin:0;color:#3b1c14;font-weight:900}
.label-panel-body{padding:1.1rem}.label-form-grid,.label-tools{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.85rem}.full{grid-column:1/-1}
.label-form-grid label,.label-tools label{font-size:.72rem;font-weight:900;color:#6b3a2a;letter-spacing:.04em;text-transform:uppercase}
.form-control,.form-select{border-radius:13px}.range-line{display:grid;grid-template-columns:86px 1fr 48px;gap:.55rem;align-items:center}.range-line small{font-weight:800;color:#795548}.range-line output{font-size:.75rem;color:var(--red);font-weight:900;text-align:right}.range-line input{accent-color:var(--red)}
.toggle-row{display:flex;flex-wrap:wrap;gap:.45rem}.toggle-row .btn{border-radius:999px;font-weight:800}
.label-preview-card{position:sticky;top:82px}.preview-shell{min-height:640px;display:grid;place-items:center;padding:1.15rem;background:radial-gradient(circle at center,#fff6e6,#efe2d3);border-radius:20px;overflow:auto}
.label-canvas{width:var(--label-preview-w,360px);height:var(--label-preview-h,560px);max-width:100%;position:relative;overflow:hidden;border-radius:18px;background:#f7ecd9;color:#2c1711;box-shadow:0 24px 50px rgba(44,23,17,.28),inset 0 0 0 1px rgba(255,255,255,.45);isolation:isolate}
.label-canvas:before{content:"";position:absolute;inset:14px;border:1px solid rgba(214,168,77,.58);border-radius:13px;z-index:3;pointer-events:none}.label-canvas:after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(255,255,255,.12),transparent 32%,rgba(67,33,18,.2));z-index:2;pointer-events:none}
.label-bg{position:absolute;inset:0;z-index:1;background:radial-gradient(circle at 30% 20%,#fff8ea,#dfc195)}.label-bg img{width:100%;height:100%;object-fit:cover;object-position:center;filter:saturate(1.05) contrast(1.03)}.label-bg.no-image:before{content:"PNG LABEL ARTWORK";position:absolute;inset:18px;border:1px dashed rgba(95,48,25,.28);border-radius:14px;display:grid;place-items:center;color:rgba(95,48,25,.5);font-weight:900;letter-spacing:.14em}
.label-overlay{position:absolute;inset:0;z-index:4;background:radial-gradient(circle at 50% 34%,rgba(255,246,219,.78),rgba(255,246,219,.18) 23%,transparent 42%),linear-gradient(180deg,rgba(26,14,10,.12),rgba(44,20,14,.05) 30%,rgba(20,11,10,.84));pointer-events:none}.theme-midnight-roast .label-overlay{background:radial-gradient(circle at 50% 34%,rgba(255,232,183,.34),rgba(255,232,183,.08) 23%,transparent 42%),linear-gradient(180deg,rgba(13,9,10,.2),rgba(13,9,10,.22) 38%,rgba(13,9,10,.94))}.theme-clean-white .label-overlay{background:radial-gradient(circle at 50% 35%,rgba(255,255,255,.95),rgba(255,255,255,.42) 28%,transparent 48%),linear-gradient(180deg,rgba(255,255,255,.7),rgba(255,255,255,.14) 46%,rgba(255,255,255,.82))}
.label-watermark{position:absolute;z-index:5;left:50%;top:50%;transform:translate(-50%,-50%) rotate(-12deg);font-family:'Bebas Neue',sans-serif;font-size:84px;color:rgba(255,255,255,.10);letter-spacing:.08em;white-space:nowrap}.label-mark{position:absolute;z-index:6;left:18px;top:18px;padding:6px 10px;border:1px solid rgba(255,226,168,.38);border-radius:999px;font-size:9px;letter-spacing:.18em;font-weight:900;color:#fff6d6;background:rgba(38,22,18,.34);backdrop-filter:blur(5px)}
.label-brand-panel{position:absolute;z-index:5;left:24px;right:24px;top:24px;height:44%;border-radius:22px;background:radial-gradient(circle at 50% 55%,rgba(255,244,219,.9),rgba(255,244,219,.38) 40%,rgba(255,255,255,.08) 72%);border:1px solid rgba(255,229,174,.22);box-shadow:0 20px 44px rgba(25,12,9,.2),inset 0 0 0 1px rgba(255,255,255,.18);pointer-events:none}.label-brand-panel:after{content:"";position:absolute;left:19px;right:19px;bottom:13px;height:1px;background:linear-gradient(90deg,transparent,rgba(255,223,157,.45),transparent)}
.label-sensory-panel{position:absolute;z-index:5;left:24px;right:24px;bottom:24px;min-height:30%;border-radius:22px;background:linear-gradient(145deg,rgba(23,16,20,.90),rgba(60,23,43,.82) 48%,rgba(14,31,61,.86));border:1px solid rgba(255,225,169,.28);box-shadow:0 18px 34px rgba(17,8,9,.3);pointer-events:none}.theme-clean-white .label-sensory-panel{background:linear-gradient(145deg,rgba(255,255,255,.92),rgba(247,231,202,.88));border-color:rgba(128,53,32,.18)}
.label-orbit{position:absolute;z-index:6;border:1px solid rgba(255,220,145,.28);border-radius:50%;pointer-events:none}.label-orbit.o1{width:54%;aspect-ratio:1;left:23%;top:13%;box-shadow:0 0 0 26px rgba(255,220,145,.035)}.label-orbit.o2{width:22%;aspect-ratio:1;right:10%;bottom:13%}.label-orbit.o3{width:12%;aspect-ratio:1;left:9%;bottom:25%}.label-speckles{position:absolute;inset:0;z-index:6;pointer-events:none;background-image:radial-gradient(circle,rgba(255,228,166,.55) 0 1.2px,transparent 1.8px),radial-gradient(circle,rgba(255,255,255,.32) 0 .8px,transparent 1.4px);background-size:38px 48px,64px 72px;background-position:7px 11px,22px 29px;mix-blend-mode:screen;opacity:.7}
.label-meta-grid{position:absolute;z-index:6;left:34px;right:34px;bottom:34px;display:grid;grid-template-columns:repeat(4,1fr);gap:0;border-top:1px solid rgba(255,226,168,.22);border-bottom:1px solid rgba(255,226,168,.14);pointer-events:none}.label-meta-grid span{min-height:32px;border-right:1px solid rgba(255,226,168,.14)}.label-meta-grid span:last-child{border-right:0}
.label-logo{position:absolute;z-index:8;cursor:pointer;object-fit:contain;filter:drop-shadow(0 5px 10px rgba(49,21,13,.16))}.label-logo.active{outline:0;background:transparent}
.label-text{position:absolute;z-index:7;min-height:14px;cursor:pointer;padding:2px 5px;border-radius:8px;white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.08;text-shadow:0 1px 0 rgba(255,255,255,.16)}.label-text.active{outline:2px solid rgba(255,193,7,.9);background:rgba(255,193,7,.12)}
.gallery-strip{display:grid;grid-template-columns:repeat(auto-fill,minmax(82px,1fr));gap:.55rem;max-height:154px;overflow:auto;padding:.25rem}.gallery-tile{border:1px solid rgba(167,15,37,.15);border-radius:14px;padding:.25rem;background:#fff7ec;cursor:pointer;text-align:left}.gallery-tile img{width:100%;height:70px;object-fit:cover;border-radius:10px}.gallery-tile span{display:block;margin-top:.25rem;font-size:.65rem;color:#6b3a2a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.gallery-tile.active{border-color:#a70f25;box-shadow:0 0 0 2px rgba(167,15,37,.12)}
.saved-card{border:1px solid rgba(167,15,37,.1);border-radius:18px;padding:.85rem;background:#fff;box-shadow:0 8px 22px rgba(70,40,25,.06);display:flex;gap:.8rem}.saved-thumb{width:54px;height:74px;border-radius:12px;overflow:hidden;background:linear-gradient(135deg,#f7e5c6,#d9b66d);flex:none}.saved-thumb img{width:100%;height:100%;object-fit:cover}
.label-status-tabs{display:flex;flex-wrap:wrap;gap:.45rem;padding:.35rem;border:1px solid rgba(167,15,37,.1);border-radius:999px;background:#fff8ed;box-shadow:inset 0 0 0 1px rgba(255,255,255,.65)}
.label-status-tab{display:inline-flex;align-items:center;gap:.35rem;padding:.5rem .9rem;border-radius:999px;color:#6b3a2a;text-decoration:none;font-weight:900;font-size:.82rem;border:1px solid transparent;transition:.18s ease}
.label-status-tab:hover{color:#a70f25;background:#fff;border-color:rgba(167,15,37,.16)}
.label-status-tab.active{color:#fff;background:linear-gradient(135deg,#a70f25,#c74a33);box-shadow:0 8px 18px rgba(167,15,37,.18)}
.saved-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.45rem;margin-top:.9rem}
.saved-actions .btn{width:100%;display:inline-flex;align-items:center;justify-content:center;gap:.3rem;border-radius:12px;font-weight:900;white-space:nowrap}
.saved-actions .btn-wide{grid-column:1/-1}
.coffee-label-page .label-canvas{border-radius:28px;background:linear-gradient(135deg,#f8ead3,#ffd39b 36%,#f28f76 67%,#9fb191);box-shadow:0 30px 65px rgba(67,38,24,.26),inset 0 0 0 1px rgba(255,255,255,.62)}
.coffee-label-page .label-canvas:before{inset:22px;border:1px solid rgba(255,255,255,.72);border-radius:24px;box-shadow:inset 0 0 0 1px rgba(119,78,45,.08)}
.coffee-label-page .label-canvas:after{background:radial-gradient(circle at 18% 18%,rgba(255,255,255,.52),transparent 24%),radial-gradient(circle at 84% 76%,rgba(167,15,37,.13),transparent 26%),linear-gradient(135deg,rgba(255,255,255,.35),transparent 38%,rgba(90,50,33,.08));z-index:2}
.coffee-label-page .label-bg{background:linear-gradient(135deg,#f8ead3,#ffd19b 38%,#f08a72 68%,#9fb191)}
.coffee-label-page .label-bg.no-image:before{content:"";inset:0;border:0;border-radius:0;background:radial-gradient(circle at 28% 24%,rgba(255,255,255,.45),transparent 22%),radial-gradient(circle at 76% 70%,rgba(117,72,121,.16),transparent 24%),linear-gradient(135deg,#f8ead3 0%,#ffd19b 37%,#f08a72 67%,#9fb191 100%)}
.coffee-label-page .label-bg.no-image:after{content:"";position:absolute;inset:-12%;background:repeating-radial-gradient(ellipse at 80% 18%,rgba(255,255,255,.18) 0 1px,transparent 1px 13px);opacity:.6;transform:rotate(-18deg)}
.coffee-label-page .label-bg img{filter:none}
.coffee-label-page .label-canvas.artwork-mode-rounded .label-bg{inset:30px 28px auto;height:45%;border-radius:28px;overflow:hidden;box-shadow:0 18px 42px rgba(57,31,22,.18),inset 0 0 0 1px rgba(255,255,255,.54)}
.coffee-label-page .label-canvas.artwork-mode-circle .label-bg{width:68%;height:auto;aspect-ratio:1;left:16%;right:auto;top:10%;bottom:auto;border-radius:50%;overflow:hidden;box-shadow:0 18px 42px rgba(57,31,22,.18),0 0 0 1px rgba(255,255,255,.65)}
.coffee-label-page .label-canvas.artwork-mode-arch .label-bg{inset:28px 30px auto;height:52%;border-radius:48% 48% 7% 7% / 38% 38% 7% 7%;overflow:hidden;box-shadow:0 20px 44px rgba(57,31,22,.18),inset 0 0 0 1px rgba(255,255,255,.58)}
.coffee-label-page .label-canvas.artwork-mode-rounded .label-bg.no-image:before,.coffee-label-page .label-canvas.artwork-mode-circle .label-bg.no-image:before,.coffee-label-page .label-canvas.artwork-mode-arch .label-bg.no-image:before{border-radius:inherit}
.coffee-label-page .label-overlay{background:linear-gradient(180deg,rgba(255,255,255,.06),transparent 38%,rgba(44,24,15,.05));}
.coffee-label-page .label-brand-panel{left:36px;right:36px;top:36px;height:53%;border-radius:24px;background:transparent;border:0;box-shadow:none}
.coffee-label-page .label-brand-panel:after{left:21%;right:21%;bottom:7%;height:1px;background:linear-gradient(90deg,transparent,rgba(57,32,20,.26),transparent)}
.coffee-label-page .label-sensory-panel{left:54px;right:54px;bottom:58px;min-height:17%;border-radius:14px;background:rgba(255,249,235,.08);border:1px solid rgba(57,32,20,.34);box-shadow:none;backdrop-filter:none}
.coffee-label-page .label-watermark{display:none}
.coffee-label-page .label-mark{display:none}
.coffee-label-page .label-orbit{border-color:rgba(255,255,255,.28)}
.coffee-label-page .label-orbit.o1{width:58%;left:-19%;top:-4%;box-shadow:none;background:rgba(255,255,255,.20)}
.coffee-label-page .label-orbit.o2{width:42%;right:-18%;bottom:-10%;background:rgba(167,15,37,.08)}
.coffee-label-page .label-orbit.o3{width:28%;left:8%;bottom:8%;background:rgba(118,135,96,.08)}
.coffee-label-page .label-orbit{display:none}
.coffee-label-page .label-speckles{display:none}
.coffee-label-page .label-meta-grid{left:52px;right:52px;bottom:56px;border:1px solid rgba(68,38,24,.30);border-radius:15px;overflow:hidden;background:rgba(255,249,235,.13)}
.coffee-label-page .label-meta-grid span{min-height:44px;border-right:1px solid rgba(68,38,24,.20)}
.coffee-label-page .label-logo{filter:drop-shadow(0 2px 5px rgba(255,255,255,.42));background:transparent!important;mix-blend-mode:multiply}
.coffee-label-page .label-text{text-shadow:none;line-height:1.05}
.coffee-label-page .label-text[data-block="origin"],.coffee-label-page .label-text[data-block="process_method"],.coffee-label-page .label-text[data-block="roast_level"],.coffee-label-page .label-text[data-block="weight_text"],.coffee-label-page .label-text[data-block="batch_no"],.coffee-label-page .label-text[data-block="roast_date"],.coffee-label-page .label-text[data-block="expiry_date"],.coffee-label-page .label-text[data-block="description"]{display:none}
.label-roastery-kicker{position:absolute;z-index:7;left:14%;right:14%;top:20%;text-align:center;font-family:'Space Grotesk',sans-serif;font-size:6.6px;letter-spacing:.26em;text-transform:uppercase;color:rgba(64,36,24,.62);font-weight:900;white-space:normal;line-height:1.35}
.label-info-panel{position:absolute;z-index:7;left:13%;right:13%;bottom:7%;border:1px solid rgba(67,37,24,.45);border-radius:15px;overflow:hidden;background:rgba(255,244,222,.14);color:#3e261b;font-family:'Space Grotesk',sans-serif;font-size:7px;letter-spacing:.03em;backdrop-filter:blur(.25px)}
.label-info-panel.active{outline:2px dashed rgba(167,15,37,.72);outline-offset:4px}
.label-info-panel .info-top{display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid rgba(67,37,24,.28)}
.label-info-panel .info-cell{padding:5px 8px 6px;min-height:38px}.label-info-panel .info-cell:first-child{border-right:1px solid rgba(67,37,24,.28)}
.label-info-panel .info-title{display:block;font-weight:900;text-transform:uppercase;letter-spacing:.11em;margin-bottom:3px;line-height:1.15}
.label-info-panel .info-value{display:block;font-size:8.4px;letter-spacing:.03em;margin-bottom:3px;line-height:1.1}
.label-info-panel .info-dots{display:flex;gap:4px}.label-info-panel .info-dot{width:6px;height:6px;border-radius:50%;border:1px solid rgba(67,37,24,.68)}.label-info-panel .info-dot.filled{background:#54301f}
.label-info-panel .info-bottom{display:grid;gap:3px;padding:6px 8px;border-bottom:1px solid rgba(67,37,24,.22)}.label-info-panel .info-line{display:grid;grid-template-columns:auto 1fr auto 1fr;gap:4px 7px;align-items:baseline}.label-info-panel .info-line b{font-weight:900;text-transform:uppercase;letter-spacing:.10em;min-width:0;line-height:1.15}.label-info-panel .info-line span{font-family:'Jost',sans-serif;font-size:7.5px;letter-spacing:.03em;line-height:1.2}
.label-info-panel .info-date-row{display:grid;grid-template-columns:1fr 1fr 1fr;border-bottom:1px solid rgba(67,37,24,.22)}.label-info-panel .info-date-cell{min-height:30px;padding:5px 6px 6px;border-right:1px solid rgba(67,37,24,.18)}.label-info-panel .info-date-cell:last-child{border-right:0}.label-info-panel .info-date-cell b{display:block;font-weight:900;text-transform:uppercase;letter-spacing:.10em;font-size:5.8px;margin-bottom:3px;line-height:1.15}.label-info-panel .info-date-cell span{display:block;min-height:10px;font-family:'Space Grotesk',sans-serif;font-size:6.8px;letter-spacing:.06em;line-height:1.2;word-break:break-word}
.label-pack-footer{position:absolute;z-index:7;left:17%;right:17%;bottom:6%;display:flex;justify-content:space-between;align-items:center;color:#4a2c1d;font-family:'Space Grotesk',sans-serif;font-size:8px;font-weight:700;letter-spacing:.14em;text-transform:uppercase}
.label-info-panel .label-pack-footer{position:static;left:auto;right:auto;bottom:auto;min-height:24px;padding:7px 9px 8px;font-size:7.2px;line-height:1.2}
.taste-icon-row{position:absolute;z-index:7;left:18%;right:18%;top:47%;display:flex;align-items:center;justify-content:center;gap:10px;color:#5d3827;font-size:13px}
.taste-icon-row span{width:22px;height:22px;border-radius:50%;display:grid;place-items:center;background:rgba(255,248,232,.22);border:1px solid rgba(70,38,22,.26);backdrop-filter:blur(.4px)}
.label-watermark[data-block],.label-mark[data-block],.label-roastery-kicker[data-block],.taste-icon-row[data-block]{cursor:pointer;pointer-events:auto}
.label-watermark.active,.label-mark.active,.label-roastery-kicker.active,.taste-icon-row.active{outline:2px dashed rgba(167,15,37,.72);outline-offset:4px;border-radius:12px}
.taste-builder{border:1px solid rgba(167,15,37,.12);border-radius:16px;background:linear-gradient(135deg,#fffaf3,#fff3e5);padding:.75rem;display:grid;gap:.55rem}
.taste-row{display:grid;grid-template-columns:1fr 150px 38px 38px;gap:.5rem;align-items:center}
.taste-row .form-control,.taste-row .form-select{border-radius:11px}
.taste-icon-preview{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#fff;border:1px solid rgba(167,15,37,.16);color:#6b3a2a}
.description-card{border:1px solid rgba(167,15,37,.12);border-radius:16px;background:#fffaf3;padding:.75rem}
.description-card textarea{min-height:86px}
.description-hint{font-size:.72rem;color:#8a6a55;margin-top:.35rem}
.label-meta-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem;margin-bottom:.75rem}.label-meta-form .full{grid-column:1/-1}
@media(max-width:576px){.label-meta-form{grid-template-columns:1fr}}
.coffee-label-page .theme-midnight-roast{background:linear-gradient(135deg,#120d0c,#3a1e26 42%,#d36b47 72%,#182946)}
.coffee-label-page .theme-midnight-roast .label-overlay{background:linear-gradient(180deg,rgba(255,255,255,.04),transparent 38%,rgba(44,24,15,.05))}
.coffee-label-page .theme-clean-white{background:linear-gradient(135deg,#fffaf0,#f7e5c2 48%,#d8c7a6)}
.coffee-label-page .theme-clean-white .label-bg.no-image:before{background:linear-gradient(135deg,#fffaf0,#f7e5c2 48%,#d8c7a6)}
.coffee-label-page .theme-clean-white .label-overlay{background:linear-gradient(180deg,rgba(255,255,255,.04),transparent 38%,rgba(44,24,15,.05))}
.coffee-label-page .theme-porcelain-mist{background:linear-gradient(135deg,#fffdf7,#f3efe3 42%,#dfe8d8 74%,#c9dacb)}
.coffee-label-page .theme-porcelain-mist .label-bg.no-image:before{background:radial-gradient(circle at 25% 18%,rgba(255,255,255,.78),transparent 26%),linear-gradient(135deg,#fffdf7,#f3efe3 42%,#dfe8d8 74%,#c9dacb)}
.coffee-label-page .theme-porcelain-mist .label-overlay{background:linear-gradient(180deg,rgba(255,255,255,.08),transparent 42%,rgba(255,255,255,.16))}
.coffee-label-page .theme-sakura-cream{background:linear-gradient(135deg,#fff8ed,#ffe4d3 45%,#f7b9a1 72%,#ecd1bd)}
.coffee-label-page .theme-sakura-cream .label-bg.no-image:before{background:radial-gradient(circle at 25% 22%,rgba(255,255,255,.62),transparent 27%),linear-gradient(135deg,#fff8ed,#ffe4d3 45%,#f7b9a1 72%,#ecd1bd)}
.coffee-label-page .theme-sakura-cream .label-overlay{background:linear-gradient(180deg,rgba(255,255,255,.06),transparent 42%,rgba(70,36,24,.04))}
.coffee-label-page .theme-oat-paper{background:linear-gradient(135deg,#fbf3df,#efe0bf 48%,#d8c19a)}
.coffee-label-page .theme-oat-paper .label-bg.no-image:before{background:linear-gradient(135deg,#fbf3df,#efe0bf 48%,#d8c19a)}
.coffee-label-page .theme-oat-paper .label-overlay{background:linear-gradient(180deg,rgba(255,255,255,.08),transparent 42%,rgba(90,58,31,.06))}
.coffee-label-page .theme-matcha-sunrise{background:linear-gradient(135deg,#fff4d9,#f7cf9c 42%,#bdc69b 73%,#7f9678)}
.coffee-label-page .theme-matcha-sunrise .label-bg.no-image:before{background:linear-gradient(135deg,#fff4d9,#f7cf9c 42%,#bdc69b 73%,#7f9678)}
.coffee-label-page .theme-matcha-sunrise .label-overlay{background:linear-gradient(180deg,rgba(255,255,255,.05),transparent 40%,rgba(47,70,46,.06))}
@media(max-width:576px){.saved-actions{grid-template-columns:1fr}.label-status-tabs{border-radius:18px}.label-status-tab{flex:1;justify-content:center}}.saved-title{font-weight:900;color:#511c18}
@media(max-width:1180px){.label-workbench{grid-template-columns:1fr}.label-preview-card{position:static}}@media(max-width:768px){.label-form-grid,.label-tools{grid-template-columns:1fr}}
@page{margin:0}
@media print{body.coffee-label-printing .layout-menu,body.coffee-label-printing .layout-navbar,body.coffee-label-printing .content-footer,body.coffee-label-printing .coffee-hero,body.coffee-label-printing .alert,body.coffee-label-printing .label-workbench>:not(.print-target),body.coffee-label-printing .print-target>.label-panel-head,body.coffee-label-printing .print-target small{display:none!important}body.coffee-label-printing .container-xxl,body.coffee-label-printing .content-wrapper,body.coffee-label-printing .coffee-label-page,body.coffee-label-printing .label-panel-body{padding:0!important;margin:0!important;background:#fff!important}body.coffee-label-printing .label-workbench{display:grid!important;grid-template-columns:1fr!important;place-items:center!important;min-height:100vh!important;margin:0!important}body.coffee-label-printing .print-target{display:grid!important;place-items:center!important;border:0!important;box-shadow:none!important;background:#fff!important;overflow:visible!important}body.coffee-label-printing .preview-shell{min-height:100vh!important;padding:0!important;background:#fff!important;overflow:visible!important}body.coffee-label-printing .label-canvas{width:var(--label-print-w,90mm)!important;height:var(--label-print-h,140mm)!important;box-shadow:none!important;border-radius:28px!important}}
</style>

<div class="coffee-label-page">
  <div class="card coffee-hero mb-4"><div class="card-body p-4 p-lg-5 d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div><div class="hero-kicker mb-2">Roastery Label Studio</div><h3 class="mb-2">Label Packaging Kopi</h3><div class="text-white-50"><?php echo $formMode ? 'Atur detail label, preview, lalu simpan untuk kembali ke daftar.' : 'Kelola label packaging kopi yang sudah dibuat. Duplikat template lama bila ingin produksi batch cepat.'; ?></div></div>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <span class="coffee-chip"><i class="ri ri-image-add-line"></i> PNG artwork</span><span class="coffee-chip"><i class="ri ri-font-size-2"></i> Editable text</span><span class="coffee-chip"><i class="ri ri-printer-line"></i> Print preview</span>
      <?php if ($formMode): ?>
        <a class="btn btn-light fw-bold" href="<?php echo site_url('roastery/packaging-labels'); ?>"><i class="ri ri-arrow-left-line me-1"></i>Kembali ke Daftar</a>
      <?php elseif (!empty($can_create)): ?>
        <a class="btn btn-light fw-bold" href="<?php echo site_url('roastery/packaging-labels?new=1'); ?>"><i class="ri ri-add-line me-1"></i>Tambah Label</a>
      <?php endif; ?>
    </div>
  </div></div>

  <?php if (!$tableReady): ?><div class="alert alert-warning">Jalankan SQL <code>sql/2026-07-26a_create_coffee_packaging_labels.sql</code> agar data bisa disimpan.</div><?php endif; ?>

  <?php if ($formMode): ?>
  <div class="label-workbench mb-4">
    <div class="label-panel"><div class="label-panel-head"><div><h5><?php echo $isEditing ? 'Edit Label' : 'Buat Label Baru'; ?></h5><small class="text-muted">Data kopi + setting desain.</small></div><a class="btn btn-sm btn-outline-secondary" href="<?php echo site_url('roastery/packaging-labels'); ?>">Daftar Label</a></div>
      <div class="label-panel-body">
        <form method="post" action="<?php echo site_url('roastery/packaging-labels/save'); ?>" enctype="multipart/form-data" id="coffeeLabelForm">
          <?php if ($this->config->item('csrf_protection')): ?><input type="hidden" name="<?php echo html_escape($this->security->get_csrf_token_name()); ?>" value="<?php echo html_escape($this->security->get_csrf_hash()); ?>"><?php endif; ?>
          <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
          <input type="hidden" name="design_json" id="designJsonInput" value="<?php echo html_escape($designJson); ?>">
          <div class="label-form-grid">
            <div class="full"><label class="form-label">Nama Kopi</label><input class="form-control" name="coffee_name" data-label-field="coffee_name" value="<?php echo html_escape((string)($edit['coffee_name'] ?? '')); ?>" placeholder="NAMUA HOUSE BLEND" required></div>
            <div><label class="form-label">Origin</label><input class="form-control" name="origin" data-label-field="origin" value="<?php echo html_escape((string)($edit['origin'] ?? '')); ?>" placeholder="Kintamani / Gayo"></div>
            <div><label class="form-label">Berat</label><input class="form-control" name="weight_text" data-label-field="weight_text" value="<?php echo html_escape((string)($edit['weight_text'] ?? '200 g')); ?>"></div>
            <div><label class="form-label">Process</label><input class="form-control" name="process_method" data-label-field="process_method" value="<?php echo html_escape((string)($edit['process_method'] ?? '')); ?>" placeholder="Natural / Washed"></div>
            <div>
              <label class="form-label">Roast Level</label>
              <select class="form-select" name="roast_level" data-label-field="roast_level">
                <?php foreach ($roastLevelOptions as $option): ?>
                  <option value="<?php echo html_escape($option); ?>" <?php echo $selectedRoastLevel === $option ? 'selected' : ''; ?>><?php echo html_escape($option); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div><label class="form-label">Batch No</label><input class="form-control" name="batch_no" data-label-field="batch_no" value="<?php echo html_escape((string)($edit['batch_no'] ?? '')); ?>"></div>
            <div><label class="form-label">Tanggal Roast</label><input class="form-control" type="date" name="roast_date" data-label-field="roast_date" value="<?php echo html_escape((string)($edit['roast_date'] ?? '')); ?>"></div>
            <div><label class="form-label">Best Before</label><input class="form-control" type="date" name="expiry_date" data-label-field="expiry_date" value="<?php echo html_escape((string)($edit['expiry_date'] ?? '')); ?>"></div>
            <div class="full">
              <label class="form-label">Tasting Notes + Icon</label>
              <textarea class="d-none" name="tasting_notes" data-label-field="tasting_notes" id="tastingNotesValue"><?php echo html_escape((string)($edit['tasting_notes'] ?? '')); ?></textarea>
              <div class="taste-builder">
                <div id="tasteRows"></div>
                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                  <button class="btn btn-sm btn-outline-danger" type="button" id="addTasteNote"><i class="ri ri-add-line me-1"></i>Tambah Note</button>
                  <small class="text-muted">Ikon opsional. Sistem akan menyarankan ikon dari kata seperti citrus, floral, tea, chocolate, nutty.</small>
                </div>
              </div>
            </div>
            <div class="full"><label class="form-label">Brew Suggestion</label><input class="form-control" name="brew_suggestion" data-label-field="brew_suggestion" value="<?php echo html_escape((string)($edit['brew_suggestion'] ?? 'Filter / Espresso / Milk Based')); ?>"></div>
            <div class="full">
              <label class="form-label">Keterangan Label</label>
              <div class="description-card">
                <div class="label-meta-form">
                  <div>
                    <label class="form-label mb-1">Body</label>
                    <select class="form-select" name="body_level" data-meta-field="body_level">
                      <?php foreach ($bodyLevelOptions as $option): ?>
                        <option value="<?php echo html_escape($option); ?>" <?php echo $selectedBodyLevel === $option ? 'selected' : ''; ?>><?php echo html_escape($option); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div><label class="form-label mb-1">Elevation</label><input class="form-control" name="elevation_text" data-meta-field="elevation_text" value="<?php echo html_escape($selectedElevation); ?>" placeholder=">1200 mdpl"></div>
                  <div><label class="form-label mb-1">Bean / Grind</label><input class="form-control" name="bean_type" data-meta-field="bean_type" value="<?php echo html_escape($selectedBeanType); ?>" placeholder="Whole Bean"></div>
                  <div><label class="form-label mb-1">Footer Mini</label><input class="form-control" name="footer_note" data-meta-field="footer_note" value="<?php echo html_escape($selectedFooterNote); ?>" placeholder="Roasted in small batch"></div>
                </div>
                <textarea class="form-control" name="description" data-label-field="description" placeholder="Catatan internal, misal batch khusus / instruksi cetak. Tidak tampil di label."><?php echo html_escape((string)($edit['description'] ?? '')); ?></textarea>
                <div class="description-hint">Yang tampil di label adalah Footer Mini, Bean/Grind, berat, dan panel data. Catatan internal hanya tersimpan untuk administrasi.</div>
              </div>
            </div>
            <div class="full">
              <label class="form-label">Ukuran Label</label>
              <div class="input-group mb-2"><input class="form-control" type="number" min="40" max="160" name="canvas_width_mm" id="canvasWidth" value="<?php echo $canvasWidth; ?>"><span class="input-group-text">x</span><input class="form-control" type="number" min="60" max="240" name="canvas_height_mm" id="canvasHeight" value="<?php echo $canvasHeight; ?>"><span class="input-group-text">mm</span></div>
              <div class="range-line"><small>Lebar Label</small><input type="range" id="labelWidthRange" min="40" max="160" value="<?php echo $canvasWidth; ?>"><output id="labelWidthOut"><?php echo $canvasWidth; ?>mm</output></div>
              <div class="range-line mt-2"><small>Tinggi Label</small><input type="range" id="labelHeightRange" min="60" max="240" value="<?php echo $canvasHeight; ?>"><output id="labelHeightOut"><?php echo $canvasHeight; ?>mm</output></div>
            </div>
            <div><label class="form-label">Tema</label><select class="form-select" name="theme_preset" id="themePreset"><option value="heritage-cream" <?php echo $themePreset==='heritage-cream'?'selected':''; ?>>Heritage Cream</option><option value="porcelain-mist" <?php echo $themePreset==='porcelain-mist'?'selected':''; ?>>Porcelain Mist</option><option value="sakura-cream" <?php echo $themePreset==='sakura-cream'?'selected':''; ?>>Sakura Cream</option><option value="oat-paper" <?php echo $themePreset==='oat-paper'?'selected':''; ?>>Oat Paper</option><option value="matcha-sunrise" <?php echo $themePreset==='matcha-sunrise'?'selected':''; ?>>Matcha Sunrise</option><option value="clean-white" <?php echo $themePreset==='clean-white'?'selected':''; ?>>Clean White</option><option value="midnight-roast" <?php echo $themePreset==='midnight-roast'?'selected':''; ?>>Midnight Roast</option></select></div>
            <div><label class="form-label">Model Artwork</label><select class="form-select" id="artworkMode"><option value="full" <?php echo $artworkMode==='full'?'selected':''; ?>>Full Background</option><option value="rounded" <?php echo $artworkMode==='rounded'?'selected':''; ?>>Rounded Card</option><option value="circle" <?php echo $artworkMode==='circle'?'selected':''; ?>>Circle Medallion</option><option value="arch" <?php echo $artworkMode==='arch'?'selected':''; ?>>Arch Window</option></select></div>
            <div><label class="form-label">Artwork PNG</label><input class="form-control" type="file" name="label_image" id="labelImageInput" accept="image/png"><small class="text-muted">Upload PNG baru akan mengganti artwork lama.</small></div>
            <div class="full">
              <label class="form-label">Galeri Artwork Tersimpan</label>
              <input type="hidden" name="gallery_image_path" id="galleryImagePath" value="">
              <?php if (empty($artworkGallery)): ?>
                <div class="alert alert-light border mb-0">Belum ada PNG di galeri. Upload artwork pertama dulu, nanti otomatis muncul di sini.</div>
              <?php else: ?>
                <div class="gallery-strip" id="artworkGallery">
                  <?php foreach ($artworkGallery as $art): ?>
                    <?php $isCurrentArt = !empty($art['path']) && $art['path'] === $imagePath; ?>
                    <button class="gallery-tile <?php echo $isCurrentArt ? 'active' : ''; ?>" type="button" data-path="<?php echo html_escape((string)$art['path']); ?>" data-url="<?php echo html_escape((string)$art['url']); ?>">
                      <img src="<?php echo html_escape((string)$art['url']); ?>" alt="">
                      <span><?php echo html_escape((string)$art['name']); ?></span>
                    </button>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
            <div><label class="form-label">Logo</label><input class="form-control" type="file" name="logo_image" id="logoImageInput" accept="image/png"><small class="text-muted">Upload PNG opsional. Jika kosong memakai wordmark roastery tanpa border.</small></div>
            <div>
              <label class="form-label">Galeri Logo</label>
              <input type="hidden" name="gallery_logo_path" id="galleryLogoPath" value="">
              <?php if (empty($logoGallery)): ?>
                <div class="alert alert-light border mb-0 py-2">Belum ada logo tersimpan.</div>
              <?php else: ?>
                <div class="gallery-strip" id="logoGallery">
                  <?php foreach ($logoGallery as $logo): ?>
                    <?php $isCurrentLogo = !empty($logo['path']) && $logo['path'] === $logoPath; ?>
                    <button class="gallery-tile <?php echo $isCurrentLogo ? 'active' : ''; ?>" type="button" data-path="<?php echo html_escape((string)$logo['path']); ?>" data-url="<?php echo html_escape((string)$logo['url']); ?>">
                      <img src="<?php echo html_escape((string)$logo['url']); ?>" alt="">
                      <span><?php echo html_escape((string)$logo['name']); ?></span>
                    </button>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
            <div><label class="form-label">Status</label><select class="form-select" name="is_active"><option value="1" <?php echo (int)($edit['is_active'] ?? 1)===1?'selected':''; ?>>Aktif</option><option value="0" <?php echo (int)($edit['is_active'] ?? 1)===0?'selected':''; ?>>Nonaktif</option></select></div>
          </div>
          <hr class="my-4">
          <div class="label-tools">
            <div class="full">
              <label class="form-label">Elemen yang diatur</label>
              <select class="form-select" id="blockSelect">
                <option value="logo">Logo</option>
                <option value="coffee_name">Nama Kopi</option>
                <option value="roastery_kicker">Footer Mini Atas</option>
                <option value="tasting_notes">Tasting Notes</option>
                <option value="taste_icons">Ikon Tasting</option>
                <option value="brew_suggestion">Brew Suggestion</option>
                <option value="info_panel">Panel Info Bawah</option>
              </select>
              <small class="text-muted d-block mt-1" id="blockSourceHint">Sumber: upload logo / galeri logo.</small>
            </div>
            <div><label class="form-label">Font</label><select class="form-select" id="fontFamily"><option>Fraunces</option><option>Playfair Display</option><option>Cormorant Garamond</option><option>Libre Baskerville</option><option>Bebas Neue</option><option>Space Grotesk</option><option>Jost</option></select></div>
            <div><label class="form-label">Warna</label><input class="form-control form-control-color w-100" type="color" id="fontColor" value="#7d1720"></div>
            <div class="full range-line"><small>Ukuran</small><input type="range" id="fontSize" min="7" max="52"><output id="fontSizeOut"></output></div>
            <div class="full range-line"><small>Posisi X</small><input type="range" id="posX" min="0" max="100"><output id="posXOut"></output></div>
            <div class="full range-line"><small>Posisi Y</small><input type="range" id="posY" min="0" max="100"><output id="posYOut"></output></div>
            <div class="full range-line"><small>Lebar</small><input type="range" id="blockWidth" min="10" max="100"><output id="blockWidthOut"></output></div>
            <div class="full range-line"><small>Tinggi Panel</small><input type="range" id="panelHeight" min="18" max="48"><output id="panelHeightOut"></output></div>
            <div class="full range-line"><small>Spasi</small><input type="range" id="letterSpacing" min="0" max="8" step=".1"><output id="letterSpacingOut"></output></div>
            <div class="full toggle-row"><button class="btn btn-sm btn-danger" type="button" id="resetPremiumLayout"><i class="ri ri-sparkling-line me-1"></i>Reset Layout Premium</button><button class="btn btn-sm btn-outline-dark" type="button" data-toggle-style="bold"><i class="ri ri-bold"></i> Bold</button><button class="btn btn-sm btn-outline-dark" type="button" data-toggle-style="italic"><i class="ri ri-italic"></i> Italic</button><button class="btn btn-sm btn-outline-dark" type="button" data-toggle-style="uppercase">Uppercase</button><button class="btn btn-sm btn-outline-dark" type="button" data-align="left">Left</button><button class="btn btn-sm btn-outline-dark" type="button" data-align="center">Center</button><button class="btn btn-sm btn-outline-dark" type="button" data-align="right">Right</button></div>
          </div>
          <div class="d-flex flex-wrap gap-2 mt-4"><button class="btn btn-danger" type="submit" <?php echo (!$tableReady || !$canSave)?'disabled':''; ?>><i class="ri ri-save-3-line me-1"></i>Simpan Label</button><button class="btn btn-outline-dark" type="button" id="printLabelBtn"><i class="ri ri-printer-line me-1"></i>Print Preview</button></div>
        </form>
      </div>
    </div>

    <div class="label-panel label-preview-card print-target"><div class="label-panel-head"><div><h5>Live Preview</h5><small class="text-muted">Klik teks pada label untuk mengatur bloknya.</small></div><span class="badge bg-label-danger">Roastery</span></div>
      <div class="label-panel-body"><div class="preview-shell"><div id="labelCanvas" class="label-canvas theme-<?php echo html_escape($themePreset); ?> artwork-mode-<?php echo html_escape($artworkMode); ?>" style="--label-preview-w:<?php echo $canvasWidth * 4; ?>px;--label-preview-h:<?php echo $canvasHeight * 4; ?>px;--label-print-w:<?php echo $canvasWidth; ?>mm;--label-print-h:<?php echo $canvasHeight; ?>mm;">
        <div class="label-bg <?php echo $imageUrl===''?'no-image':''; ?>" id="labelBg"><?php if ($imageUrl !== ''): ?><img id="labelImagePreview" src="<?php echo html_escape($imageUrl); ?>" alt="Label artwork"><?php else: ?><img id="labelImagePreview" src="" alt="" style="display:none"><?php endif; ?></div>
        <div class="label-overlay"></div><div class="label-brand-panel"></div><div class="label-sensory-panel"></div><div class="label-orbit o1"></div><div class="label-orbit o2"></div><div class="label-orbit o3"></div><div class="label-speckles"></div><div class="label-watermark" data-block="watermark">NAMUA</div><div class="label-mark" data-block="brand_mark">NAMUA ROASTERY</div><div class="taste-icon-row" data-block="taste_icons"><span><i class="ri ri-seedling-line"></i></span><span><i class="ri ri-flower-line"></i></span><span><i class="ri ri-cup-line"></i></span></div>
        <div class="label-roastery-kicker" data-block="roastery_kicker" data-info-value="footer_note"></div>
        <div class="label-info-panel" data-block="info_panel">
          <div class="info-top">
            <div class="info-cell"><span class="info-title">Roast Level</span><span class="info-value" data-info-value="roast_level">Medium</span><span class="info-dots" data-info-dots="roast_level"></span></div>
            <div class="info-cell"><span class="info-title">Body</span><span class="info-value" data-info-value="body_level">Light - Medium</span><span class="info-dots" data-info-dots="body_level"></span></div>
          </div>
          <div class="info-bottom">
            <div class="info-line"><b>Origin</b><span data-info-value="origin">Nusantara</span></div>
            <div class="info-line"><b>Elevation</b><span data-info-value="elevation">&gt;1200 mdpl</span><b>Process</b><span data-info-value="process_method">Natural</span></div>
          </div>
          <div class="info-date-row">
            <div class="info-date-cell"><b>Batch</b><span data-info-value="batch_no">&nbsp;</span></div>
            <div class="info-date-cell"><b>Roasted</b><span data-info-value="roast_date">&nbsp;</span></div>
            <div class="info-date-cell"><b>Best Before</b><span data-info-value="expiry_date">&nbsp;</span></div>
          </div>
          <div class="label-pack-footer"><span data-info-value="bean_type">Whole Bean</span><span data-info-value="weight_text">200 g</span></div>
        </div>
        <img class="label-logo" data-block="logo" src="<?php echo html_escape($logoUrl); ?>" alt="NAMUA">
        <?php foreach (['coffee_name','origin','process_method','roast_level','weight_text','tasting_notes','brew_suggestion','batch_no','roast_date','expiry_date','description'] as $block): ?><div class="label-text" data-block="<?php echo $block; ?>"></div><?php endforeach; ?>
      </div></div><small class="text-muted d-block mt-3"><i class="ri ri-lightbulb-line me-1"></i>Gunakan artwork PNG sebagai dasar, lalu teks batch tetap bisa diubah cepat.</small></div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$formMode): ?>
  <div class="label-panel">
    <div class="label-panel-head flex-wrap align-items-start">
      <div>
        <h5>Daftar Label Packaging</h5>
        <small class="text-muted">Terbaru paling atas. Edit, duplikat, atau nonaktifkan label lama dari sini.</small>
      </div>
      <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 ms-lg-auto">
        <div class="label-status-tabs">
          <?php foreach ($statusTabs as $statusKey => $statusLabel): ?>
            <?php
              $tabQuery = ['status' => $statusKey];
              if (trim((string)($filters['q'] ?? '')) !== '') {
                  $tabQuery['q'] = trim((string)$filters['q']);
              }
              $tabActive = $currentStatus === $statusKey;
            ?>
            <a class="label-status-tab <?php echo $tabActive ? 'active' : ''; ?>" href="<?php echo site_url('roastery/packaging-labels?'.http_build_query($tabQuery)); ?>">
              <?php if ($statusKey === 'ACTIVE'): ?><i class="ri ri-checkbox-circle-line"></i><?php elseif ($statusKey === 'INACTIVE'): ?><i class="ri ri-pause-circle-line"></i><?php else: ?><i class="ri ri-stack-line"></i><?php endif; ?>
              <?php echo html_escape($statusLabel); ?>
            </a>
          <?php endforeach; ?>
        </div>
        <form class="d-flex flex-wrap gap-2" method="get" action="<?php echo site_url('roastery/packaging-labels'); ?>">
          <input type="hidden" name="status" value="<?php echo html_escape($currentStatus); ?>">
          <input class="form-control" name="q" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Cari nama/origin" style="min-width:220px">
          <button class="btn btn-outline-danger" type="submit"><i class="ri ri-search-line me-1"></i>Filter</button>
          <?php if (trim((string)($filters['q'] ?? '')) !== ''): ?><a class="btn btn-outline-secondary" href="<?php echo site_url('roastery/packaging-labels?status='.rawurlencode($currentStatus)); ?>">Clear</a><?php endif; ?>
        </form>
        <?php if (!empty($can_create)): ?><a class="btn btn-danger" href="<?php echo site_url('roastery/packaging-labels?new=1'); ?>"><i class="ri ri-add-line me-1"></i>Tambah Label</a><?php endif; ?>
      </div>
    </div>
    <div class="label-panel-body">
      <?php if (empty($labels)): ?>
        <div class="text-center py-5">
          <div class="mb-2 fw-bold text-muted">Belum ada label tersimpan.</div>
          <?php if (!empty($can_create)): ?><a class="btn btn-danger" href="<?php echo site_url('roastery/packaging-labels?new=1'); ?>"><i class="ri ri-add-line me-1"></i>Buat Label Pertama</a><?php endif; ?>
        </div>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($labels as $row): ?>
            <div class="col-md-6 col-xl-4"><div class="saved-card h-100">
              <div class="saved-thumb"><?php if (!empty($row['image_path'])): ?><img src="<?php echo html_escape(base_url($row['image_path'])); ?>" alt=""><?php endif; ?></div>
              <div class="flex-grow-1 min-w-0">
                <div class="d-flex justify-content-between gap-2">
                  <div class="saved-title text-truncate"><?php echo html_escape((string)$row['coffee_name']); ?></div>
                  <span class="badge <?php echo (int)($row['is_active'] ?? 1) === 1 ? 'bg-label-success' : 'bg-label-secondary'; ?>"><?php echo (int)($row['is_active'] ?? 1) === 1 ? 'Aktif' : 'Nonaktif'; ?></span>
                </div>
                <div class="small text-muted text-truncate"><?php echo html_escape(trim((string)$row['origin'].' '.(string)$row['weight_text'])); ?></div>
                <div class="small text-muted text-truncate"><?php echo html_escape((string)($row['label_code'] ?? '')); ?></div>
                <div class="saved-actions">
                  <a class="btn btn-sm btn-outline-dark" href="<?php echo site_url('roastery/packaging-labels/print/'.(int)$row['id']); ?>"><i class="ri ri-printer-line"></i>Cetak</a>
                  <?php if (!empty($can_edit)): ?><a class="btn btn-sm btn-outline-primary" href="<?php echo site_url('roastery/packaging-labels?edit='.(int)$row['id']); ?>"><i class="ri ri-edit-line"></i>Edit</a><?php endif; ?>
                  <?php if (!empty($can_create)): ?><a class="btn btn-sm btn-outline-warning" href="<?php echo site_url('roastery/packaging-labels/duplicate/'.(int)$row['id']); ?>" onclick="return confirm('Duplikat label ini sebagai template baru?')"><i class="ri ri-file-copy-line"></i>Duplikat</a><?php endif; ?>
                  <?php if ((int)($row['is_active'] ?? 1) === 0): ?>
                    <?php if (!empty($can_edit)): ?><a class="btn btn-sm btn-success btn-wide" href="<?php echo site_url('roastery/packaging-labels/activate/'.(int)$row['id']); ?>" onclick="return confirm('Aktifkan kembali label ini?')"><i class="ri ri-checkbox-circle-line"></i>Aktifkan</a><?php endif; ?>
                  <?php elseif (!empty($can_delete)): ?>
                    <a class="btn btn-sm btn-outline-danger" href="<?php echo site_url('roastery/packaging-labels/delete/'.(int)$row['id']); ?>" onclick="return confirm('Nonaktifkan label ini?')"><i class="ri ri-delete-bin-line"></i>Nonaktif</a>
                  <?php endif; ?>
                </div>
              </div>
            </div></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if ($formMode): ?>
<script>
(function(){
const initialRaw=<?php echo json_encode($designJson, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
const el=id=>document.getElementById(id), fields=[...document.querySelectorAll('[data-label-field]')], metaFields=[...document.querySelectorAll('[data-meta-field]')], by=n=>document.querySelector('[data-label-field="'+n+'"]'), metaBy=n=>document.querySelector('[data-meta-field="'+n+'"]');
const canvas=el('labelCanvas'), bg=el('labelBg'), img=el('labelImagePreview'), designInput=el('designJsonInput'), blockSelect=el('blockSelect'), blockSourceHint=el('blockSourceHint');
const canvasWidthEl=el('canvasWidth'), canvasHeightEl=el('canvasHeight'), labelWidthRange=el('labelWidthRange'), labelHeightRange=el('labelHeightRange'), labelWidthOut=el('labelWidthOut'), labelHeightOut=el('labelHeightOut'), themePresetEl=el('themePreset'), artworkModeEl=el('artworkMode'), imageInput=el('labelImageInput'), logoInput=el('logoImageInput'), galleryPath=el('galleryImagePath'), galleryLogoPath=el('galleryLogoPath'), formEl=el('coffeeLabelForm'), printBtn=el('printLabelBtn'), resetPremiumLayout=el('resetPremiumLayout'), logoEl=document.querySelector('.label-logo[data-block="logo"]'), tasteRows=el('tasteRows'), addTasteNote=el('addTasteNote'), tastingNotesValue=el('tastingNotesValue'), tasteIconRow=document.querySelector('.taste-icon-row');
const c={fontFamily:el('fontFamily'),fontColor:el('fontColor'),fontSize:el('fontSize'),posX:el('posX'),posY:el('posY'),blockWidth:el('blockWidth'),panelHeight:el('panelHeight'),letterSpacing:el('letterSpacing')};
const o={fontSize:el('fontSizeOut'),posX:el('posXOut'),posY:el('posYOut'),blockWidth:el('blockWidthOut'),panelHeight:el('panelHeightOut'),letterSpacing:el('letterSpacingOut')};
const defaults={canvas:{theme:'heritage-cream',artworkMode:'full'},meta:{body_level:'Light - Medium',elevation_text:'>1200 mdpl',bean_type:'Whole Bean',footer_note:''},tasteIcons:['ri-seedling-line','ri-flower-line','ri-cup-line'],blocks:{logo:{x:14,y:8,w:10,size:24,font:'Jost',color:'#2f2018',bold:true,align:'center',letter:0},brand_mark:{x:7,y:4,w:26,size:9,font:'Space Grotesk',color:'#fff6d6',bold:true,uppercase:true,align:'left',letter:2.4},watermark:{x:14,y:18,w:72,size:84,font:'Bebas Neue',color:'rgba(255,255,255,.10)',bold:false,uppercase:true,align:'center',letter:6},roastery_kicker:{x:14,y:20,w:72,size:6.6,font:'Space Grotesk',color:'rgba(64,36,24,.62)',bold:true,uppercase:true,align:'center',letter:4.2},coffee_name:{x:12,y:31,w:76,size:31,font:'Cormorant Garamond',color:'#26150f',bold:true,italic:false,uppercase:true,align:'center',letter:4.6},origin:{x:18,y:50,w:64,size:9,font:'Space Grotesk',color:'#3c261c',bold:true,uppercase:true,align:'center',letter:4},process_method:{x:16,y:73,w:22,size:8,font:'Space Grotesk',color:'#3c261c',bold:true,uppercase:true,align:'center',letter:1.2},roast_level:{x:39,y:73,w:22,size:8,font:'Space Grotesk',color:'#3c261c',bold:true,uppercase:true,align:'center',letter:1.2},weight_text:{x:66,y:87,w:22,size:8,font:'Space Grotesk',color:'#3c261c',bold:true,uppercase:true,align:'center',letter:1.1},tasting_notes:{x:15,y:40,w:70,size:7.8,font:'Jost',color:'#5f3c2b',bold:false,italic:false,align:'center',letter:.65},taste_icons:{x:18,y:47,w:64,size:13,font:'Jost',color:'#5d3827',bold:false,align:'center',letter:0},brew_suggestion:{x:18,y:46,w:64,size:6.2,font:'Space Grotesk',color:'#755039',bold:true,uppercase:true,align:'center',letter:1},info_panel:{x:10,y:54,w:80,h:39,size:7,font:'Space Grotesk',color:'#3e261b',align:'left',letter:.03},batch_no:{x:16,y:82,w:22,size:7,font:'Space Grotesk',color:'#3c261c',align:'center',letter:.8},roast_date:{x:39,y:82,w:22,size:7,font:'Space Grotesk',color:'#3c261c',align:'center',letter:.8},expiry_date:{x:62,y:82,w:22,size:7,font:'Space Grotesk',color:'#3c261c',align:'center',letter:.8},description:{x:14,y:94,w:72,size:6,font:'Jost',color:'#6a4937',align:'center',letter:.25}}};
function clone(x){return JSON.parse(JSON.stringify(x))}function parsed(){try{return JSON.parse(initialRaw||'{}')||{}}catch(e){return{}}}
function merge(a,b){const r=clone(a);if(b.canvas)Object.assign(r.canvas,b.canvas);if(b.meta)Object.assign(r.meta,b.meta);if(Array.isArray(b.tasteIcons))r.tasteIcons=b.tasteIcons;if(b.blocks)Object.keys(b.blocks).forEach(k=>r.blocks[k]=Object.assign(r.blocks[k]||{},b.blocks[k]));return r}
const initialDesign=parsed();
let state=merge(defaults,initialDesign), active=blockSelect.value;
const blockHints={
  logo:'Sumber: upload logo / galeri logo.',
  coffee_name:'Sumber: field Nama Kopi.',
  roastery_kicker:'Sumber: field Footer Mini.',
  tasting_notes:'Sumber: builder Tasting Notes + Icon.',
  taste_icons:'Sumber: ikon pada builder Tasting Notes + Icon.',
  brew_suggestion:'Sumber: field Brew Suggestion.',
  info_panel:'Sumber: gabungan field Roast Level, Body, Origin, Elevation, Process, Batch, Tanggal Roast, Best Before, Bean/Grind, dan Berat.'
};
function near(a,b){return Math.abs((parseFloat(a)||0)-b)<0.01}
if((parseFloat(state.blocks?.brew_suggestion?.y)||0)>=49){state.blocks.tasting_notes.y=40;state.blocks.tasting_notes.size=7.8;state.blocks.brew_suggestion.y=46;state.blocks.brew_suggestion.size=6.2}
if(!Array.isArray(initialDesign.tasteIcons)){state.tasteIcons=noteList().map(suggestIcon)}
let forceBlankTasteRow=false;
const iconChoices=[
  ['','Tanpa ikon'],['ri-seedling-line','Seed / earthy'],['ri-flower-line','Floral'],['ri-cup-line','Tea / cup'],['ri-leaf-line','Leaf / herbal'],['ri-sun-line','Citrus / bright'],['ri-fire-line','Spice'],['ri-cake-3-line','Sweet / cake'],['ri-drop-line','Juicy'],['ri-contrast-drop-line','Chocolate'],['ri-plant-line','Nutty'],['ri-sparkling-2-line','Bright']
];
function suggestIcon(note){const t=(note||'').toLowerCase();if(/floral|flower|jasmine|rose|lavender/.test(t))return'ri-flower-line';if(/tea|teh|oolong|earl|black tea/.test(t))return'ri-cup-line';if(/citrus|orange|lemon|lime|jeruk|grapefruit|apricot|peach|berry|fruit/.test(t))return'ri-sun-line';if(/choco|cocoa|coklat|dark/.test(t))return'ri-contrast-drop-line';if(/nut|almond|hazelnut|peanut|kacang/.test(t))return'ri-plant-line';if(/spice|cinnamon|clove|ginger|rempah/.test(t))return'ri-fire-line';if(/sweet|caramel|sugar|honey|vanilla|cake/.test(t))return'ri-cake-3-line';if(/juicy|wine|syrup/.test(t))return'ri-drop-line';if(/herbal|leaf|green/.test(t))return'ri-leaf-line';return'ri-seedling-line'}
function noteList(){return (tastingNotesValue?.value||'').split(',').map(s=>s.trim()).filter(Boolean)}
function syncTasteValueFromRows(){const notes=[...tasteRows.querySelectorAll('[data-taste-note]')].map(i=>i.value.trim()).filter(Boolean);tastingNotesValue.value=notes.join(', ');state.tasteIcons=[...tasteRows.querySelectorAll('[data-taste-icon]')].map((s,idx)=>s.value||suggestIcon(notes[idx]||''));refresh()}
function renderTasteIconRow(){const notes=noteList();if(!tasteIconRow)return;tasteIconRow.innerHTML='';notes.slice(0,4).forEach((note,idx)=>{const icon=(state.tasteIcons&&state.tasteIcons[idx])||suggestIcon(note);if(!icon)return;const span=document.createElement('span');span.title=note;span.innerHTML='<i class="ri '+icon+'"></i>';tasteIconRow.appendChild(span)});tasteIconRow.style.display=tasteIconRow.children.length?'flex':'none'}
function renderTasteRows(){const notes=noteList();const list=notes.length?notes:[''];if(forceBlankTasteRow&&notes.length&&list.length<6){list.push('')}forceBlankTasteRow=false;tasteRows.innerHTML='';list.slice(0,6).forEach((note,idx)=>{const row=document.createElement('div');row.className='taste-row';const input=document.createElement('input');input.className='form-control';input.placeholder='Contoh: Apricot';input.value=note;input.setAttribute('data-taste-note','1');const select=document.createElement('select');select.className='form-select';select.setAttribute('data-taste-icon','1');iconChoices.forEach(([val,label])=>{const opt=document.createElement('option');opt.value=val;opt.textContent=label;select.appendChild(opt)});select.value=(state.tasteIcons&&state.tasteIcons[idx])||suggestIcon(note);const preview=document.createElement('div');preview.className='taste-icon-preview';const paint=()=>{preview.innerHTML=select.value?'<i class="ri '+select.value+'"></i>':'-'};const remove=document.createElement('button');remove.type='button';remove.className='btn btn-sm btn-outline-danger';remove.innerHTML='<i class="ri ri-close-line"></i>';input.addEventListener('input',()=>{if(!select.dataset.userPicked){select.value=suggestIcon(input.value)}paint();syncTasteValueFromRows()});select.addEventListener('change',()=>{select.dataset.userPicked='1';paint();syncTasteValueFromRows()});remove.addEventListener('click',()=>{row.remove();syncTasteValueFromRows();if(!tasteRows.children.length){tastingNotesValue.value='';renderTasteRows();refresh()}});preview.addEventListener('click',()=>select.focus());paint();row.appendChild(input);row.appendChild(select);row.appendChild(preview);row.appendChild(remove);tasteRows.appendChild(row)})}
function hydrateMetaFields(){metaFields.forEach(f=>{const k=f.dataset.metaField;const legacy=k==='elevation_text'?(state.meta&&state.meta.elevation):'';f.value=(state.meta&&state.meta[k])||legacy||(defaults.meta&&defaults.meta[k])||''})}
function syncMetaFromFields(){state.meta=state.meta||{};metaFields.forEach(f=>{state.meta[f.dataset.metaField]=(f.value||'').trim()})}
function textValue(name,fallback=''){const field=by(name);return ((field&&field.value)||fallback||'').trim()}
function metaValue(name,fallback=''){const field=metaBy(name);return ((field&&field.value)||(state.meta&&state.meta[name])||fallback||'').trim()}
function dotLevel(v){const t=(v||'').toLowerCase().replace(/\s+/g,' ').trim();if(t==='dark'||t==='full'||t==='espresso roast')return 5;if(t==='medium - dark'||t==='medium-dark'||t==='medium - full'||t==='medium-full'||t==='omni roast')return 4;if(t==='medium'||t==='filter roast')return 3;if(t==='light - medium'||t==='light-medium')return 2;if(t==='light')return 1;return 3}
function paintDots(key,val){const wrap=document.querySelector('[data-info-dots="'+key+'"]');if(!wrap)return;const level=dotLevel(val);wrap.innerHTML='';for(let i=1;i<=5;i++){const dot=document.createElement('span');dot.className='info-dot'+(i<=level?' filled':'');wrap.appendChild(dot)}}
function setInfo(key,value){document.querySelectorAll('[data-info-value="'+key+'"]').forEach(e=>e.textContent=value)}
function renderInfoPanel(){const blank='\u00a0',roast=textValue('roast_level','Light - Medium')||'Light - Medium',body=metaValue('body_level','Light - Medium')||'Light - Medium',origin=textValue('origin','Mt. Ijen, East Java')||'Mt. Ijen, East Java',elevation=metaValue('elevation_text',state.meta&&state.meta.elevation?state.meta.elevation:'>1200 mdpl')||'>1200 mdpl',process=textValue('process_method','Natural')||'Natural',bean=metaValue('bean_type','Whole Bean')||'Whole Bean',weight=textValue('weight_text','200 g')||'200 g',footer=metaValue('footer_note',''),batch=textValue('batch_no',''),roasted=textValue('roast_date',''),bestBefore=textValue('expiry_date','');setInfo('roast_level',roast);setInfo('body_level',body);setInfo('origin',origin);setInfo('elevation',elevation);setInfo('process_method',process);setInfo('bean_type',bean);setInfo('weight_text',weight);setInfo('footer_note',footer);setInfo('batch_no',batch||blank);setInfo('roast_date',roasted||blank);setInfo('expiry_date',bestBefore||blank);document.querySelectorAll('[data-info-value="footer_note"]').forEach(e=>{e.style.display=footer?'block':'none'});paintDots('roast_level',roast);paintDots('body_level',body)}
function val(n){const v=(by(n)?.value||'').trim(); if(n==='coffee_name')return v||'NAMUA COFFEE'; if(n==='origin')return v?'ORIGIN '+v:'ORIGIN NUSANTARA'; if(n==='process_method')return v||'PROCESS'; if(n==='roast_level')return v||'ROAST'; if(n==='weight_text')return v||'200 g'; if(n==='tasting_notes')return v||'Chocolate, citrus, brown sugar'; if(n==='brew_suggestion')return v||'Filter / Espresso'; if(n==='batch_no')return v?'BATCH '+v:'BATCH -'; if(n==='roast_date')return v?'ROASTED '+v:'ROASTED -'; if(n==='expiry_date')return v?'BEST BEFORE '+v:'BEST BEFORE -'; if(n==='description')return v||metaValue('footer_note','Roasted in small batch by NAMUA Roastery.'); return v}
function blockValue(n){if(n==='brand_mark')return'NAMUA ROASTERY'; if(n==='watermark')return'NAMUA'; if(n==='roastery_kicker')return metaValue('footer_note','Roasted in small batch'); return val(n)}
function getBlockElement(n){if(n==='info_panel')return document.querySelector('.label-info-panel[data-block="info_panel"]'); if(n==='logo')return document.querySelector('.label-logo[data-block="logo"]'); if(n==='taste_icons')return document.querySelector('.taste-icon-row[data-block="taste_icons"]'); if(n==='roastery_kicker')return document.querySelector('.label-roastery-kicker[data-block="roastery_kicker"]'); if(n==='watermark')return document.querySelector('.label-watermark[data-block="watermark"]'); if(n==='brand_mark')return document.querySelector('.label-mark[data-block="brand_mark"]'); return document.querySelector('.label-text[data-block="'+n+'"]')}
function apply(n){const s=state.blocks[n]||{}, node=getBlockElement(n); if(!node)return; if(n==='info_panel'){node.style.left=(s.x||10)+'%';node.style.top=(s.y||54)+'%';node.style.right='auto';node.style.bottom='auto';node.style.width=(s.w||80)+'%';node.style.height=(s.h||39)+'%';node.style.fontFamily='"'+(s.font||'Space Grotesk')+'",sans-serif';node.style.color=s.color||'#3e261b';node.style.letterSpacing=(s.letter||.03)+'px';node.classList.toggle('active',n===active);return} if(n==='logo'){node.style.left=(s.x||0)+'%';node.style.top=(s.y||0)+'%';node.style.width=(s.w||20)+'%';node.style.height='auto';node.classList.toggle('active',n===active);return} if(n==='taste_icons'){node.style.left=(s.x||18)+'%';node.style.top=(s.y||47)+'%';node.style.right='auto';node.style.width=(s.w||64)+'%';node.style.fontSize=(s.size||13)+'px';node.style.color=s.color||'#5d3827';node.style.justifyContent=s.align==='left'?'flex-start':(s.align==='right'?'flex-end':'center');node.classList.toggle('active',n===active);return} node.textContent=s.uppercase?blockValue(n).toUpperCase():blockValue(n); node.style.left=(s.x||0)+'%'; node.style.top=(s.y||0)+'%'; node.style.right='auto'; node.style.width=(s.w||50)+'%'; node.style.fontSize=(s.size||12)+'px'; node.style.fontFamily='"'+(s.font||'Jost')+'",sans-serif'; node.style.color=s.color||'#2c1711'; node.style.fontWeight=s.bold?'900':'500'; node.style.fontStyle=s.italic?'italic':'normal'; node.style.textAlign=s.align||'left'; node.style.letterSpacing=(s.letter||0)+'px'; node.classList.toggle('active',n===active)}
function syncSizeControls(w,h){canvasWidthEl.value=w;canvasHeightEl.value=h;labelWidthRange.value=w;labelHeightRange.value=h;labelWidthOut.textContent=w+'mm';labelHeightOut.textContent=h+'mm'}
function refresh(){syncMetaFromFields();const w=Math.max(40,Math.min(160,parseInt(canvasWidthEl.value||90,10))),h=Math.max(60,Math.min(240,parseInt(canvasHeightEl.value||140,10))),t=themePresetEl.value||'heritage-cream',m=artworkModeEl.value||'full'; syncSizeControls(w,h); state.canvas.theme=t; state.canvas.artworkMode=m; canvas.style.setProperty('--label-preview-w',(w*4)+'px'); canvas.style.setProperty('--label-preview-h',(h*4)+'px'); canvas.style.setProperty('--label-print-w',w+'mm'); canvas.style.setProperty('--label-print-h',h+'mm'); canvas.className='label-canvas theme-'+t+' artwork-mode-'+m; Object.keys(state.blocks).forEach(apply); renderTasteIconRow(); renderInfoPanel(); designInput.value=JSON.stringify(state)}
function outs(){o.fontSize.textContent=c.fontSize.value+'px';o.posX.textContent=c.posX.value+'%';o.posY.textContent=c.posY.value+'%';o.blockWidth.textContent=c.blockWidth.value+'%';o.panelHeight.textContent=c.panelHeight.value+'%';o.letterSpacing.textContent=c.letterSpacing.value+'px'}
function load(){const fallback=defaults.blocks[active]||{},s=Object.assign({},fallback,state.blocks[active]||{});c.fontFamily.value=s.font||(active==='info_panel'?'Space Grotesk':'Jost');c.fontColor.value=s.color||(active==='info_panel'?'#3e261b':'#7d1720');c.fontSize.value=s.size||(active==='info_panel'?7:12);c.posX.value=s.x||0;c.posY.value=s.y||0;c.blockWidth.value=s.w||(active==='info_panel'?80:50);c.panelHeight.value=s.h||(active==='info_panel'?39:28);c.letterSpacing.value=s.letter||0;if(blockSourceHint){blockSourceHint.textContent=blockHints[active]||'Sumber: elemen visual label.'}if(c.panelHeight){c.panelHeight.disabled=active!=='info_panel';c.panelHeight.closest('.range-line').style.opacity=active==='info_panel'?'1':'.45'}outs();refresh()}
function save(){const s=state.blocks[active]||(state.blocks[active]={});s.font=c.fontFamily.value;s.color=c.fontColor.value;s.size=+c.fontSize.value||12;s.x=+c.posX.value||0;s.y=+c.posY.value||0;s.w=+c.blockWidth.value||50;if(active==='info_panel')s.h=+c.panelHeight.value||39;s.letter=+c.letterSpacing.value||0;refresh()}
fields.forEach(e=>{e.addEventListener('input',refresh);e.addEventListener('change',refresh)});metaFields.forEach(e=>{e.addEventListener('input',refresh);e.addEventListener('change',refresh)});[canvasWidthEl,canvasHeightEl,themePresetEl,artworkModeEl].forEach(e=>{e.addEventListener('input',refresh);e.addEventListener('change',refresh)});labelWidthRange.addEventListener('input',function(){canvasWidthEl.value=this.value;refresh()});labelHeightRange.addEventListener('input',function(){canvasHeightEl.value=this.value;refresh()});Object.values(c).forEach(e=>e.addEventListener('input',()=>{outs();save()}));
blockSelect.addEventListener('change',function(){active=this.value;load()});document.querySelectorAll('[data-block]').forEach(e=>e.addEventListener('click',function(){active=this.dataset.block;blockSelect.value=active;load()}));
document.querySelectorAll('[data-toggle-style]').forEach(b=>b.addEventListener('click',function(){const s=state.blocks[active]||(state.blocks[active]={});s[this.dataset.toggleStyle]=!s[this.dataset.toggleStyle];refresh()}));document.querySelectorAll('[data-align]').forEach(b=>b.addEventListener('click',function(){(state.blocks[active]||(state.blocks[active]={})).align=this.dataset.align;refresh()}));
resetPremiumLayout.addEventListener('click',function(){state=merge(defaults,{});themePresetEl.value='heritage-cream';artworkModeEl.value='full';active='logo';blockSelect.value=active;hydrateMetaFields();renderTasteRows();load()});
addTasteNote.addEventListener('click',function(){forceBlankTasteRow=true;renderTasteRows();refresh()});
document.querySelectorAll('#artworkGallery .gallery-tile').forEach(tile=>tile.addEventListener('click',function(){document.querySelectorAll('#artworkGallery .gallery-tile').forEach(t=>t.classList.remove('active'));this.classList.add('active');galleryPath.value=this.dataset.path||'';img.src=this.dataset.url||'';img.style.display='';bg.classList.remove('no-image')}));
document.querySelectorAll('#logoGallery .gallery-tile').forEach(tile=>tile.addEventListener('click',function(){document.querySelectorAll('#logoGallery .gallery-tile').forEach(t=>t.classList.remove('active'));this.classList.add('active');galleryLogoPath.value=this.dataset.path||'';if(logoEl)logoEl.src=this.dataset.url||logoEl.src}));
imageInput.addEventListener('change',function(){const f=this.files&&this.files[0];if(!f)return;if(f.type!=='image/png'){alert('Artwork harus PNG.');this.value='';return}galleryPath.value='';document.querySelectorAll('#artworkGallery .gallery-tile').forEach(t=>t.classList.remove('active'));const r=new FileReader();r.onload=e=>{img.src=e.target.result;img.style.display='';bg.classList.remove('no-image')};r.readAsDataURL(f)});
logoInput.addEventListener('change',function(){const f=this.files&&this.files[0];if(!f)return;if(f.type!=='image/png'){alert('Logo harus PNG.');this.value='';return}galleryLogoPath.value='';document.querySelectorAll('#logoGallery .gallery-tile').forEach(t=>t.classList.remove('active'));const r=new FileReader();r.onload=e=>{if(logoEl)logoEl.src=e.target.result};r.readAsDataURL(f)});
function openPrint(){refresh();document.body.classList.add('coffee-label-printing');window.print();setTimeout(()=>document.body.classList.remove('coffee-label-printing'),500)}
formEl.addEventListener('submit',function(){syncTasteValueFromRows();syncMetaFromFields();refresh()});printBtn.addEventListener('click',openPrint);syncMetaFromFields();renderTasteRows();load();
<?php if ($autoPrint): ?>setTimeout(openPrint,700);<?php endif; ?>
})();
</script>
<?php endif; ?>
