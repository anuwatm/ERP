import React, { useEffect, useRef, useState } from 'react';

export type MembershipOptions = {
  publication_id: string | null;
  members: {member_id: string; code: string; name_masked: string; phone_masked: string; tier: string}[];
  policies: {id: string; name: string}[];
};
export type MembershipSelection = {
  payload: {membership_publication_id: string; member_id?: string; promotion_id?: string};
  ready: boolean;
  cartKey: string;
  total: string | null;
};

export default function MembershipPicker({data, input, disabled, manualDiscount = false, onChange, refresh}: {
  data: MembershipOptions; input: Record<string, unknown>; disabled: boolean; manualDiscount?: boolean;
  onChange: (value: MembershipSelection | null) => void; refresh: () => Promise<void>;
}) {
  const [member, setMember] = useState('');
  const [memberSearch, setMemberSearch] = useState('');
  const [promotion, setPromotion] = useState('');
  const [total, setTotal] = useState<string | null>(null);
  const [error, setError] = useState('');
  const [pending, setPending] = useState(false);
  const cartKey = JSON.stringify(input);
  const fingerprint = JSON.stringify([cartKey, data.publication_id, member, promotion, manualDiscount]);
  const current = useRef(fingerprint);
  current.current = fingerprint;
  const payload = (m = member, p = promotion) => ({membership_publication_id: data.publication_id || '', ...(m ? {member_id: m} : {}), ...(p ? {promotion_id: p} : {})});
  const select = (m: string, p: string) => {
    setMember(m); setPromotion(p); setTotal(null); setError('');
    onChange(m || p ? {payload: payload(m, p), ready: !p && !!data.publication_id, cartKey, total: null} : null);
  };
  useEffect(() => {
    setTotal(null); setError('');
    const memberValid = !member || data.members.some(x => x.member_id === member);
    const promotionValid = !promotion || data.policies.some(x => x.id === promotion);
    if ((member || promotion) && (!data.publication_id || !memberValid || !promotionValid)) setError('สิทธิ์เดิมไม่พร้อมใช้ โปรดอัปเดตและเลือกใหม่ หรือยกเลิกตัวเลือกด้วยตนเอง');
    onChange(member || promotion ? {payload: payload(), ready: !promotion && !!data.publication_id && memberValid && promotionValid, cartKey, total: null} : null);
  }, [cartKey, data.publication_id, member, promotion, manualDiscount]);
  useEffect(() => () => { current.current = ''; onChange(null); }, [onChange]);
  const preview = async () => {
    const expected = fingerprint;
    setPending(true); setError('');
    onChange({payload:payload(), ready:false, cartKey, total:null});
    try {
      const response = await fetch('/membership/quote', {method:'POST', credentials:'same-origin', headers:{Accept:'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || ''}, body:JSON.stringify({...input, ...payload()})});
      const body = await response.json();
      if (!response.ok) throw new Error(Object.values(body.errors || {}).flat().join(' ') || body.message || 'ตรวจสิทธิ์ไม่สำเร็จ');
      if (expected !== current.current) return;
      const result = body.quote?.total || null;
      if (result === '0.00') throw new Error('บิลเงินสดต้องมียอดสุทธิมากกว่า 0 กรุณาเลือกโปรโมชันอื่น');
      setTotal(result);
      onChange({payload:payload(), ready:!!result && !manualDiscount, cartKey, total:result});
    } catch (e) {
      if (expected === current.current) setError(e instanceof Error ? e.message : 'ตรวจสิทธิ์ไม่สำเร็จ');
    } finally { setPending(false); }
  };
  return <fieldset className="membership-picker"><legend>สมาชิก / โปรโมชัน</legend>
    <label>ค้นหารหัสสมาชิกหรือ4หลักท้ายโทรศัพท์<input value={memberSearch} disabled={disabled || pending} onChange={e => setMemberSearch(e.target.value)} autoComplete="off" /></label>
    <label>สมาชิก<select aria-label="สมาชิกของบิล" value={member} disabled={disabled || pending} onChange={e => select(e.target.value, promotion)}><option value="">ไม่ผูกสมาชิก</option>{member && !data.members.some(m => m.member_id === member) && <option value={member}>สมาชิกเดิมไม่พร้อมใช้ — เลือกใหม่</option>}{data.members.filter(m => m.member_id === member || (m.code+' '+m.phone_masked+' '+m.name_masked).toLowerCase().includes(memberSearch.toLowerCase())).map(m => <option key={m.member_id} value={m.member_id}>{m.code} · {m.name_masked} · {m.phone_masked} · {m.tier}</option>)}</select></label>
    <label>โปรโมชัน<select aria-label="โปรโมชันของบิล" value={promotion} disabled={disabled || pending || manualDiscount} onChange={e => select(member, e.target.value)}><option value="">ไม่ใช้โปรโมชัน</option>{promotion && !data.policies.some(p => p.id === promotion) && <option value={promotion}>โปรโมชันเดิมไม่พร้อมใช้ — เลือกใหม่</option>}{data.policies.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}</select></label>
    {manualDiscount && <p>ไม่ใช้โปรโมชันร่วมกับส่วนลดผู้จัดการ</p>}
    <button type="button" disabled={disabled || pending || !promotion || manualDiscount} onClick={preview}>{pending ? 'กำลังตรวจ…' : 'ตรวจยอดโปรโมชัน'}</button>
    <button type="button" disabled={disabled || pending} onClick={() => refresh().catch(() => setError('อัปเดตสิทธิ์ไม่สำเร็จ'))}>อัปเดตสิทธิ์</button>
    <button type="button" disabled={disabled || pending} onClick={() => select('', '')}>ไม่ใช้สมาชิก/โปรโมชัน</button>
    {total && <p role="status">ยอดหลังโปรโมชันรวม VAT {total}</p>}
    {error && <p role="alert">{error}</p>}
    <p>ใช้ได้หนึ่งโปรโมชันต่อบิล การตรวจยอดยังไม่กันสิทธิ์ Server ยืนยันอีกครั้งตอนรับเงิน <a href="/membership">สมัครสมาชิก / ดูคำขอ</a></p>
  </fieldset>;
}
