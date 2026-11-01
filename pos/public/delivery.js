document.querySelectorAll('form[data-delivery]').forEach((form) => {
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (form.dataset.busy === '1') return;
    form.dataset.busy = '1';
    const body = new FormData(form);
    const button = form.querySelector('button[type=submit]');
    const notice = form.querySelector('[role=alert]');
    button.disabled = true;
    notice.textContent = 'กำลังบันทึก…';
    const password = form.querySelector('[name=manager_password]');
    if (password) password.value = '';
    try {
      const response = await fetch(form.getAttribute('action'), { method: 'POST', headers: { Accept: 'application/json' }, body });
      if (!response.ok) {
        notice.textContent = response.status === 409 ? 'ข้อมูลหรือสถานะเปลี่ยนแล้ว กรุณาตรวจข้อมูลล่าสุดก่อนทำซ้ำ' : response.status === 401 || response.status === 419 ? 'Session หมดอายุ กรุณาเข้าสู่ระบบใหม่ ร่างในหน้านี้ยังอยู่' : response.status === 403 ? 'ไม่มีสิทธิ์หรือยืนยันรหัสผ่านไม่สำเร็จ' : 'บันทึกไม่ได้ กรุณาตรวจข้อมูลและเงื่อนไขงาน';
        return;
      }
      window.location.assign('/deliveries');
    } catch {
      notice.textContent = 'เชื่อมต่อไม่สำเร็จ ร่างยังอยู่ ลองใหม่ด้วยรายการเดิมได้ ระบบป้องกันบันทึกซ้ำ';
    } finally {
      button.disabled = false;
      form.dataset.busy = '0';
    }
  });
});
