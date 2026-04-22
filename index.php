<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>CashFlow Control</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="CashFlow Control helps you record income, expenses, monitor balances, and analyze cashflow with a modern dashboard.">
  <link href="assets/img/logocv.jpg" rel="icon">
  <link href="assets/img/logocv.jpg" rel="apple-touch-icon">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="lib/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="lib/font-awesome/css/font-awesome.min.css" rel="stylesheet">
  <style>
    :root{--bg:#f4f9f7;--surface:#fff;--surface-2:#f8fbff;--text:#0f172a;--muted:#617082;--line:rgba(15,23,42,.08);--green:#0f9f74;--green-deep:#0c7b5b;--blue:#1d77ff;--shadow-lg:0 24px 60px rgba(15,23,42,.12);--shadow-md:0 16px 36px rgba(15,23,42,.08);--shadow-sm:0 10px 24px rgba(15,23,42,.06)}
    html{scroll-behavior:smooth}
    body{margin:0;font-family:'Manrope',sans-serif;color:var(--text);background:radial-gradient(circle at top left,rgba(15,159,116,.08),transparent 28%),radial-gradient(circle at top right,rgba(29,119,255,.10),transparent 32%),linear-gradient(180deg,#fbfffd 0%,#f4f8fc 100%)}
    .w-100{width:100%}.pt-0{padding-top:0!important}.mt-2{margin-top:.5rem!important}.mt-3{margin-top:1rem!important}.mt-4{margin-top:1.5rem!important}.mt-5{margin-top:3rem!important}.mb-0{margin-bottom:0!important}.border-0{border:0!important}.text-muted{color:var(--muted)!important}.font-weight-bold{font-weight:700!important}.mx-auto{margin-left:auto!important;margin-right:auto!important}.d-inline-flex{display:inline-flex!important}.align-items-center{align-items:center!important}.justify-content-center{justify-content:center!important}
    .form-row{display:flex;flex-wrap:wrap;margin-left:-10px;margin-right:-10px}.form-row>.form-group{padding-left:10px;padding-right:10px}
    .section{padding:88px 0}
    .eyebrow{display:inline-flex;align-items:center;gap:10px;padding:10px 16px;border:1px solid rgba(15,159,116,.16);border-radius:999px;background:rgba(255,255,255,.85);font-size:13px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:var(--green-deep);margin-bottom:18px;box-shadow:var(--shadow-sm)}
    .eyebrow:before{content:"";width:8px;height:8px;border-radius:50%;background:linear-gradient(135deg,var(--green),#43d9a5)}
    .display-title{font-size:clamp(2.6rem,5vw,4.8rem);line-height:1.02;letter-spacing:-.05em;font-weight:800;margin:0}
    .section-title{font-size:clamp(2rem,3.2vw,3.3rem);line-height:1.06;letter-spacing:-.04em;font-weight:800;margin:0}
    .lead-copy,.muted-copy{color:var(--muted);line-height:1.8}
    .lead-copy{font-size:1.08rem;max-width:560px;margin-top:18px}
    .muted-copy{font-size:1rem;margin-top:16px}
    .site-navbar{position:sticky;top:0;z-index:1200;padding:16px 0;background:rgba(248,251,255,.84);backdrop-filter:blur(18px)}
    .nav-shell{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 18px;border:1px solid rgba(15,23,42,.06);border-radius:22px;background:rgba(255,255,255,.88);box-shadow:var(--shadow-sm)}
    .brand{display:inline-flex;align-items:center;gap:12px;font-weight:800;color:var(--text);text-decoration:none}
    .brand-mark{width:42px;height:42px;border-radius:14px;display:inline-flex;align-items:center;justify-content:center;color:#fff;background:linear-gradient(135deg,var(--green),var(--blue));box-shadow:0 14px 28px rgba(29,119,255,.18)}
    .site-navbar .nav-link{padding:10px 16px;border-radius:14px;color:#445063;font-weight:700}
    .site-navbar .nav-link:hover{background:rgba(29,119,255,.08);color:var(--text)}
    .nav-actions{display:flex;gap:12px;flex-wrap:wrap}
    .btn-soft,.btn-solid,.btn-glass{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:50px;padding:0 22px;border-radius:16px;font-weight:700;text-decoration:none;border:1px solid transparent;transition:.25s ease}
    .btn-soft{background:rgba(29,119,255,.08);border-color:rgba(29,119,255,.08);color:var(--blue)}
    .btn-soft:hover{text-decoration:none;color:var(--blue);background:rgba(29,119,255,.14);transform:translateY(-1px)}
    .btn-solid{background:linear-gradient(135deg,var(--green),var(--blue));color:#fff;box-shadow:0 18px 34px rgba(29,119,255,.22)}
    .btn-solid:hover{text-decoration:none;color:#fff;transform:translateY(-2px)}
    .btn-glass{background:rgba(255,255,255,.84);border-color:var(--line);color:var(--text)}
    .btn-glass:hover{text-decoration:none;color:var(--text);background:#fff}
    .hero{padding:44px 0 86px;position:relative;overflow:hidden}
    .hero-copy{padding-top:28px}
    .hero-actions,.hero-proof{display:flex;gap:14px;flex-wrap:wrap}
    .hero-actions{margin-top:32px}
    .hero-proof{margin-top:26px}
    .proof-chip{padding:12px 14px;border-radius:16px;background:rgba(255,255,255,.84);border:1px solid var(--line);box-shadow:var(--shadow-sm);font-weight:600;color:var(--muted)}
    .proof-chip i{color:var(--green)}
    .hero-visual{margin-top:44px;position:relative}
    .dashboard{padding:24px;border-radius:30px;background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(245,249,255,.98));border:1px solid var(--line);box-shadow:var(--shadow-lg)}
    .dashboard-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:22px}
    .dashboard-title{font-size:1rem;font-weight:800}
    .dashboard-subtitle{font-size:.92rem;color:var(--muted)}
    .badge-live{padding:10px 14px;border-radius:999px;background:rgba(15,159,116,.1);color:var(--green-deep);font-weight:800;font-size:.84rem}
    .mock-grid{display:grid;grid-template-columns:repeat(12,1fr);gap:18px}
    .mock-card{padding:20px;border-radius:24px;background:rgba(255,255,255,.92);border:1px solid var(--line);box-shadow:var(--shadow-sm)}
    .mock-card small,.mock-card p{color:var(--muted)}
    .balance-card{grid-column:span 7;background:linear-gradient(145deg,#0f172a,#193759);color:#fff}
    .balance-card small,.balance-card p{color:rgba(255,255,255,.74)}
    .balance-amount{font-size:clamp(1.9rem,2.5vw,2.7rem);font-weight:800;letter-spacing:-.04em;margin-top:12px}
    .metric-pills{display:flex;gap:12px;flex-wrap:wrap;margin-top:20px}
    .metric-pill{flex:1 1 150px;padding:14px 16px;border-radius:18px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.08)}
    .metric-pill strong{display:block;color:#fff}
    .insight-card{grid-column:span 5}
    .bars{display:flex;align-items:flex-end;gap:10px;height:160px;margin-top:18px}
    .bars span{flex:1;border-radius:16px 16px 8px 8px;background:linear-gradient(180deg,rgba(29,119,255,.22),rgba(15,159,116,.82))}
    .bars span:nth-child(1){height:40%}.bars span:nth-child(2){height:68%}.bars span:nth-child(3){height:56%}.bars span:nth-child(4){height:82%}.bars span:nth-child(5){height:66%}.bars span:nth-child(6){height:92%}
    .transaction-card{grid-column:span 7}
    .transactions{display:grid;gap:14px;margin-top:18px}
    .transaction{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-radius:18px;background:rgba(248,251,255,.92);border:1px solid var(--line)}
    .transaction-main{display:flex;align-items:center;gap:12px}
    .transaction-icon{width:44px;height:44px;border-radius:16px;display:inline-flex;align-items:center;justify-content:center;background:rgba(29,119,255,.1);color:var(--blue)}
    .transaction strong{display:block;font-size:.95rem}
    .transaction small{color:var(--muted)}
    .income{color:var(--green-deep);font-weight:800}.expense{color:#e24e5c;font-weight:800}
    .mini-card{grid-column:span 5;background:linear-gradient(180deg,rgba(29,119,255,.08),rgba(15,159,116,.08))}
    .progress-shell{margin-top:16px;height:12px;border-radius:999px;background:rgba(15,23,42,.08);overflow:hidden}
    .progress-shell span{display:block;width:76%;height:100%;background:linear-gradient(90deg,var(--blue),var(--green))}
    .floating-note{position:absolute;right:-12px;bottom:36px;padding:16px 18px;border-radius:20px;background:rgba(255,255,255,.95);border:1px solid var(--line);box-shadow:var(--shadow-md);min-width:220px}
    .floating-note strong{display:block;margin-bottom:6px}
    .stats,.features,.benefits{display:grid;gap:20px}
    .stats{grid-template-columns:repeat(4,1fr)}
    .features{grid-template-columns:repeat(3,1fr)}
    .benefits{grid-template-columns:repeat(2,1fr)}
    .stat-card,.feature-card,.benefit-card,.preview-panel,.contact-card,.contact-form{padding:28px;border-radius:28px;background:rgba(255,255,255,.92);border:1px solid var(--line);box-shadow:var(--shadow-sm);transition:.25s ease}
    .stat-card:hover,.feature-card:hover,.benefit-card:hover,.preview-panel:hover,.contact-card:hover,.contact-form:hover{transform:translateY(-4px);box-shadow:var(--shadow-md)}
    .stat-number{font-size:clamp(1.8rem,2.5vw,2.35rem);font-weight:800;letter-spacing:-.04em}
    .feature-icon,.benefit-icon{width:56px;height:56px;border-radius:20px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:18px;font-size:20px}
    .feature-icon{background:linear-gradient(135deg,rgba(15,159,116,.14),rgba(29,119,255,.16));color:var(--blue)}
    .benefit-icon{background:rgba(15,159,116,.12);color:var(--green-deep)}
    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:28px;align-items:start}
    .preview-top{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:24px}
    .preview-tabs{display:flex;gap:10px;flex-wrap:wrap}
    .preview-tab{padding:10px 14px;border-radius:14px;background:rgba(15,23,42,.05);font-size:.92rem;font-weight:700;color:var(--muted)}
    .preview-tab.active{background:rgba(15,159,116,.12);color:var(--green-deep)}
    .preview-surface{padding:24px;border-radius:26px;background:#f9fbfd;border:1px solid var(--line)}
    .preview-metrics{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:18px}
    .preview-metric{padding:18px;border-radius:22px;background:#fff;border:1px solid var(--line)}
    .preview-metric span{display:block;font-size:.88rem;color:var(--muted);margin-bottom:6px}
    .chart-shell{display:grid;grid-template-columns:1.35fr .95fr;gap:18px}
    .chart-card,.summary-card{padding:20px;border-radius:24px;background:#fff;border:1px solid var(--line)}
    .line-chart{position:relative;height:220px;margin-top:18px;border-radius:20px;background:linear-gradient(180deg,rgba(29,119,255,.05),transparent),repeating-linear-gradient(to top,rgba(15,23,42,.05) 0,rgba(15,23,42,.05) 1px,transparent 1px,transparent 54px);overflow:hidden}
    .line-chart svg{position:absolute;inset:0;width:100%;height:100%}
    .summary-list{display:grid;gap:14px;margin-top:18px}
    .summary-item{display:flex;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--line)}
    .summary-item:last-child{border-bottom:0;padding-bottom:0}
    .contact-shell{display:grid;grid-template-columns:.95fr 1.05fr;gap:28px}
    .contact-list{display:grid;gap:16px;margin-top:24px}
    .contact-item{display:flex;gap:14px;align-items:flex-start;padding:16px;border-radius:20px;background:rgba(248,251,255,.9);border:1px solid var(--line)}
    .contact-item i{width:44px;height:44px;border-radius:16px;display:inline-flex;align-items:center;justify-content:center;background:rgba(29,119,255,.1);color:var(--blue)}
    .contact-form .form-control{height:54px;border-radius:16px;border:1px solid var(--line);background:rgba(248,251,255,.72);box-shadow:none;padding:0 18px}
    .contact-form textarea.form-control{min-height:150px;padding-top:14px;resize:vertical}
    .contact-form .form-control:focus{border-color:rgba(29,119,255,.34);background:#fff;box-shadow:0 0 0 4px rgba(29,119,255,.08)}
    .footer{padding:24px 0 42px}
    .footer-shell{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;padding:22px 24px;border-radius:24px;background:rgba(255,255,255,.88);border:1px solid var(--line);box-shadow:var(--shadow-sm)}
    .footer-nav{display:flex;gap:16px;flex-wrap:wrap}
    .footer-nav a{font-weight:700;color:var(--muted);text-decoration:none}
    .footer-nav a:hover{color:var(--blue)}
    .fade{opacity:0;transform:translateY(18px);animation:rise .75s ease forwards}.d1{animation-delay:.08s}.d2{animation-delay:.16s}.d3{animation-delay:.24s}.d4{animation-delay:.32s}
    @keyframes rise{to{opacity:1;transform:translateY(0)}}
    @media (max-width:1199.98px){.stats,.features,.benefits{grid-template-columns:repeat(2,1fr)}.two-col,.contact-shell,.chart-shell{grid-template-columns:1fr}}
    @media (max-width:991.98px){.section{padding:72px 0}.hero{padding:28px 0 78px}.hero-copy{padding-top:0}.mock-grid,.preview-metrics{grid-template-columns:1fr}.balance-card,.insight-card,.transaction-card,.mini-card{grid-column:span 12}.floating-note{position:relative;right:auto;bottom:auto;margin-top:18px}}
    @media (max-width:767.98px){.nav-shell{padding:12px 14px}.nav-actions{margin-top:12px}.nav-actions a,.hero-actions a{width:100%}.stats,.features,.benefits{grid-template-columns:1fr}.dashboard,.preview-panel,.contact-card,.contact-form{padding:22px;border-radius:24px}.stat-card,.feature-card,.benefit-card{padding:22px}.proof-chip{width:100%}}
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg site-navbar">
    <div class="container">
      <div class="nav-shell w-100">
        <a class="brand" href="#home">
          <span class="brand-mark"><i class="fa fa-line-chart"></i></span>
          <span>CashFlow Control</span>
        </a>
        <button class="navbar-toggler border-0 p-0" type="button" data-toggle="collapse" data-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
          <span class="d-inline-flex align-items-center justify-content-center" style="width:44px;height:44px;border-radius:14px;background:rgba(29,119,255,.08);color:#1d77ff;"><i class="fa fa-bars"></i></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
          <ul class="navbar-nav mx-auto">
            <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
            <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
            <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
            <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
          </ul>
          <div class="nav-actions">
            <a href="login.php" class="btn-soft">Login</a>
            <a href="register.php" class="btn-solid">Start Free <i class="fa fa-arrow-right"></i></a>
          </div>
        </div>
      </div>
    </div>
  </nav>

  <main>
    <section class="hero" id="home">
      <div class="container">
        <div class="row align-items-center">
          <div class="col-lg-6">
            <div class="hero-copy">
              <span class="eyebrow">Modern Finance Workspace</span>
              <h1 class="display-title">Cashflow yang rapi membuat keputusan finansial terasa jauh lebih pasti.</h1>
              <p class="lead-copy">CashFlow Control membantu kamu mencatat pemasukan, pengeluaran, memantau saldo, melihat laporan, mengelola kategori transaksi, dan membaca analisis cashflow dalam satu pengalaman yang terasa premium, bersih, dan profesional.</p>
              <div class="hero-actions">
                <a href="register.php" class="btn-solid">Coba Sekarang <i class="fa fa-long-arrow-right"></i></a>
                <a href="#preview" class="btn-glass">Lihat Dashboard</a>
              </div>
              <div class="hero-proof">
                <div class="proof-chip"><i class="fa fa-check-circle"></i> Pemasukan dan pengeluaran tercatat lebih cepat</div>
                <div class="proof-chip"><i class="fa fa-line-chart"></i> Analisis cashflow lebih mudah dipahami</div>
                <div class="proof-chip"><i class="fa fa-shield"></i> UI bersih untuk first impression yang meyakinkan</div>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="hero-visual fade d1">
              <div class="dashboard">
                <div class="dashboard-head">
                  <div>
                    <div class="dashboard-title">Realtime Cashflow Overview</div>
                    <div class="dashboard-subtitle">Semua transaksi dan insight penting dalam satu panel.</div>
                  </div>
                  <div class="badge-live">Finance Synced</div>
                </div>
                <div class="mock-grid">
                  <div class="mock-card balance-card">
                    <small>Saldo Aktif</small>
                    <div class="balance-amount">Rp 28.450.000</div>
                    <p>Naik 18,4% dibanding bulan lalu dengan ritme transaksi yang lebih stabil dan sehat.</p>
                    <div class="metric-pills">
                      <div class="metric-pill"><strong>Rp 12.800.000</strong><small>Pemasukan bulan ini</small></div>
                      <div class="metric-pill"><strong>Rp 6.240.000</strong><small>Pengeluaran bulan ini</small></div>
                    </div>
                  </div>
                  <div class="mock-card insight-card">
                    <small>Trend Cashflow</small>
                    <h4 class="mt-2 mb-0">Performa 30 Hari</h4>
                    <div class="bars"><span></span><span></span><span></span><span></span><span></span><span></span></div>
                  </div>
                  <div class="mock-card transaction-card">
                    <small>Aktivitas Terbaru</small>
                    <h5 class="mt-2 mb-0">Transaksi Terkini</h5>
                    <div class="transactions">
                      <div class="transaction">
                        <div class="transaction-main">
                          <span class="transaction-icon"><i class="fa fa-arrow-down"></i></span>
                          <div><strong>Pembayaran Invoice</strong><small>Pemasukan • Hari ini</small></div>
                        </div>
                        <div class="income">+ Rp 3.200.000</div>
                      </div>
                      <div class="transaction">
                        <div class="transaction-main">
                          <span class="transaction-icon" style="background:rgba(226,78,92,.10);color:#e24e5c;"><i class="fa fa-arrow-up"></i></span>
                          <div><strong>Biaya Operasional</strong><small>Pengeluaran • 2 jam lalu</small></div>
                        </div>
                        <div class="expense">- Rp 860.000</div>
                      </div>
                    </div>
                  </div>
                  <div class="mock-card mini-card">
                    <small>Budget Monitoring</small>
                    <h5 class="mt-2 mb-0">76% target bulanan sudah tercapai</h5>
                    <div class="progress-shell"><span></span></div>
                    <p class="mt-3 mb-0">Kategori transaksi membantu kamu melihat pola pengeluaran terbesar lebih cepat.</p>
                  </div>
                </div>
              </div>
              <div class="floating-note fade d3">
                <strong>Insight cepat</strong>
                <small>Kondisi saldo, kategori aktif, dan laporan cashflow bisa dibaca tanpa berpindah-pindah halaman.</small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="section pt-0">
      <div class="container">
        <div class="stats">
          <div class="stat-card fade d1"><div class="stat-number">24/7</div><p class="muted-copy">Akses data cashflow kapan saja dengan tampilan yang tetap nyaman di desktop maupun mobile.</p></div>
          <div class="stat-card fade d2"><div class="stat-number">6 Core</div><p class="muted-copy">Pemasukan, pengeluaran, saldo, laporan, kategori, dan analisis dalam satu sistem.</p></div>
          <div class="stat-card fade d3"><div class="stat-number">Realtime</div><p class="muted-copy">Perubahan transaksi langsung tercermin pada ringkasan dashboard dan panel monitoring.</p></div>
          <div class="stat-card fade d4"><div class="stat-number">Clean UI</div><p class="muted-copy">Hierarki visual yang jelas membuat user baru langsung paham arah produknya.</p></div>
        </div>
      </div>
    </section>

    <section class="section pt-0" id="features">
      <div class="container">
        <span class="eyebrow">Feature Highlights</span>
        <h2 class="section-title">Didesain untuk membuat pencatatan keuangan terasa modern, ringan, dan meyakinkan.</h2>
        <p class="muted-copy" style="max-width:640px;">Setiap modul dibuat untuk membantu user memahami arus kas dengan cepat, bukan sekadar memasukkan angka. Hasilnya adalah workflow yang lebih rapi dan insight yang lebih jelas.</p>
        <div class="features mt-5">
          <div class="feature-card fade d1"><span class="feature-icon"><i class="fa fa-plus-circle"></i></span><h4>Mencatat Pemasukan</h4><p class="muted-copy">Input transaksi pemasukan dengan cepat, beri catatan, dan simpan histori secara terstruktur.</p></div>
          <div class="feature-card fade d2"><span class="feature-icon"><i class="fa fa-credit-card"></i></span><h4>Mencatat Pengeluaran</h4><p class="muted-copy">Lacak biaya operasional, kebutuhan rutin, dan pengeluaran penting agar arus kas tetap sehat.</p></div>
          <div class="feature-card fade d3"><span class="feature-icon"><i class="fa fa-tags"></i></span><h4>Mengelola Kategori</h4><p class="muted-copy">Kelompokkan transaksi agar pola pengeluaran dan sumber pemasukan lebih mudah dibaca.</p></div>
          <div class="feature-card fade d1"><span class="feature-icon"><i class="fa fa-wallet"></i></span><h4>Memantau Saldo</h4><p class="muted-copy">Lihat posisi saldo aktif secara cepat dan pahami perubahan kondisi keuangan setiap saat.</p></div>
          <div class="feature-card fade d2"><span class="feature-icon"><i class="fa fa-file-text-o"></i></span><h4>Melihat Laporan</h4><p class="muted-copy">Tinjau rekap pemasukan dan pengeluaran dalam periode tertentu tanpa repot merapikan manual.</p></div>
          <div class="feature-card fade d3"><span class="feature-icon"><i class="fa fa-line-chart"></i></span><h4>Analisis Cashflow</h4><p class="muted-copy">Temukan tren, bulan terkuat, dan area pemborosan dengan tampilan yang lebih mudah dipahami.</p></div>
        </div>
      </div>
    </section>

    <section class="section" id="preview">
      <div class="container">
        <div class="two-col">
          <div>
            <span class="eyebrow">Dashboard Preview</span>
            <h2 class="section-title">Preview antarmuka yang terasa seperti finance cockpit, bukan sekadar tabel transaksi.</h2>
            <p class="muted-copy">Arah visual halaman ini dibuat untuk menunjukkan seperti apa produk cashflow yang terasa premium, profesional, dan mudah dipercaya oleh user pertama kali.</p>
            <div class="benefits mt-4">
              <div class="benefit-card fade d1"><span class="benefit-icon"><i class="fa fa-bolt"></i></span><h4>Decision Faster</h4><p class="muted-copy">User lebih cepat melihat transaksi penting, saldo bergerak, dan kategori paling dominan.</p></div>
              <div class="benefit-card fade d2"><span class="benefit-icon"><i class="fa fa-sliders"></i></span><h4>Data Lebih Tertata</h4><p class="muted-copy">Whitespace, typography, dan card layout dibuat agar informasi terasa rapi dan tidak sempit.</p></div>
            </div>
          </div>
          <div class="preview-panel fade d3">
            <div class="preview-top">
              <div>
                <strong style="font-size:1.08rem;">Cashflow Dashboard</strong>
                <div class="dashboard-subtitle">Preview panel monitoring harian dan bulanan</div>
              </div>
              <div class="preview-tabs">
                <span class="preview-tab active">Overview</span>
                <span class="preview-tab">Reports</span>
                <span class="preview-tab">Categories</span>
              </div>
            </div>
            <div class="preview-surface">
              <div class="preview-metrics">
                <div class="preview-metric"><span>Pemasukan</span><strong>Rp 18,2 Jt</strong></div>
                <div class="preview-metric"><span>Pengeluaran</span><strong>Rp 7,4 Jt</strong></div>
                <div class="preview-metric"><span>Saldo Bersih</span><strong>Rp 10,8 Jt</strong></div>
              </div>
              <div class="chart-shell">
                <div class="chart-card">
                  <strong>Arus Kas Mingguan</strong>
                  <div class="line-chart">
                    <svg viewBox="0 0 600 220" preserveAspectRatio="none" aria-hidden="true">
                      <defs>
                        <linearGradient id="fillArea" x1="0" y1="0" x2="0" y2="1">
                          <stop offset="0%" stop-color="rgba(29,119,255,.24)"></stop>
                          <stop offset="100%" stop-color="rgba(29,119,255,.02)"></stop>
                        </linearGradient>
                      </defs>
                      <path d="M0,160 C60,138 92,70 150,82 C210,94 250,128 300,112 C350,96 385,38 450,50 C512,62 548,120 600,96 L600,220 L0,220 Z" fill="url(#fillArea)"></path>
                      <path d="M0,160 C60,138 92,70 150,82 C210,94 250,128 300,112 C350,96 385,38 450,50 C512,62 548,120 600,96" fill="none" stroke="#1d77ff" stroke-width="5" stroke-linecap="round"></path>
                    </svg>
                  </div>
                </div>
                <div class="summary-card">
                  <strong>Ringkasan Cepat</strong>
                  <div class="summary-list">
                    <div class="summary-item"><span>Kategori aktif</span><strong>12</strong></div>
                    <div class="summary-item"><span>Transaksi minggu ini</span><strong>48</strong></div>
                    <div class="summary-item"><span>Laporan terbaru</span><strong>Ready</strong></div>
                    <div class="summary-item"><span>Cashflow growth</span><strong style="color:var(--green-deep);">+21%</strong></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <span class="eyebrow">Why It Works</span>
        <h2 class="section-title">Lebih dari sekadar pencatatan, ini adalah sistem yang membuat cashflow lebih mudah dianalisis.</h2>
        <p class="muted-copy" style="max-width:680px;">Fokus desain dan struktur fitur dibuat agar user tidak kewalahan. Semua terasa lebih intuitif, lebih terukur, dan lebih meyakinkan saat ingin mengambil keputusan finansial.</p>
        <div class="benefits mt-5">
          <div class="benefit-card fade d1"><span class="benefit-icon"><i class="fa fa-eye"></i></span><h4>Lebih Mudah Dipahami</h4><p class="muted-copy">Angka penting langsung terlihat tanpa harus membaca terlalu banyak detail.</p></div>
          <div class="benefit-card fade d2"><span class="benefit-icon"><i class="fa fa-clock-o"></i></span><h4>Hemat Waktu Operasional</h4><p class="muted-copy">Pencatatan harian jadi lebih cepat sehingga user bisa fokus pada keputusan.</p></div>
          <div class="benefit-card fade d3"><span class="benefit-icon"><i class="fa fa-filter"></i></span><h4>Analisis Lebih Terarah</h4><p class="muted-copy">Kategori transaksi dan laporan membantu menemukan pola cashflow dengan lebih presisi.</p></div>
          <div class="benefit-card fade d4"><span class="benefit-icon"><i class="fa fa-mobile"></i></span><h4>Nyaman di Semua Device</h4><p class="muted-copy">Layout dibuat mobile-first agar tetap premium dan mudah dipakai di laptop maupun smartphone.</p></div>
        </div>
      </div>
    </section>

    <section class="section" id="contact">
      <div class="container">
        <div class="contact-shell">
          <div class="contact-card fade d1">
            <span class="eyebrow">Contact</span>
            <h2 class="section-title">Bangun sistem cashflow yang lebih rapi untuk aktivitas keuangan harianmu.</h2>
            <p class="muted-copy">Punya pertanyaan, butuh demo, atau ingin mulai menggunakan sistem ini untuk pencatatan keuangan? Tinggalkan pesanmu dan kita bisa lanjut dari sana.</p>
            <div class="contact-list">
              <div class="contact-item"><i class="fa fa-envelope-o"></i><div><strong>Email</strong><div class="text-muted">KINCompany@gmail.co.id</div></div></div>
              <div class="contact-item"><i class="fa fa-phone"></i><div><strong>Phone</strong><div class="text-muted">+62 812 87892110451</div></div></div>
              <div class="contact-item"><i class="fa fa-map-marker"></i><div><strong>Location</strong><div class="text-muted">Indonesia • Politeknik Gajah Tunggal</div></div></div>
            </div>
          </div>
          <div class="contact-form fade d2">
            <form id="contactForm">
              <div class="form-row">
                <div class="form-group col-md-6">
                  <label class="font-weight-bold">Nama</label>
                  <input type="text" class="form-control" placeholder="Masukkan nama">
                </div>
                <div class="form-group col-md-6">
                  <label class="font-weight-bold">Email</label>
                  <input type="email" class="form-control" placeholder="nama@email.com">
                </div>
              </div>
              <div class="form-group">
                <label class="font-weight-bold">Subjek</label>
                <input type="text" class="form-control" placeholder="Apa yang ingin kamu tanyakan?">
              </div>
              <div class="form-group">
                <label class="font-weight-bold">Pesan</label>
                <textarea class="form-control" placeholder="Ceritakan kebutuhanmu secara singkat..."></textarea>
              </div>
              <button type="submit" class="btn-solid w-100 border-0">Kirim Pesan <i class="fa fa-paper-plane-o"></i></button>
            </form>
          </div>
        </div>
      </div>
    </section>
  </main>

  <footer class="footer">
    <div class="container">
      <div class="footer-shell">
        <div>
          <a class="brand" href="#home">
            <span class="brand-mark"><i class="fa fa-line-chart"></i></span>
            <span>CashFlow Control</span>
          </a>
          <div class="muted-copy" style="margin-top:8px;">Landing page modern untuk aplikasi cashflow yang bersih, profesional, dan meyakinkan.</div>
        </div>
        <div class="footer-nav">
          <a href="#home">Home</a>
          <a href="#contact">Contact</a>
          <a href="login.php">Login</a>
          <a href="register.php">Register</a>
        </div>
      </div>
    </div>
  </footer>
  <script src="js/vendor/jquery-1.11.3.min.js"></script>
  <script src="lib/bootstrap/js/bootstrap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    document.getElementById('contactForm')?.addEventListener('submit', function (event) {
      event.preventDefault();

      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'success',
          title: 'Pesan diterima',
          text: 'Pesan kamu sudah tercatat. Form ini siap dihubungkan ke backend kapan saja.',
          confirmButtonColor: '#0ea5e9'
        });
      } else {
        alert('Pesan kamu sudah tercatat. Form ini siap dihubungkan ke backend kapan saja.');
      }
    });
  </script>
</body>
</html>
