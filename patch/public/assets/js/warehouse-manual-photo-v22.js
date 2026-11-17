(() => {
  const ready = () => {
    const root = document.getElementById('wc-manual-v22');
    if (!root) return;
    const el = id => document.getElementById(id);
    const form = el('wc-manual-lookup-form'), code = el('wc-manual-code');
    const message = el('wc-manual-message'), modal = el('wc-manual-modal');
    const modalMessage = el('wc-manual-modal-message');
    const cameraStart = el('wc-manual-camera-start'), capture = el('wc-manual-capture');
    const preview = el('wc-manual-photo-preview'), retake = el('wc-manual-retake');
    const video = el('wc-manual-video'), qty = el('wc-manual-qty'), save = el('wc-manual-submit');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let selected = null, photo = null, stream = null, busy = false;
    const status = (node,text) => { node.textContent = text; };
    function validSave() { save.disabled = busy || !selected || !photo || !(Number(qty.value)>0); }
    function stopCamera() { if(stream) stream.getTracks().forEach(track=>track.stop()); stream = null; video.srcObject=null; capture.disabled=true; }
    function close() { stopCamera(); modal.classList.remove('is-open'); modal.setAttribute('aria-hidden','true'); document.body.classList.remove('modal-open'); selected=null; photo=null; qty.value=''; preview.hidden=true; validSave(); }
    async function readJson(response) { let data; try { data=await response.json(); } catch (_) { throw new Error('HTTP '+response.status+': server tidak mengembalikan JSON'); } if(!response.ok || !data.ok) throw new Error(Object.values(data.errors||{}).flat()[0]||data.message||'HTTP '+response.status); return data; }
    form.addEventListener('submit', async e => {
      e.preventDefault(); if (busy) return;
      busy=true; status(message,'Memeriksa kode pada dokumen...');
      try {
        const url = root.dataset.lookupUrl+'?item_code='+encodeURIComponent(code.value.trim());
        const data = await readJson(await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'}));
        selected=data; photo=null; qty.value=''; preview.hidden=true;
        el('wc-manual-item').textContent=data.item_code;
        el('wc-manual-name').textContent=data.item_name;
        el('wc-manual-remaining').textContent='Sisa qty dokumen (base UOM): '+data.remaining_qty+(data.uom_code?' · UOM dokumen: '+data.uom_code:'');
        status(message,'Kode ditemukan. Masukkan Qty dan ambil foto bukti.'); status(modalMessage,'');
        modal.classList.add('is-open'); modal.setAttribute('aria-hidden','false'); document.body.classList.add('modal-open');
        validSave();
      } catch(error) { status(message,error.message); }
      finally { busy=false; validSave(); }
    });
    async function startCamera() {
      if (stream) stopCamera();
      if (!navigator.mediaDevices?.getUserMedia || !window.isSecureContext) { status(modalMessage,'Akses kamera harus melalui HTTPS atau localhost yang aman.'); return; }
      status(modalMessage,'Menghubungkan kamera...');
      try {
        try { stream = await navigator.mediaDevices.getUserMedia({audio:false,video:{facingMode:{ideal:'environment'},width:{ideal:1280}}}); }
        catch (_) { stream = await navigator.mediaDevices.getUserMedia({audio:false,video:true}); }
        video.srcObject=stream; await video.play(); capture.disabled=false;
        el('wc-manual-camera-hint').textContent='Arahkan kamera ke barang lalu Ambil Foto';
        status(modalMessage,'Kamera aktif. Foto wajib diambil sebelum menyimpan.');
      } catch(error) { status(modalMessage,'Kamera gagal: '+(error.message||error)); stopCamera(); }
    }
    cameraStart.addEventListener('click',startCamera);
    capture.addEventListener('click', () => {
      if(!stream || !video.videoWidth) { status(modalMessage,'Kamera belum siap.'); return; }
      const canvas=document.createElement('canvas');
      const scale=Math.min(1,1600/Math.max(video.videoWidth,video.videoHeight));
      canvas.width=Math.round(video.videoWidth*scale); canvas.height=Math.round(video.videoHeight*scale);
      canvas.getContext('2d').drawImage(video,0,0,canvas.width,canvas.height);
      photo=canvas.toDataURL('image/jpeg',0.83);
      preview.src=photo; preview.hidden=false; retake.hidden=false;
      stopCamera(); status(modalMessage,'Foto bukti sudah diambil dari kamera.'); validSave();
    });
    retake.addEventListener('click', () => { photo=null; preview.hidden=true; retake.hidden=true; validSave(); startCamera(); });
    qty.addEventListener('input',validSave);
    el('wc-manual-save-form').addEventListener('submit',async e => {
      e.preventDefault(); validSave(); if(save.disabled) return;
      busy=true;validSave();status(modalMessage,'Menyimpan qty dan foto...');
      try {
        const data=await readJson(await fetch(root.dataset.saveUrl,{
          method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},
          body:JSON.stringify({item_code:selected.item_code,qty:qty.value,photo})
        }));
        const line=data.line;
        if(line){const row=el('line-'+line.id); if(row){
          const progress=row.querySelector('.progress-text');
          if(progress&&line.expected_base_qty!==null) progress.textContent=Number(line.scanned_base_qty).toFixed(4)+' / '+Number(line.expected_base_qty).toFixed(4);
          const badge=row.querySelector('.line-status'); if(badge){badge.textContent=line.status;badge.className='badge line-status badge-'+String(line.status).toLowerCase();}
          if(line.status==='OK') row.querySelector('.js-open-override')?.remove();
        }}
        if(data.session_complete){const finalBtn=el('finalizeBtn'); if(finalBtn) finalBtn.disabled=false;}
        close(); status(message,'Berhasil: '+data.message);
        if(navigator.vibrate) navigator.vibrate(90);
      } catch(error){status(modalMessage,error.message);}
      finally{busy=false;validSave();}
    });
    document.querySelectorAll('[data-wc-manual-close]').forEach(node=>node.addEventListener('click',close));
    document.addEventListener('keydown',e=>{if(e.key==='Escape'&&modal.classList.contains('is-open'))close();});
    window.addEventListener('beforeunload',stopCamera);
  };
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',ready);else ready();
})();
