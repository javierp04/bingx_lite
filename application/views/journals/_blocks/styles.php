<style>
  /* Estilos compartidos por el detalle de trade y la card desplegable del dashboard */
  .text-profit{color:#28a745;} .text-loss{color:#dc3545;}
  .lbl{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:#8a94a6;}
  .calc{background:#eef4ff;border-left:4px solid #0d6efd;border-radius:6px;padding:12px 14px;font-size:.9rem;}
  .calc b{color:#0d6efd;}
  .cmp td,.cmp th{padding:.3rem .55rem;font-variant-numeric:tabular-nums;}
  .cmp .arrow{color:#8a94a6;}
  .pill{font-size:.7rem;} .badge-soft{background:#eef4ff;color:#0d6efd;}
  .corr-grid{display:flex;flex-wrap:wrap;gap:16px;font-variant-numeric:tabular-nums;font-size:.86rem;}
  .corr-grid .lbl{margin-bottom:1px;}
  /* timeline (detalle) */
  .tl{position:relative;margin-left:8px;}
  .tl:before{content:"";position:absolute;left:9px;top:4px;bottom:4px;width:2px;background:#dde3ea;}
  .tl-item{position:relative;padding:0 0 18px 34px;}
  .tl-dot{position:absolute;left:0;top:2px;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.6rem;}
  .tl-item h6{margin:0;font-size:.92rem;} .tl-item small{color:#6c757d;}
  /* cards desplegables (dashboard) — grilla alineada tipo tabla */
  .sig-grid-wrap{overflow-x:auto;}
  .sig-card{box-shadow:0 .075rem .2rem rgba(0,0,0,.05);margin-bottom:8px;border:1px solid #e7ebf0;min-width:780px;}
  .sig-thead,.sig-head{display:grid;grid-template-columns:44px 140px minmax(180px,1.4fr) minmax(120px,1fr) 70px 88px 104px 22px;align-items:center;gap:.6rem;padding:.5rem .85rem;}
  .sig-thead{min-width:780px;border:1px solid transparent;font-size:.68rem;text-transform:uppercase;letter-spacing:.03em;color:#8a94a6;padding-top:.2rem;padding-bottom:.2rem;}
  .sig-head{cursor:pointer;}
  .sig-head:hover{background:#fbfcfe;}
  .sig-estado{display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;}
  .sig-num{font-variant-numeric:tabular-nums;}
  .sig-r{text-align:right;}
  .chev{transition:transform .15s;color:#8a94a6;text-align:center;}
  .sig-head[aria-expanded="true"] .chev{transform:rotate(180deg);}
</style>
