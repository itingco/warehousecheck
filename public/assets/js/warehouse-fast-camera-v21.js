/* Scoped camera engine: no business logic, no database writes. */
(() => {
  const clean = value => String(value || '').trim();
  const isWebsite = value => /^https?:\/\//i.test(clean(value));
  window.WarehouseFastCameraV21 = function (container, onCode, onMiss, onStatus) {
    let video = null, stream = null, controls = null, timer = null, busy = false;
    let generation = 0, reader = null, detectionPending = false;
    const tell = text => onStatus?.(text);
    function cleanup() {
      generation++;
      if (timer) clearInterval(timer);
      timer = null;
      try { controls?.stop(); } catch (_) {}
      controls = null;
      try { stream?.getTracks().forEach(track => track.stop()); } catch (_) {}
      stream = null;
      try { video?.srcObject?.getTracks().forEach(track => track.stop()); } catch (_) {}
      if (video) video.srcObject = null;
      busy = false;
    }
    function makeVideo() {
      container.replaceChildren();
      video = document.createElement('video');
      video.setAttribute('playsinline', '');
      video.setAttribute('autoplay', '');
      video.muted = true;
      video.style.cssText = 'display:block;width:100%;height:auto;min-height:200px;object-fit:cover;background:#111827';
      container.append(video);
      return video;
    }
    const detected = result => {
      const value = clean(result?.getText?.() || result?.rawValue || result);
      if (value && !isWebsite(value)) onCode(value);
      else onMiss?.();
    };
    async function start() {
      if (busy) return;
      cleanup();
      if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) throw new Error('Kamera memerlukan HTTPS atau localhost dan izin kamera.');
      busy = true;
      const token = generation;
      makeVideo();
      tell('Meminta akses kamera belakang...');
      try {
        const Reader = window.ZXingBrowser?.BrowserMultiFormatReader;
        if (typeof Reader === 'function') {
          reader = new Reader(undefined, {delayBetweenScanAttempts: 110, delayBetweenScanSuccess: 180});
          try {
            controls = await reader.decodeFromConstraints({ audio:false, video:{facingMode:{ideal:'environment'}} }, video, (result) => {
              if (token !== generation) return;
              if (result) detected(result); else onMiss?.();
            });
          } catch (e) {
            controls = await reader.decodeFromConstraints({audio:false,video:true}, video, (result) => {
              if (token !== generation) return;
              if (result) detected(result); else onMiss?.();
            });
          }
          if (token !== generation) { controls?.stop(); return; }
          stream = video.srcObject;
        } else if (typeof window.BarcodeDetector === 'function') {
          stream = await navigator.mediaDevices.getUserMedia({audio:false,video:{facingMode:{ideal:'environment'}}});
          if (token !== generation) { stream.getTracks().forEach(track=>track.stop()); return; }
          video.srcObject = stream; await video.play();
          const detector = new BarcodeDetector();
          timer = setInterval(async () => {
            if (token !== generation || detectionPending || video.readyState < 2) return;
            detectionPending = true;
            try {
              const matches = await detector.detect(video);
              if (matches.length === 1) detected(matches[0]);
              else onMiss?.();
            } catch (_) { onMiss?.(); }
            finally { detectionPending = false; }
          }, 170);
        } else {
          throw new Error('Decoder tidak tersedia. Periksa file ZXing atau gunakan browser dengan BarcodeDetector.');
        }
        try { await stream?.getVideoTracks()?.[0]?.applyConstraints?.({advanced:[{focusMode:'continuous'}]}); } catch (_) {}
        tell('Kamera aktif. Arahkan barcode atau ambil foto untuk AI.');
      } catch (e) { cleanup(); throw e; }
      finally { if (token === generation) busy = false; }
    }
    async function snapshot() {
      if (!video || video.readyState < 2 || !video.videoWidth) throw new Error('Kamera belum menampilkan gambar.');
      const canvas = document.createElement('canvas');
      const scale = Math.min(1, 1600 / Math.max(video.videoWidth,video.videoHeight));
      canvas.width = Math.round(video.videoWidth*scale); canvas.height = Math.round(video.videoHeight*scale);
      canvas.getContext('2d',{alpha:false}).drawImage(video,0,0,canvas.width,canvas.height);
      let barcode = '';
      if (typeof window.BarcodeDetector === 'function') {
        try { const found = await new BarcodeDetector().detect(canvas); if (found.length === 1) barcode = clean(found[0].rawValue); } catch (_) {}
      }
      if (!barcode && typeof window.ZXingBrowser?.BrowserMultiFormatReader === 'function') {
        try {
          const img = new Image(); img.src = canvas.toDataURL('image/jpeg',.88); await img.decode();
          const r = new window.ZXingBrowser.BrowserMultiFormatReader();
          barcode = clean((await r.decodeFromImageElement(img)).getText());
        } catch (_) {}
      }
      return {barcode:isWebsite(barcode)?'':barcode, image:canvas.toDataURL('image/jpeg',.85)};
    }
    return {start,stop:cleanup,snapshot, isRunning:()=>!!(controls||stream)};
  };
})();
