<?php
$months = [
  '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
  '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];
$selectedMonth = (string)($selected_month ?? date('m'));
$selectedYear = (string)($selected_year ?? date('Y'));
$employees = $employees ?? [];
$scheduleMap = $schedule_map ?? [];
$shiftCodes = $shift_codes ?? [];
$holidayMap = array_flip($holiday_dates ?? []);
$todayDate = date('Y-m-d');
$daysInMonth = (int)date('t', strtotime($selectedYear . '-' . $selectedMonth . '-01'));
?>
<style>
  .schedule-v2-wrap { overflow:auto; max-height:72vh; border:1px solid #d8dee4; border-radius:10px; background:#fff; position: relative; }
  .schedule-v2-table { border-collapse:separate; border-spacing:0; min-width:1200px; }
  .schedule-v2-table th, .schedule-v2-table td { border:1px solid #d7dfe7 !important; }
  .schedule-v2-table thead th { position:sticky; top:0; background:#eef2f6; z-index:7; }
  .schedule-v2-fixed { position:sticky; left:0; background:#f9fbfd; min-width:240px; max-width:240px; border-right:2px solid #aeb8c2 !important; background-clip:padding-box; box-shadow:2px 0 0 #aeb8c2; }
  .schedule-v2-table thead .schedule-v2-fixed { z-index:6; }
  .schedule-v2-table tbody .schedule-v2-fixed { z-index:5; }
  .schedule-v2-cell { min-width:58px; text-align:center; font-weight:700; background:#fbf8ea; }
  .schedule-v2-cell[contenteditable="true"] { background:#fffbe8; outline:none; }
  .schedule-v2-cell.is-error { background:#ffe8e8; color:#9f1d1d; }
  .schedule-v2-cell.is-holiday { background:#ffe4e1; border-color:#ffb3ab !important; }
  .schedule-v2-cell.is-holiday[contenteditable="true"] { background:#ffd7d2; }
  .schedule-v2-today-head {
    background: #ffb020 !important;
    color: #1f2937 !important;
    border-color: #f08c00 !important;
    box-shadow: inset 0 -3px 0 #b45309;
  }
  .schedule-v2-today-cell {
    background: #fff2cc !important;
    border-color: #f59e0b !important;
    box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.45);
  }
  .schedule-v2-cell.is-holiday.schedule-v2-today-cell {
    background: #ffd9c7 !important;
    border-color: #fb923c !important;
  }
  .schedule-v2-legend code { padding:.15rem .35rem; background:#f1f5f9; border-radius:6px; }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h5 class="fw-bold mb-0"><i class="ri-table-line me-2 text-primary"></i><?php echo html_escape($title ?? 'Jadwal Shift (Spreadsheet)'); ?></h5>
  <a href="<?php echo site_url('attendance/schedules'); ?>" class="btn btn-outline-secondary">Versi Tabel</a>
</div>

<div class="card border-0 shadow-sm mb-3"><div class="card-body">
  <form method="get" action="<?php echo site_url('attendance/schedules-v2'); ?>" class="row g-2 align-items-end">
    <div class="col-6 col-md-2"><label class="form-label">Bulan</label><select name="month" class="form-select"><?php foreach($months as $k=>$v): ?><option value="<?php echo $k; ?>" <?php echo $selectedMonth===$k?'selected':''; ?>><?php echo html_escape($v); ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-2"><label class="form-label">Tahun</label><input type="number" name="year" min="2000" max="2100" class="form-control" value="<?php echo html_escape($selectedYear); ?>"></div>
    <div class="col-12 col-md-2"><button type="submit" class="btn btn-primary w-100">Tampilkan</button></div>
    <div class="col-12 col-md-6 text-md-end schedule-v2-legend">
      <small class="text-muted">Isi sel dengan kode shift. Kosongkan sel untuk hapus jadwal. Kode aktif:
        <?php foreach($shiftCodes as $s): ?>
          <code><?php echo html_escape((string)($s['shift_code'] ?? '')); ?></code>
        <?php endforeach; ?>
      </small>
    </div>
  </form>
</div></div>

<div id="scheduleWarn" class="alert alert-danger d-none"></div>

<div class="schedule-v2-wrap">
  <table class="table table-sm table-bordered schedule-v2-table mb-0">
    <thead>
      <tr>
        <th class="schedule-v2-fixed">Pegawai</th>
        <?php for($d=1;$d<=$daysInMonth;$d++): $date = $selectedYear . '-' . $selectedMonth . '-' . str_pad((string)$d,2,'0',STR_PAD_LEFT); $isHoliday = isset($holidayMap[$date]); $isToday = ($date === $todayDate); ?>
          <th class="schedule-v2-cell<?php echo $isHoliday ? ' is-holiday' : ''; ?><?php echo $isToday ? ' schedule-v2-today-head' : ''; ?>"><?php echo str_pad((string)$d,2,'0',STR_PAD_LEFT); ?></th>
        <?php endfor; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach($employees as $emp): ?>
        <tr>
          <td class="schedule-v2-fixed"><?php echo html_escape(((string)($emp->employee_code ?? '-')) . ' - ' . ((string)($emp->employee_name ?? '-'))); ?></td>
          <?php for($d=1;$d<=$daysInMonth;$d++): $date = $selectedYear . '-' . $selectedMonth . '-' . str_pad((string)$d,2,'0',STR_PAD_LEFT); $val = $scheduleMap[(int)$emp->id][$date] ?? ''; $isHoliday = isset($holidayMap[$date]); $isToday = ($date === $todayDate); ?>
            <td class="schedule-v2-cell<?php echo $isHoliday ? ' is-holiday' : ''; ?><?php echo $isToday ? ' schedule-v2-today-cell' : ''; ?>" contenteditable="true" data-employee-id="<?php echo (int)$emp->id; ?>" data-date="<?php echo html_escape($date); ?>" data-original="<?php echo html_escape((string)$val); ?>"><?php echo html_escape((string)$val); ?></td>
          <?php endfor; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
(function(){
  var warnEl = document.getElementById('scheduleWarn');
  function showWarn(msg){ warnEl.textContent = msg; warnEl.classList.remove('d-none'); }
  function clearWarn(){ warnEl.textContent = ''; warnEl.classList.add('d-none'); }

  function saveCell(cell){
    var employeeId = cell.getAttribute('data-employee-id');
    var date = cell.getAttribute('data-date');
    var shiftCode = (cell.textContent || '').trim().toUpperCase();
    var original = (cell.getAttribute('data-original') || '').trim().toUpperCase();
    if (shiftCode === original) {
      cell.textContent = shiftCode;
      return;
    }

    fetch(<?php echo json_encode(site_url('attendance/schedules-v2/save')); ?>, {
      method: 'POST',
      headers: {'Content-Type':'application/json', 'X-Requested-With':'XMLHttpRequest'},
      body: JSON.stringify({employee_id: employeeId, schedule_date: date, shift_code: shiftCode})
    })
    .then(function(r){ return r.json().then(function(j){ return {ok:r.ok, j:j}; }); })
    .then(function(res){
      if (!res.ok || !res.j || Number(res.j.ok) !== 1) {
        cell.classList.add('is-error');
        showWarn((res.j && res.j.message) ? res.j.message : 'Gagal simpan jadwal.');
        return;
      }
      cell.classList.remove('is-error');
      cell.setAttribute('data-original', shiftCode);
      cell.textContent = shiftCode;
      clearWarn();
    })
    .catch(function(){
      cell.classList.add('is-error');
      showWarn('Terjadi kesalahan koneksi saat menyimpan jadwal.');
    });
  }

  document.querySelectorAll('.schedule-v2-cell[contenteditable="true"]').forEach(function(cell){
    cell.addEventListener('blur', function(){ saveCell(cell); });
    cell.addEventListener('keydown', function(e){
      if (e.key === 'Enter') {
        e.preventDefault();
        cell.blur();
      }
    });
  });
})();
</script>
