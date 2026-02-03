<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'Bad request';
    exit;
}

// Load invoice and items
$stmt = $conn->prepare('SELECT * FROM invoices WHERE id = ?');
$stmt->execute([$id]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$inv) { http_response_code(404); echo 'Invoice not found'; exit; }

$it = $conn->prepare('SELECT * FROM invoice_items WHERE invoice_id = ?');
$it->execute([$id]);
$items = $it->fetchAll(PDO::FETCH_ASSOC);

// Load settings (seller info)
$settings = $conn->query('SELECT * FROM invoice_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];

$number = $inv['invoice_number'];
$filename = 'faktura_' . preg_replace('/[^A-Za-z0-9_-]+/','-', $number) . '.html';

// Build HTML with KJD styling
$css = "
body{
    font-family:'SF Pro Display','SF Compact Text',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
    background:#f8f9fa;
    color:#102820;
    margin:0;
    padding:20px;
}
.wrap{
    max-width:900px;
    margin:0 auto;
    background:#fff;
    border-radius:16px;
    box-shadow:0 8px 32px rgba(16,40,32,0.1);
    overflow:hidden;
}
.head{
    background:linear-gradient(135deg,#102820,#4c6444);
    color:#fff;
    padding:30px 28px;
    border-bottom:3px solid #CABA9C;
}
.head-content{
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.logo{
    font-weight:800;
    font-size:28px;
}
.logo span{
    color:#CABA9C;
}
.meta{
    color:#fff;
    font-size:14px;
    text-align:right;
    line-height:1.6;
}
.content{
    padding:30px 28px;
}
h1{
    font-size:22px;
    margin:0 0 16px;
    color:#102820;
    font-weight:700;
    border-bottom:2px solid #CABA9C;
    padding-bottom:8px;
}
table{
    width:100%;
    border-collapse:collapse;
    margin:20px 0;
}
th{
    background:#4c6444;
    color:#fff;
    padding:12px;
    text-align:left;
    font-weight:700;
    font-size:14px;
    border:1px solid #4c6444;
}
th.num{
    text-align:right;
}
td{
    padding:12px;
    border-bottom:1px solid #e0e0e0;
    color:#102820;
    font-size:14px;
}
td.num{
    text-align:right;
    font-weight:600;
}
tr:nth-child(even){
    background:rgba(202,186,156,0.05);
}
.grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
    margin-top:25px;
}
.card{
    background:#fff;
    border:2px solid #8A6240;
    border-radius:12px;
    padding:20px;
    box-shadow:0 4px 15px rgba(138,98,64,0.1);
}
.sumline{
    display:flex;
    justify-content:space-between;
    margin:8px 0;
    padding:8px 0;
    border-bottom:1px solid rgba(202,186,156,0.3);
}
.sumline:last-child{
    border-bottom:none;
}
.total{
    font-size:20px;
    font-weight:800;
    color:#102820;
    background:linear-gradient(135deg,rgba(202,186,156,0.2),rgba(202,186,156,0.1));
    padding:12px;
    border-radius:8px;
    margin-top:10px;
}
.foot{
    background:linear-gradient(135deg,#4D2D18,#8A6240);
    color:#fff;
    padding:20px 28px;
    text-align:center;
    font-size:13px;
}
@media (prefers-color-scheme: dark){
    body{background:#1a1a1a;}
    .wrap{background:#2d2d2d;box-shadow:0 8px 32px rgba(0,0,0,0.5);}
    .content{color:#e0e0e0;}
    h1{color:#e0e0e0;border-bottom-color:#8A6240;}
    td{color:#e0e0e0;border-bottom-color:#4c6444;}
    .card{background:#2d2d2d;border-color:#8A6240;}
    .sumline{border-bottom-color:#4c6444;}
    .total{color:#e0e0e0;background:rgba(138,98,64,0.2);}
}
";

$buyerBlock = function($inv){
    $out = '';
    $out .= '<div>'.h($inv['buyer_name']).'</div>';
    if (!empty($inv['buyer_address1'])) $out .= '<div>'.h($inv['buyer_address1']).'</div>';
    if (!empty($inv['buyer_address2'])) $out .= '<div>'.h($inv['buyer_address2']).'</div>';
    $line = trim(($inv['buyer_zip']??'').' '.($inv['buyer_city']??''));
    if ($line !== '') $out .= '<div>'.h($line).'</div>';
    if (!empty($inv['buyer_country'])) $out .= '<div>'.h($inv['buyer_country']).'</div>';
    if (!empty($inv['buyer_ico'])) $out .= '<div>IČO: '.h($inv['buyer_ico']).'</div>';
    if (!empty($inv['buyer_dic'])) $out .= '<div>DIČ: '.h($inv['buyer_dic']).'</div>';
    if (!empty($inv['buyer_email'])) $out .= '<div>✉️ '.h($inv['buyer_email']).'</div>';
    if (!empty($inv['buyer_phone'])) $out .= '<div>📞 '.h($inv['buyer_phone']).'</div>';
    return $out;
};

$sellerBlock = function($s){
    $out = '';
    $out .= '<div>'.h($s['seller_name'] ?? 'KJD').'</div>';
    if (!empty($s['seller_address1'])) $out .= '<div>'.h($s['seller_address1']).'</div>';
    if (!empty($s['seller_address2'])) $out .= '<div>'.h($s['seller_address2']).'</div>';
    $line = trim(($s['seller_zip']??'').' '.($s['seller_city']??''));
    if ($line !== '') $out .= '<div>'.h($line).'</div>';
    if (!empty($s['seller_country'])) $out .= '<div>'.h($s['seller_country']).'</div>';
    $out .= '<div>IČO: 23982381</div>';
    if (!empty($s['seller_dic'])) $out .= '<div>DIČ: '.h($s['seller_dic']).'</div>';
    if (!empty($s['bank_account'])) $out .= '<div>Bankovní účet: '.h($s['bank_account']).'</div>';
    if (!empty($s['bank_name'])) $out .= '<div>Bankovní instituce: '.h($s['bank_name']).'</div>';
    return $out;
};

$rows = '';
foreach ($items as $it) {
    $rows .= '<tr>'
        .'<td>'.h($it['name']).'</td>'
        .'<td class="num">'.(int)$it['quantity'].'</td>'
        .'<td class="num">'.number_format((float)$it['unit_price_without_vat'], 2, ',', ' ').' Kč</td>'
        .'<td class="num">'.number_format((float)$it['total_with_vat'], 2, ',', ' ').' Kč</td>'
        .'</tr>';
}
// Add shipping as a line item (fixed 90 Kč)
$rows .= '<tr>'
    .'<td>Doprava</td>'
    .'<td class="num">1</td>'
    .'<td class="num">'.number_format(90.0, 2, ',', ' ').' Kč</td>'
    .'<td class="num">'.number_format(90.0, 2, ',', ' ').' Kč</td>'
    .'</tr>';

$html = "<!DOCTYPE html><html lang='cs'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1'><title>Faktura ".h($number)."</title><style>{$css}</style></head><body>".
    "<div class='wrap'>".
      "<div class='head'>".
        "<div class='head-content'>".
          "<div class='logo'>KJ<span>D</span></div>".
          "<div class='meta'>Faktura ".h($number)."<br>Vystaveno: ".h($inv['issue_date'])."<br>Splatnost: ".h($inv['due_date'])."</div>".
        "</div>".
      "</div>".
      "<div class='content'>".
        "<div class='grid'>".
          "<div class='card'><h1>Dodavatel</h1>".$sellerBlock($settings)."</div>".
          "<div class='card'><h1>Odběratel</h1>".$buyerBlock($inv)."</div>".
        "</div>".
        "<h1 style='margin-top:22px'>Položky</h1>".
        "<div class='card' style='padding:0'><table><thead><tr><th>Název</th><th class='num'>Množství</th><th class='num'>Jedn. cena</th><th class='num'>Celkem</th></tr></thead><tbody>".$rows."</tbody></table></div>".
        "<div class='grid'>".
          "<div class='card'>Číslo objednávky: ".h($inv['order_id'] ?? '')."</div>".
          "<div class='card'>".
            "<div class='sumline'><span>Celkem bez DPH</span><strong>".number_format((float)$inv['total_without_vat'], 2, ',', ' ')." Kč</strong></div>".
            "<div class='sumline'><span>DPH</span><strong>".number_format((float)$inv['vat_total'], 2, ',', ' ')." Kč</strong></div>".
            "<div class='sumline total'><span>Celkem k úhradě</span><span>".number_format((float)$inv['total_with_vat'], 2, ',', ' ')." Kč</span></div>".
            "<div style='margin-top:8px;color:#666;font-size:13px'>Dodavatel není plátcem DPH.</div>".
          "</div>".
        "</div>".
      "</div>".
      "<div class='foot'>KubaJa Designs — automaticky generovaná faktura</div>".
    "</div>".
  "</body></html>";

header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
echo $html;
