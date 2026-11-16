const confirmationDialog = document.querySelector('#action-confirm');
const approvedForms = new WeakSet();
let pendingConfirmation = null;

document.querySelectorAll('form[data-confirm]').forEach(form => {
 form.addEventListener('submit', event => {
  if (approvedForms.has(form)) { approvedForms.delete(form); return; }
  event.preventDefault();
  if (!confirmationDialog || confirmationDialog.open) { return; }
  pendingConfirmation = {form, submitter:event.submitter};
  const payment = new URL(form.action).pathname.endsWith('/payment');
  document.querySelector('#confirm-title').textContent = payment ? 'CATAT PEMBAYARAN?' : 'CUCIAN SUDAH DIAMBIL?';
  document.querySelector('#confirm-description').textContent = form.dataset.confirm;
  document.querySelector('#confirm-accept').textContent = payment ? 'Ya, catat pembayaran' : 'Ya, sudah diambil';
  confirmationDialog.returnValue = '';
  confirmationDialog.showModal();
 });
});

confirmationDialog?.addEventListener('close', () => {
 const pending = pendingConfirmation;
 pendingConfirmation = null;
 if (confirmationDialog.returnValue !== 'confirm' || !pending?.form.isConnected) { return; }
 approvedForms.add(pending.form);
 try {
  if (pending.submitter?.form === pending.form) { pending.form.requestSubmit(pending.submitter); }
  else { pending.form.requestSubmit(); }
 } finally {
  approvedForms.delete(pending.form);
 }
});
