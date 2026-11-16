const paperSize = document.querySelector('#paper-size');
const pageStyle = document.querySelector('#receipt-page-size');
function updatePaperSize() {
 const paper = ['80','58','a4'].includes(paperSize.value) ? paperSize.value : '80';
 document.body.dataset.paper = paper;
 if (paper === 'a4') { pageStyle.textContent = '@page { size: A4; margin: 12mm; }'; return; }
 const receipt = document.querySelector('.receipt');
 const margin = window.matchMedia('print').matches ? 8 : 0;
 const height = Math.max(100, Math.ceil(receipt.getBoundingClientRect().height * 25.4 / 96 + margin + 2));
 pageStyle.textContent = `@page { size: ${paper}mm ${height}mm; margin: 4mm; }`;
}
paperSize?.addEventListener('change', updatePaperSize);
window.addEventListener('load', updatePaperSize);
window.addEventListener('beforeprint', updatePaperSize);
const printButton = document.querySelector('#print-receipt');
printButton?.addEventListener('click', () => { updatePaperSize(); window.print(); });
const copyButton = document.querySelector('#copy-link');
copyButton?.addEventListener('click', async () => {
 const field = document.querySelector('#copy-url');
 const status = document.querySelector('#copy-status');
 try {
  if (!navigator.clipboard?.writeText) { throw new Error('Clipboard unavailable'); }
  await navigator.clipboard.writeText(field.value);
  status.textContent = 'Tautan disalin. Tempelkan di pesan untuk pelanggan.';
 } catch {
  field.focus(); field.select(); field.setSelectionRange(0,field.value.length);
  status.textContent = 'Tautan sudah dipilih. Tekan Ctrl+C (Windows) atau Cmd+C (Mac) untuk menyalin.';
 }
});
