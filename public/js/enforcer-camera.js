(() => {
    const input = document.getElementById('minor_photos');
    const open = document.getElementById('open-camera');
    const panel = document.getElementById('camera-panel');
    const video = document.getElementById('camera-preview');
    const capture = document.getElementById('capture-photo');
    const status = document.getElementById('camera-status');
    const gallery = document.getElementById('captured-photos');
    let stream = null;
    let generation = 0;
    let busy = false;
    const photos = [];
    function stop() {
        generation++;
        stream?.getTracks().forEach(track => track.stop());
        stream = null;
        video.srcObject = null;
        panel.hidden = true;
        open.disabled = false;
        capture.disabled = true;
    }
    function sync() {
        const transfer = new DataTransfer();
        photos.forEach(photo => transfer.items.add(photo.file));
        input.files = transfer.files;
        gallery.replaceChildren();
        photos.forEach((photo, index) => {
            const item = document.createElement('div');
            const img = document.createElement('img');
            img.src = photo.url;
            img.alt = 'Captured picture ' + (index + 1);
            img.style.cssText = 'width:120px;height:120px;object-fit:cover;display:block';
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-sm btn-outline-danger';
            remove.textContent = 'Remove picture ' + (index + 1);
            remove.onclick = () => {
                URL.revokeObjectURL(photos[index].url);
                photos.splice(index, 1);
                sync();
            };
            item.append(img, remove);
            gallery.append(item);
        });
        status.textContent = photos.length + ' picture(s) captured.';
        capture.disabled = !stream || busy || photos.length >= 5;
    }
    open.addEventListener('click', async () => {
        if (!navigator.mediaDevices?.getUserMedia || !window.isSecureContext) {
            status.textContent = 'Camera access requires HTTPS (or localhost) and a browser with camera support.';
            return;
        }
        open.disabled = true;
        const request = ++generation;
        status.textContent = 'Allow camera access to take a picture.';
        try {
            const camera = await navigator.mediaDevices.getUserMedia({video: {facingMode: {ideal: 'environment'}}, audio: false});
            if (request !== generation) { camera.getTracks().forEach(track => track.stop()); return; }
            stream = camera;
            video.srcObject = stream;
            panel.hidden = false;
            await video.play();
            capture.disabled = photos.length >= 5;
            status.textContent = 'Camera ready. Tap Capture picture.';
        } catch (error) {
            stop();
            status.textContent = error.name === 'NotAllowedError'
                ? 'Camera permission was denied. Allow camera access in your browser and try again.'
                : 'The camera could not be opened. Check that a camera is connected and available, then try again.';
        }
    });
    capture.addEventListener('click', () => {
        if (!stream || busy || photos.length >= 5 || !video.videoWidth) return;
        busy = true;
        capture.disabled = true;
        const canvas = document.createElement('canvas');
        const scale = Math.min(1, 1600 / Math.max(video.videoWidth, video.videoHeight));
        canvas.width = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(blob => {
            busy = false;
            if (!blob || blob.size > 5 * 1024 * 1024) {
                status.textContent = 'Picture could not be captured. Please try again.';
                capture.disabled = !stream;
                return;
            }
            photos.push({file: new File([blob], 'capture-' + Date.now() + '.jpg', {type: 'image/jpeg'}), url: URL.createObjectURL(blob)});
            sync();
        }, 'image/jpeg', 0.9);
    });
    document.getElementById('close-camera').addEventListener('click', stop);
    input.form.addEventListener('submit', event => {
        if (busy) { event.preventDefault(); status.textContent = 'Please wait for the picture to finish capturing.'; return; }
        stop();
    });
    window.addEventListener('pagehide', stop);
    document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); });
})();
