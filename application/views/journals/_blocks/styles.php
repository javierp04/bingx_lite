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
  /* cards desplegables (dashboard) */
  .sig-card{box-shadow:0 .075rem .2rem rgba(0,0,0,.05);margin-bottom:10px;border:1px solid #e7ebf0;}
  .sig-head{cursor:pointer;padding:.55rem .85rem;display:flex;align-items:center;gap:.85rem;flex-wrap:wrap;}
  .sig-head:hover{background:#fbfcfe;}
  .sig-head .num{font-variant-numeric:tabular-nums;}
  .sig-head .grow{flex:1;min-width:120px;}
  .chev{transition:transform .15s;color:#8a94a6;}
  .sig-head[aria-expanded="true"] .chev{transform:rotate(180deg);}
</style>
