const printButton = document.querySelector('#print-receipt');
printButton?.addEventListener('click', () => window.print());
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
