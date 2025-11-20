<?php
// index.php - GST E-Cube Dashboard (Design B)
// Place this file alongside your working db_connect.php
$table = 'gst_tbl';
require_once 'db_connect.php'; // expects $conn (mysqli)

// Send JSON helper
function send_json($arr) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

$cols_all = "`SN`,`ID`,`Latitude`,`Longitude`,`Date`,`Time`,`Day`,`Status`,`WiFi_Strength`,`IP_Address`,`Temperature`,`Pressure`,`Humidity`,`Depth`";

// API endpoints
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    // get_latest
    if ($action === 'get_latest') {
        $sql = "SELECT $cols_all FROM `$table` ORDER BY `SN` DESC LIMIT 1";
        if ($res = $conn->query($sql)) {
            if ($row = $res->fetch_assoc()) {
                $data = [
                    'station_id'    => $row['ID'] ?? null,
                    'latitude'      => $row['Latitude'] ?? null,
                    'longitude'     => $row['Longitude'] ?? null,
                    'date'          => $row['Date'] ?? null,   // MM/DD/YYYY
                    'time'          => $row['Time'] ?? null,   // HH:MM:SS
                    'day'           => $row['Day'] ?? null,
                    'status'        => isset($row['Status']) ? (int)$row['Status'] : 0,
                    'wifi_strength' => $row['WiFi_Strength'] ?? null, // dBm
                    'ip_address'    => $row['IP_Address'] ?? null,
                    'temperature'   => isset($row['Temperature']) ? (float)$row['Temperature'] : null,
                    'pressure'      => isset($row['Pressure']) ? (float)$row['Pressure'] : null,
                    'humidity'      => isset($row['Humidity']) ? (float)$row['Humidity'] : null,
                    'depth'         => isset($row['Depth']) ? (float)$row['Depth'] : null,
                    'raw'           => $row
                ];
                send_json(['success' => true, 'data' => $data]);
            }
        }
        send_json(['success' => false, 'message' => 'No data']);
    }

    // get_live (last N rows)
    if ($action === 'get_live') {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        if ($limit <= 0) $limit = 10;
        $cols = "`SN`,`ID`,`Latitude`,`Longitude`,`Date`,`Time`,`Day`,`WiFi_Strength`,`Temperature`,`Pressure`,`Humidity`,`Depth`";
        $sql = "SELECT $cols FROM `$table` ORDER BY `SN` DESC LIMIT $limit";
        $out = [];
        if ($res = $conn->query($sql)) {
            while ($r = $res->fetch_assoc()) {
                $out[] = [
                    'sn' => $r['SN'],
                    'station_id' => $r['ID'],
                    'latitude' => $r['Latitude'],
                    'longitude'=> $r['Longitude'],
                    'date' => $r['Date'],
                    'time' => $r['Time'],
                    'day' => $r['Day'],
                    'wifi_strength' => $r['WiFi_Strength'],
                    'temperature' => isset($r['Temperature']) ? (float)$r['Temperature'] : null,
                    'pressure' => isset($r['Pressure']) ? (float)$r['Pressure'] : null,
                    'humidity' => isset($r['Humidity']) ? (float)$r['Humidity'] : null,
                    'depth' => isset($r['Depth']) ? (float)$r['Depth'] : null,
                ];
            }
        }
        send_json(['success' => true, 'data' => $out]);
    }

    // get_search (by MM/DD/YYYY)
    if ($action === 'get_search') {
        $date = isset($_GET['date']) ? trim($_GET['date']) : '';
        if ($date === '') {
            send_json(['success' => false, 'message' => 'Date required']);
        }
        $date_esc = $conn->real_escape_string($date);
        $cols = "`SN`,`ID`,`Latitude`,`Longitude`,`Date`,`Time`,`Day`,`WiFi_Strength`,`Temperature`,`Pressure`,`Humidity`,`Depth`";
        $sql = "SELECT $cols FROM `$table` WHERE `Date` LIKE '%{$date_esc}%' ORDER BY `SN` DESC";
        $out = [];
        if ($res = $conn->query($sql)) {
            while ($r = $res->fetch_assoc()) {
                $out[] = [
                    'sn' => $r['SN'],
                    'station_id' => $r['ID'],
                    'latitude' => $r['Latitude'],
                    'longitude'=> $r['Longitude'],
                    'date' => $r['Date'],
                    'time' => $r['Time'],
                    'day' => $r['Day'],
                    'wifi_strength' => $r['WiFi_Strength'],
                    'temperature' => isset($r['Temperature']) ? (float)$r['Temperature'] : null,
                    'pressure' => isset($r['Pressure']) ? (float)$r['Pressure'] : null,
                    'humidity' => isset($r['Humidity']) ? (float)$r['Humidity'] : null,
                    'depth' => isset($r['Depth']) ? (float)$r['Depth'] : null,
                ];
            }
        }
        send_json(['success' => true, 'data' => $out]);
    }

    // get_history for charts
    if ($action === 'get_history') {
        $sensor = isset($_GET['sensor']) ? $_GET['sensor'] : 'temperature';
        $map = [
            'temperature' => 'Temperature',
            'pressure' => 'Pressure',
            'humidity' => 'Humidity',
            'depth' => 'Depth'
        ];
        if (!isset($map[$sensor])) send_json(['success' => false, 'message' => 'Unknown sensor']);
        $col = $map[$sensor];
        $limit = 500;
        $sql = "SELECT `Date`,`Time`,`$col` FROM `$table` WHERE `$col` IS NOT NULL ORDER BY `SN` DESC LIMIT $limit";
        $arr = [];
        if ($res = $conn->query($sql)) {
            while ($r = $res->fetch_assoc()) {
                $date = $r['Date'] ?? '';
                $time = $r['Time'] ?? '';
                $ts = trim($date . ' ' . $time);
                $val = is_numeric($r[$col]) ? (float)$r[$col] : null;
                $arr[] = ['timestamp' => $ts, 'value' => $val];
            }
        }
        $arr = array_reverse($arr);
        send_json(['success' => true, 'data' => $arr]);
    }

    send_json(['success' => false, 'message' => 'Unknown action']);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>GST E-Cube Dashboard</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{
      font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, Arial;
      background: linear-gradient(135deg,#667eea 0%,#764ba2 100%);
      min-height:100vh;padding:20px;color:#0f172a;
    }
    .container{max-width:1200px;margin:0 auto}
    .header{display:flex;justify-content:space-between;align-items:center;background:rgba(255,255,255,0.95);backdrop-filter:blur(6px);padding:18px;border-radius:12px;box-shadow:0 12px 40px rgba(0,0,0,0.12);margin-bottom:14px;}
    .header-left h1{font-size:20px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-weight:800}
    .header-sub{font-size:13px;color:#475569;margin-top:4px}
    .header-right{display:flex;gap:12px;align-items:center}
    .badge{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;font-weight:700}
    .live{display:flex;align-items:center;gap:10px;background:#f1f5f9;padding:8px 12px;border-radius:10px;font-weight:700;color:#475569}
    .live-dot{width:10px;height:10px;border-radius:50%;background:#ef4444;animation:blink 1s infinite}
    @keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
    .controls{background:rgba(255,255,255,0.95);padding:12px;border-radius:10px;margin-bottom:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    input[type=date]{padding:8px 12px;border-radius:8px;border:2px solid #e6edf5;font-weight:600}
    .btn{padding:8px 12px;border-radius:8px;border:0;cursor:pointer;font-weight:700}
    .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
    .btn-secondary{background:#64748b;color:#fff}

    /* fixed-height cards (fixed2) */
    .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:12px;height:160px}
    .card{background:rgba(255,255,255,0.98);padding:16px;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.08);position:relative;overflow:hidden;cursor:pointer;display:flex;flex-direction:column;justify-content:space-between}
    .card .title{font-size:12px;color:#64748b;font-weight:800;text-transform:uppercase}
    .card .value{font-size:28px;font-weight:900;color:#0f172a}
    .card .unit{font-size:13px;color:#64748b;margin-left:8px}
    .card .time{font-size:12px;color:#94a3b8}
    .card .footer{display:flex;justify-content:space-between;align-items:center;margin-top:8px;border-top:1px solid #f1f5f9;padding-top:8px;font-weight:700;color:#64748b}

    /* scrollable live table: fixed height + scroll */
    .data-table{background:rgba(255,255,255,0.98);padding:12px;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.08);margin-bottom:12px}
    .table-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
    .table-scroll{max-height:360px;overflow:auto;padding-right:6px}
    table{width:100%;border-collapse:collapse}
    th,td{padding:8px 10px;text-align:left;border-bottom:1px solid #f1f5f9;font-size:13px;vertical-align:middle}
    th{position:sticky;top:0;background:#f8fafc;color:#475569;font-weight:800;text-transform:uppercase}
    @media (max-width:768px){.header{flex-direction:column;align-items:flex-start}.stats-grid{grid-template-columns:1fr;height:auto}}
    .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:200;align-items:center;justify-content:center}
    .modal .box{background:#fff;border-radius:10px;padding:14px;max-width:1100px;width:96%;max-height:90vh;overflow:auto}
    .wifi-icon{width:36px;height:28px;display:inline-block}
    .wifi-bars{display:inline-flex;gap:2px;align-items:flex-end}
    .wifi-bar{width:4px;height:8px;background:#e6edf5;border-radius:2px}
    .wifi-bar.active{background:#10b981}
    .badge.offline{background:linear-gradient(135deg,#dc2626,#b91c1c) !important}
    .live-dot.offline{background:#dc2626 !important; animation: none !important;}
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="header-left">
        <h1>🌐 GST E-Cube Dashboard</h1>
        <div class="header-sub">Real-time Environmental Monitoring System</div>
      </div>
      <div class="header-right">
        <div class="live"><div class="live-dot" id="liveDot"></div><span id="liveText" style="margin-left:6px;">LIVE</span></div>

        <!-- WiFi curved icon (Option B) -->
        <div id="wifiWrapper" title="WiFi Strength" style="display:flex;align-items:center;gap:8px;margin-left:8px">
          <svg class="wifi-icon" viewBox="0 0 24 24" id="wifiSvg" aria-hidden="true">
            <!-- 3 arcs + dot; JS will change 'fill' -->
            <path id="w3" d="M2 8.5C6 5 10 4 12 4s6 1 10 4.5l-1.4 1.2C17.6 7.1 14.9 6 12 6s-5.6 1.1-8.6 3.7L2 8.5z" fill="#d1d5db"/>
            <path id="w2" d="M4.6 11.2C7 9 9 8.2 12 8.2s5 .8 7.4 3L18 12.8c-1.7-1.5-3.8-2-6-2s-4.3.6-6 2L4.6 11.2z" fill="#d1d5db"/>
            <path id="w1" d="M7.6 14.2c1.2-.9 2.7-1.2 4.4-1.2s3.2.3 4.4 1.2L15 16c-.8-.5-1.7-.7-2.6-.7s-1.8.2-2.6.7l-.2-.1z" fill="#d1d5db"/>
            <circle id="w0" cx="12" cy="19" r="1.6" fill="#d1d5db"/>
          </svg>
          <div id="wifiText" style="font-size:13px;color:#475569">-- dBm</div>
        </div>

        <div class="badge" id="connBadge">Checking...</div>
      </div>
    </div>

    <div class="controls">
      <input type="date" id="searchDate">
      <button class="btn btn-primary" onclick="doSearch()">🔍 Search</button>
      <button class="btn btn-secondary" onclick="manualRefresh()">🔄 Refresh Live</button>
      <div style="margin-left:auto;font-size:13px;color:#475569">Live table shows last <strong>10</strong> rows</div>
    </div>

    <!-- Fixed cards -->
    <div class="stats-grid" aria-hidden="false">
      <div class="card" style="--color-start:#f59e0b;--color-end:#ef4444" onclick="openChartModal('temperature','Temperature','°C')">
        <div>
          <div class="title">Temperature</div>
          <div style="display:flex;align-items:baseline;gap:8px">
            <div class="value" id="temp-value">--</div><div class="unit">°C</div>
          </div>
        </div>
        <div>
          <div class="time" id="temp-time">--</div>
          <div class="footer"><div id="temp-change">--</div><div>vs prev</div></div>
        </div>
      </div>

      <div class="card" style="--color-start:#3b82f6;--color-end:#1d4ed8" onclick="openChartModal('pressure','Pressure','hPa')">
        <div>
          <div class="title">Pressure</div>
          <div style="display:flex;align-items:baseline;gap:8px">
            <div class="value" id="pressure-value">--</div><div class="unit">hPa</div>
          </div>
        </div>
        <div>
          <div class="time" id="pressure-time">--</div>
          <div class="footer"><div id="pressure-change">--</div><div>vs prev</div></div>
        </div>
      </div>

      <div class="card" style="--color-start:#06b6d4;--color-end:#0891b2" onclick="openChartModal('humidity','Humidity','%')">
        <div>
          <div class="title">Humidity</div>
          <div style="display:flex;align-items:baseline;gap:8px">
            <div class="value" id="humidity-value">--</div><div class="unit">%</div>
          </div>
        </div>
        <div>
          <div class="time" id="humidity-time">--</div>
          <div class="footer"><div id="humidity-change">--</div><div>vs prev</div></div>
        </div>
      </div>

      <div class="card" style="--color-start:#8b5cf6;--color-end:#6d28d9" onclick="openChartModal('depth','Water Depth','cm')">
        <div>
          <div class="title">Water Depth</div>
          <div style="display:flex;align-items:baseline;gap:8px">
            <div class="value" id="depth-value">--</div><div class="unit">cm</div>
          </div>
        </div>
        <div>
          <div class="time" id="depth-time">--</div>
          <div class="footer"><div id="depth-change">--</div><div>vs prev</div></div>
        </div>
      </div>
    </div>

    <!-- Live Data Table (scrollable) -->
    <div class="data-table" aria-live="polite">
      <div class="table-header">
        <h3>📡 Live Station Data</h3>
        <div class="badge">Latest 10</div>
      </div>
      <div class="table-scroll" id="liveTableContainer">
        <div style="padding:12px;color:#64748b">Loading live data...</div>
      </div>
    </div>

    <!-- Search Results -->
    <div class="data-table" id="searchSection" style="display:none;">
      <div class="table-header">
        <h3>🔎 Search Results</h3>
        <div class="badge" id="searchBadge">Filtered</div>
      </div>
      <div id="searchTableContainer"><div style="padding:12px;color:#64748b">No search yet</div></div>
    </div>

  </div>

  <!-- Chart Modal -->
  <div id="chartModal" class="modal" onclick="if(event.target===this)closeChartModal()">
    <div class="box">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <h3 id="chartTitle">📈 Sensor History</h3>
        <button onclick="closeChartModal()" class="btn btn-secondary">Close</button>
      </div>
      <div style="margin-bottom:8px">
        <button class="btn btn-primary" onclick="loadChart('1h')">1 Hour</button>
        <button class="btn btn-secondary" onclick="loadChart('1d')">1 Day</button>
        <button class="btn btn-secondary" onclick="loadChart('1w')">1 Week</button>
        <button class="btn btn-secondary" onclick="loadChart('1m')">1 Month</button>
      </div>
      <div style="height:360px"><canvas id="sensorChart"></canvas></div>
      <div id="chartTable" style="margin-top:10px"></div>
    </div>
  </div>

<script>
/* Frontend JS */
let previous = {};
let currentSensor = null;
let currentUnit = '';
let chartInstance = null;

document.addEventListener('DOMContentLoaded', () => {
  // set today's date in picker
  const d = new Date();
  document.getElementById('searchDate').valueAsDate = d;

  // initial loads
  loadLatestAndLive();
  // auto-refresh every 8s
  setInterval(loadLatestAndLive, 8000);
});

function api(path) { return fetch(path).then(r => r.json()); }

/* Manual refresh */
function manualRefresh() {
  loadLatestAndLive();
  setTimeout(() => loadLatestAndLive(), 700);
}

/* Connection badge (online/offline) based on DB timestamp (MM/DD/YYYY + HH:MM:SS) */
function updateConnectionStatus(latestDate, latestTime) {
  const badge = document.getElementById('connBadge');
  const liveDot = document.getElementById('liveDot');
  if (!latestDate || !latestTime) {
    badge.textContent = 'Offline';
    badge.classList.add('offline');
    badge.style.background = 'linear-gradient(135deg,#dc2626,#b91c1c)';
    liveDot.classList.add('offline');
    document.getElementById('liveText').textContent = 'OFFLINE';
    return;
  }
  const ts = new Date(`${latestDate} ${latestTime}`);
  if (isNaN(ts.getTime())) {
    badge.textContent = 'Offline';
    badge.classList.add('offline');
    badge.style.background = 'linear-gradient(135deg,#dc2626,#b91c1c)';
    liveDot.classList.add('offline');
    document.getElementById('liveText').textContent = 'OFFLINE';
    return;
  }
  const diffSec = (Date.now() - ts.getTime()) / 1000;
  const threshold = 90;
  if (diffSec <= threshold) {
    badge.textContent = 'Connected';
    badge.classList.remove('offline');
    badge.style.background = '';
    liveDot.classList.remove('offline');
    liveDot.style.animation = 'blink 1s infinite';
    document.getElementById('liveText').textContent = 'LIVE';
  } else {
    badge.textContent = 'Offline';
    badge.classList.add('offline');
    badge.style.background = 'linear-gradient(135deg,#dc2626,#b91c1c)';
    liveDot.classList.add('offline');
    liveDot.style.animation = 'none';
    document.getElementById('liveText').textContent = 'OFFLINE';
  }
}

/* WiFi icon update (curved icon in header) based on RSSI thresholds:
   A: -30 Excellent, -50 Very Good, -60 Good, -70 Weak, -80 Very Weak
*/
function updateWifiHeader(rssi) {
  const w3 = document.getElementById('w3');
  const w2 = document.getElementById('w2');
  const w1 = document.getElementById('w1');
  const w0 = document.getElementById('w0');
  const wifiText = document.getElementById('wifiText');
  if (rssi === null || rssi === undefined || isNaN(rssi)) {
    [w3,w2,w1,w0].forEach(el => el.setAttribute('fill', '#d1d5db'));
    wifiText.textContent = '-- dBm';
    return;
  }
  const n = parseInt(rssi,10);
  wifiText.textContent = `${n} dBm`;

  // thresholds: Excellent >= -50, Very Good -59..-51, Good -69..-60, Weak -79..-70, Very Weak < -80
  if (n >= -50) {
    // all green
    w3.setAttribute('fill','#10b981');
    w2.setAttribute('fill','#10b981');
    w1.setAttribute('fill','#10b981');
    w0.setAttribute('fill','#10b981');
  } else if (n >= -60) {
    w3.setAttribute('fill','#6ee7b7');
    w2.setAttribute('fill','#10b981');
    w1.setAttribute('fill','#10b981');
    w0.setAttribute('fill','#10b981');
  } else if (n >= -70) {
    w3.setAttribute('fill','#d1fae5');
    w2.setAttribute('fill','#6ee7b7');
    w1.setAttribute('fill','#10b981');
    w0.setAttribute('fill','#10b981');
  } else if (n >= -80) {
    w3.setAttribute('fill','#fef3c7');
    w2.setAttribute('fill','#fca5a5');
    w1.setAttribute('fill','#fb923c');
    w0.setAttribute('fill','#fb923c');
  } else {
    // very weak
    [w3,w2,w1,w0].forEach(el => el.setAttribute('fill', '#d1d5db'));
  }
}

/* tiny helper: produce per-row small bars element HTML based on RSSI */
function wifiBarsHtml(rssi){
  // 4-step bars
  if (rssi === null || rssi === undefined || isNaN(rssi)) {
    return '<div class="wifi-bars"><div class="wifi-bar"></div><div class="wifi-bar"></div><div class="wifi-bar"></div><div class="wifi-bar"></div></div>';
  }
  const n = parseInt(rssi,10);
  let level = 0;
  if (n >= -50) level = 4;
  else if (n >= -60) level = 3;
  else if (n >= -70) level = 2;
  else if (n >= -80) level = 1;
  else level = 0;
  let html = '<div class="wifi-bars" aria-hidden="true">';
  for(let i=1;i<=4;i++){
    html += `<div class="wifi-bar ${i<=level ? 'active' : ''}" style="height:${6 + i*5}px"></div>`;
  }
  html += '</div>';
  return html;
}

/* ========== Load latest cards + live table ========== */
function loadLatestAndLive(){
  // get latest for cards + wifi + connection
  api('?action=get_latest').then(resp=>{
    if(resp.success && resp.data){
      const d = resp.data;
      updateConnectionStatus(d.date, d.time);
      updateCard('temp', d.temperature, `${d.date} ${d.time}`);
      updateCard('pressure', d.pressure, `${d.date} ${d.time}`);
      updateCard('humidity', d.humidity, `${d.date} ${d.time}`);
      updateCard('depth', d.depth, `${d.date} ${d.time}`);
      updateWifiHeader(d.wifi_strength);
    } else {
      updateConnectionStatus(null, null);
      updateCard('temp', null, null);
      updateCard('pressure', null, null);
      updateCard('humidity', null, null);
      updateCard('depth', null, null);
      updateWifiHeader(null);
    }
  }).catch(e=>{
    console.error('get_latest failed', e);
    updateConnectionStatus(null, null);
    updateWifiHeader(null);
  });

  // load live table (last 10 rows)
  api('?action=get_live&limit=10').then(resp=>{
    if(resp.success){
      renderLiveTable(resp.data || []);
    } else {
      document.getElementById('liveTableContainer').innerHTML = '<div style="padding:12px;color:#64748b">No live data</div>';
    }
  }).catch(e=>{
    console.error('get_live failed', e);
    document.getElementById('liveTableContainer').innerHTML = '<div style="padding:12px;color:#64748b">Error loading live</div>';
  });
}

function updateCard(k, val, timeStr){
  const idVal = document.getElementById(`${k}-value`);
  const idTime = document.getElementById(`${k}-time`);
  const idChange = document.getElementById(`${k}-change`);
  if (val === null || val === undefined || isNaN(val)) {
    idVal.textContent = '--';
    idTime.textContent = '--';
    return;
  }
  idVal.textContent = parseFloat(val).toFixed( (k==='pressure')?1:1 );
  idTime.textContent = timeAgoFromString(timeStr);
  if(previous[k] !== undefined){
    const diff = parseFloat(val) - parseFloat(previous[k]);
    const arrow = diff >= 0 ? '↑' : '↓';
    const unit = k==='temp' ? '°C' : k==='pressure' ? 'hPa' : k==='humidity' ? '%' : 'cm';
    idChange.textContent = `${arrow} ${Math.abs(diff).toFixed(1)} ${unit}`;
    idChange.style.color = diff>=0 ? '#10b981' : '#ef4444';
  }
  previous[k] = parseFloat(val);
}

function timeAgoFromString(ts){
  if(!ts) return '--';
  const parsed = new Date(ts);
  if(isNaN(parsed)) return ts;
  const s = Math.floor((Date.now() - parsed.getTime())/1000);
  if(s < 10) return 'Just now';
  if(s < 60) return s + ' sec ago';
  if(s < 3600) return Math.floor(s/60) + ' min ago';
  if(s < 86400) return Math.floor(s/3600) + ' hr ago';
  return Math.floor(s/86400) + ' days ago';
}

/* ========== Render live table (scrollable) ========== */
function renderLiveTable(rows){
  const container = document.getElementById('liveTableContainer');
  if(!rows || rows.length === 0){
    container.innerHTML = '<div style="padding:12px;color:#64748b">No live data</div>';
    return;
  }
  let html = '<table><thead><tr>';
  html += '<th>Station ID</th><th>Latitude</th><th>Longitude</th><th>Date</th><th>Time</th><th>Day</th><th>WiFi</th><th>Temp</th><th>Pressure</th><th>Humidity</th><th>Depth</th>';
  html += '</tr></thead><tbody>';
  rows.forEach(r => {
    html += '<tr>';
    html += `<td>${escapeHtml(r.station_id)}</td>`;
    html += `<td>${escapeHtml(r.latitude)}</td>`;
    html += `<td>${escapeHtml(r.longitude)}</td>`;
    html += `<td>${escapeHtml(r.date)}</td>`;
    html += `<td>${escapeHtml(r.time)}</td>`;
    html += `<td>${escapeHtml(r.day)}</td>`;
    html += `<td style="white-space:nowrap">${wifiBarsHtml(r.wifi_strength)} <span style="margin-left:6px;color:#475569;font-size:12px">${r.wifi_strength!==null?escapeHtml(r.wifi_strength):'--'} dBm</span></td>`;
    html += `<td>${r.temperature!==null?parseFloat(r.temperature).toFixed(2):'--'}</td>`;
    html += `<td>${r.pressure!==null?parseFloat(r.pressure).toFixed(2):'--'}</td>`;
    html += `<td>${r.humidity!==null?parseFloat(r.humidity).toFixed(2):'--'}</td>`;
    html += `<td>${r.depth!==null?parseFloat(r.depth).toFixed(2):'--'}</td>`;
    html += '</tr>';
  });
  html += '</tbody></table>';
  container.innerHTML = html;
}

/* ========== Search ========== */
function convertYMDtoMDY(ymd){
  if(!ymd) return '';
  const parts = ymd.split('-');
  if(parts.length !== 3) return ymd;
  return `${parts[1]}/${parts[2]}/${parts[0]}`; // MM/DD/YYYY
}

function doSearch(){
  const dateVal = document.getElementById('searchDate').value;
  if(!dateVal){ alert('Please pick a date'); return; }
  const md = convertYMDtoMDY(dateVal);
  api(`?action=get_search&date=${encodeURIComponent(md)}`).then(resp=>{
    if(resp.success){
      renderSearchTable(resp.data || [], md);
    } else {
      document.getElementById('searchSection').style.display = 'block';
      document.getElementById('searchTableContainer').innerHTML = '<div style="padding:12px;color:#64748b">No results</div>';
    }
  }).catch(e=>{
    console.error('search failed', e);
    document.getElementById('searchSection').style.display = 'block';
    document.getElementById('searchTableContainer').innerHTML = '<div style="padding:12px;color:#64748b">Search error</div>';
  });
}

function renderSearchTable(rows, dateDisplay){
  document.getElementById('searchSection').style.display = 'block';
  document.getElementById('searchBadge').textContent = `Results for ${dateDisplay}`;
  const container = document.getElementById('searchTableContainer');
  if(!rows || rows.length === 0){
    container.innerHTML = '<div style="padding:12px;color:#64748b">No data for selected date</div>';
    return;
  }
  let html = '<table><thead><tr>';
  html += '<th>Station ID</th><th>Latitude</th><th>Longitude</th><th>Date</th><th>Time</th><th>Day</th><th>WiFi</th><th>Temp</th><th>Pressure</th><th>Humidity</th><th>Depth</th>';
  html += '</tr></thead><tbody>';
  rows.forEach(r => {
    html += '<tr>';
    html += `<td>${escapeHtml(r.station_id)}</td>`;
    html += `<td>${escapeHtml(r.latitude)}</td>`;
    html += `<td>${escapeHtml(r.longitude)}</td>`;
    html += `<td>${escapeHtml(r.date)}</td>`;
    html += `<td>${escapeHtml(r.time)}</td>`;
    html += `<td>${escapeHtml(r.day)}</td>`;
    html += `<td style="white-space:nowrap">${wifiBarsHtml(r.wifi_strength)} <span style="margin-left:6px;color:#475569;font-size:12px">${r.wifi_strength!==null?escapeHtml(r.wifi_strength):'--'} dBm</span></td>`;
    html += `<td>${r.temperature!==null?parseFloat(r.temperature).toFixed(2):'--'}</td>`;
    html += `<td>${r.pressure!==null?parseFloat(r.pressure).toFixed(2):'--'}</td>`;
    html += `<td>${r.humidity!==null?parseFloat(r.humidity).toFixed(2):'--'}</td>`;
    html += `<td>${r.depth!==null?parseFloat(r.depth).toFixed(2):'--'}</td>`;
    html += '</tr>';
  });
  html += '</tbody></table>';
  container.innerHTML = html;
}

/* Chart modal (unchanged) */
function openChartModal(sensorKey, title, unit){
  currentSensor = sensorKey;
  currentUnit = unit || '';
  document.getElementById('chartTitle').textContent = `📈 ${title} History`;
  document.getElementById('chartModal').style.display = 'flex';
  loadChart();
}
function closeChartModal(){
  document.getElementById('chartModal').style.display = 'none';
  if(chartInstance){ chartInstance.destroy(); chartInstance = null; }
}
function loadChart(){
  if(!currentSensor) return;
  api(`?action=get_history&sensor=${encodeURIComponent(currentSensor)}`).then(resp=>{
    if(resp.success){
      renderChart(resp.data || []);
      renderChartTable(resp.data || []);
    }
  }).catch(e=>console.error('loadChart', e));
}
function renderChart(data){
  const ctx = document.getElementById('sensorChart').getContext('2d');
  if(chartInstance) chartInstance.destroy();
  const labels = data.map(r => r.timestamp);
  const values = data.map(r => r.value === null ? null : parseFloat(r.value));
  chartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{ label: currentUnit, data: values, borderColor: '#667eea', backgroundColor: 'rgba(102,126,234,0.12)', fill:true, tension:0.35, pointRadius:0, pointHoverRadius:6 }]
    },
    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{ x:{grid:{display:false}}, y:{grid:{color:'#f1f5f9'}} } }
  });
}
function renderChartTable(data){
  if(!data || data.length===0){ document.getElementById('chartTable').innerHTML = '<div style="padding:12px;color:#64748b">No history</div>'; return; }
  let html = '<table style="width:100%;border-collapse:collapse"><thead><tr><th>Timestamp</th><th>Value</th></tr></thead><tbody>';
  data.forEach(r=>{
    html += `<tr><td style="padding:8px 6px;border-bottom:1px solid #f1f5f9">${escapeHtml(r.timestamp)}</td><td style="padding:8px 6px;border-bottom:1px solid #f1f5f9">${r.value!==null?parseFloat(r.value).toFixed(2)+' '+escapeHtml(currentUnit):'--'}</td></tr>`;
  });
  html += '</tbody></table>';
  document.getElementById('chartTable').innerHTML = html;
}

/* helper */
function escapeHtml(s){ if(s===null||s===undefined) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
</body>
</html>
