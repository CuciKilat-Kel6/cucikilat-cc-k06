const service = document.querySelector('#service');
if (service) {
 const weight = document.querySelector('#weight'), quantity = document.querySelector('#quantity');
 const money = new Intl.NumberFormat('id-ID', {style:'currency',currency:'IDR',maximumFractionDigits:0});
 function estimate() {
  const option = service.selectedOptions[0], unit = option?.dataset.type === 'satuan';
  document.querySelector('#weight-label').hidden = unit;
  document.querySelector('#quantity-label').hidden = !unit;
  weight.disabled = unit; weight.required = !unit;
  quantity.disabled = !unit; quantity.required = unit;
  const amount = unit ? Number(quantity.value) : Number(weight.value);
  if (!option?.dataset.price || !Number.isFinite(amount) || amount <= 0) { document.querySelector('#estimate').textContent = 'ISI CUCIANMU.'; document.querySelector('#estimate-detail').textContent = 'Pilih layanan dan isi berat atau jumlah item.'; return; }
  const billable = unit ? amount : Math.max(2,Math.ceil(Math.round(amount*1000)/500)*.5);
  const express = option.dataset.type === 'express';
  document.querySelector('#estimate').textContent = money.format(Math.ceil(billable*Number(option.dataset.price)*(express?1.5:1)));
  document.querySelector('#estimate-detail').textContent = `${billable} ${unit?'item':'kg tagihan'}${express?' + biaya express 50%':''} · Estimasi ${express?'1':'3'} hari.`;
 }
 [service,weight,quantity].forEach(el=>el.addEventListener('input',estimate)); estimate();
}

const orderForm = service?.closest('form');
if (orderForm) {
 orderForm.addEventListener('submit', () => { const button = orderForm.querySelector('button[type="submit"], button:not([type])'); if (button) { button.disabled = true; button.textContent = 'Menyimpan…'; } });
 window.addEventListener('pageshow', () => { const button = orderForm.querySelector('button[type="submit"], button:not([type])'); if (button) { button.disabled = false; button.textContent = orderForm.querySelector('[name="_method"]') ? 'Simpan perubahan ↗' : 'Catat order ↗'; } });
}
