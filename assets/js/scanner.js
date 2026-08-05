/**
 * Camera Document Scanner Integration
 */

let videoStream = null;

function startCameraScanner() {
  const video = document.getElementById('scannerVideo');
  const modal = new bootstrap.Modal(document.getElementById('cameraScannerModal'));
  modal.show();

  navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
    .then(stream => {
      videoStream = stream;
      video.srcObject = stream;
    })
    .catch(err => {
      console.error("Camera access error: ", err);
      alert("Unable to access camera: " + err.message);
    });
}

function captureDocumentPhoto() {
  const video = document.getElementById('scannerVideo');
  const canvas = document.getElementById('scannerCanvas');
  const context = canvas.getContext('2d');

  canvas.width = video.videoWidth || 640;
  canvas.height = video.videoHeight || 480;
  context.drawImage(video, 0, 0, canvas.width, canvas.height);

  const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
  document.getElementById('scannedImageBase64').value = dataUrl;

  // Show preview
  const previewImg = document.getElementById('scannerPreviewImg');
  previewImg.src = dataUrl;
  previewImg.classList.remove('d-none');
  
  stopCamera();
  showToast("Document captured successfully! Click 'Upload Scanned Doc' to save.", 'success');
}

function stopCamera() {
  if (videoStream) {
    videoStream.getTracks().forEach(track => track.stop());
    videoStream = null;
  }
}
