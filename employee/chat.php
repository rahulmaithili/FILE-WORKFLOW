<?php
$pageTitle = 'Enterprise Chat & Document Exchange';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

$user = getLoggedInUser();
?>

<div class="row g-4" style="height: calc(100vh - 130px);">
  <!-- Left Side: Chat Feed Area -->
  <div class="col-lg-9 h-100">
    <div class="card border-0 shadow-sm d-flex flex-column h-100" style="border-radius: var(--radius-lg); overflow: hidden;">
      
      <!-- Chat Header -->
      <div class="card-header bg-dark text-white p-3 border-0 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
          <div class="avatar-circle bg-primary-soft text-primary" style="width: 40px; height: 40px; border: none;">
            <i class="fas fa-comments"></i>
          </div>
          <div>
            <h6 class="fw-bold mb-0">Central Chat Feed & File Transfer</h6>
            <small class="text-muted text-white-50" style="font-size: 0.75rem;">Discuss tasks and share project documents in real-time</small>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <!-- Mobile Directory Toggle Button -->
          <button type="button" class="btn btn-sm btn-outline-light d-lg-none" data-bs-toggle="modal" data-bs-target="#mobileOnlineUsersModal" title="View Online Users">
            <i class="fas fa-users me-1"></i> <span id="mobileOnlineCountBadge" class="badge bg-success">0</span>
          </button>
          
          <span class="badge bg-success-soft text-success px-2.5 py-1.5 rounded-pill shadow-sm" id="chatActiveUserCount">
            <i class="fas fa-circle-notch fa-spin me-1"></i> Syncing...
          </span>
        </div>
      </div>

      <!-- Messages Viewport -->
      <div class="card-body p-4 flex-grow-1" id="chatMessagesViewport" style="overflow-y: auto; background-color: var(--bg-main);">
        <div class="text-center py-5 text-muted" id="chatLoadingIndicator">
          <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
          <div>Loading central chat stream...</div>
        </div>
      </div>

      <!-- Selected File Preview Bar (Hidden initially) -->
      <div class="px-4 py-2 bg-light border-top d-none align-items-center justify-content-between" id="selectedFilePreviewBar">
        <div class="d-flex align-items-center gap-2">
          <i class="fas fa-paperclip text-primary"></i>
          <span id="selectedFileName" class="small fw-semibold text-dark">No file selected</span>
        </div>
        <button type="button" class="btn-close btn-sm" id="cancelSelectedFileBtn"></button>
      </div>

      <!-- Message Form Input -->
      <div class="card-footer p-3 border-0 bg-light">
        <form id="chatForm" class="d-flex gap-2 align-items-center" enctype="multipart/form-data">
          <!-- Hidden File Upload Input -->
          <input type="file" name="chat_file" id="chatFileInput" class="d-none">
          
          <button type="button" class="btn btn-outline-secondary btn-lg" id="attachFileBtn" title="Attach Document / Photo">
            <i class="fas fa-paperclip"></i>
          </button>
          
          <input type="text" name="message" id="chatMessageInput" class="form-control form-control-lg border-0 shadow-sm" placeholder="Write a message or drop updates..." autocomplete="off">
          
          <button type="submit" class="btn btn-primary btn-lg px-4 fw-bold shadow-sm" id="chatSendBtn">
            <i class="fas fa-paper-plane"></i>
          </button>
        </form>
      </div>

    </div>
  </div>

  <!-- Right Side: Active Online Directory Panel -->
  <div class="col-lg-3 h-100 d-none d-lg-block">
    <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: var(--radius-lg); overflow-y: auto;">
      <h6 class="fw-bold mb-3 border-bottom pb-2 text-dark">
        <i class="fas fa-users text-primary me-2"></i> Online Directory
      </h6>
      <div class="list-group list-group-flush gap-2" id="onlineUsersList">
        <!-- Rendered dynamically -->
        <p class="text-muted small text-center my-4">Fetching online users...</p>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Premium Document In-App Preview & Print -->
<div class="modal fade" id="documentPreviewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-lg);">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title fw-bold" id="previewModalLabel">Document Preview</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 text-center bg-light" style="min-height: 250px;">
        <div id="previewContentContainer" class="d-flex justify-content-center align-items-center w-100 h-100">
          <!-- Dynamically loaded preview content -->
        </div>
      </div>
      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary fw-semibold" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-success fw-bold px-4" id="printPreviewBtn">
          <i class="fas fa-print me-1"></i> Print Document
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Javascript Client-Side Chat Handler -->
<script>
let lastMessageId = 0;
const currentUserId = <?= intval($user['id']) ?>;

document.addEventListener('DOMContentLoaded', () => {
  const viewport = document.getElementById('chatMessagesViewport');
  const chatForm = document.getElementById('chatForm');
  const msgInput = document.getElementById('chatMessageInput');
  const fileInput = document.getElementById('chatFileInput');
  const attachBtn = document.getElementById('attachFileBtn');
  const previewBar = document.getElementById('selectedFilePreviewBar');
  const previewName = document.getElementById('selectedFileName');
  const cancelFileBtn = document.getElementById('cancelSelectedFileBtn');
  
  // Attach File Event handlers
  attachBtn.addEventListener('click', () => fileInput.click());
  fileInput.addEventListener('change', () => {
    if (fileInput.files.length > 0) {
      previewName.textContent = fileInput.files[0].name;
      previewBar.classList.remove('d-none');
      previewBar.classList.add('d-flex');
    }
  });

  cancelFileBtn.addEventListener('click', () => {
    fileInput.value = '';
    previewBar.classList.remove('d-flex');
    previewBar.classList.add('d-none');
  });

  // Fetch Message Stream Loop
  function fetchMessages() {
    fetch(`<?= APP_URL ?>/api/chat-api.php?action=fetch&last_id=${lastMessageId}`)
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          // Remove loading indicator on first fetch
          const loader = document.getElementById('chatLoadingIndicator');
          if (loader) loader.remove();

          let shouldScroll = false;
          // Determine if user is already scrolled to bottom
          if (viewport.scrollHeight - viewport.scrollTop - viewport.clientHeight < 50) {
            shouldScroll = true;
          }

          data.messages.forEach(msg => {
            lastMessageId = Math.max(lastMessageId, msg.id);
            appendMessageBubble(msg);
          });

          if (data.messages.length > 0 && shouldScroll) {
            viewport.scrollTop = viewport.scrollHeight;
          }

          // Update active directory count and list
          updateOnlineDirectory(data.online_users);
        }
      })
      .catch(err => console.error("Chat sync issue:", err));
  }

  // Send message handler
  chatForm.addEventListener('submit', (e) => {
    e.preventDefault();
    if (msgInput.value.trim() === '' && fileInput.files.length === 0) return;

    const formData = new FormData(chatForm);
    
    // Clear inputs immediately for smooth UI transition
    const tempText = msgInput.value;
    msgInput.value = '';
    fileInput.value = '';
    previewBar.classList.remove('d-flex');
    previewBar.classList.add('d-none');

    fetch(`<?= APP_URL ?>/api/chat-api.php?action=send`, {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        fetchMessages(); // Pull updates immediately
      } else {
        msgInput.value = tempText; // Restore text if failed
        alert("Failed to send: " + data.message);
      }
    })
    .catch(err => {
      msgInput.value = tempText;
      console.error(err);
    });
  });

  // Start polling loop every 3 seconds
  fetchMessages();
  setInterval(fetchMessages, 3000);
});

// Render Message Bubble
function appendMessageBubble(msg) {
  const viewport = document.getElementById('chatMessagesViewport');
  const isMe = parseInt(msg.user_id) === currentUserId;
  const initial = msg.sender_name.charAt(0).toUpperCase();

  const bubbleWrapper = document.createElement('div');
  bubbleWrapper.className = `d-flex gap-3 mb-3 ${isMe ? 'justify-content-end' : ''}`;
  
  let attachmentHtml = '';
  if (msg.file_path) {
    const ext = msg.file_path.split('.').pop().toLowerCase();
    const isImg = ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext);

    if (isImg) {
      attachmentHtml = `
        <div class="mt-2 text-center" style="max-width: 250px;">
          <a href="javascript:void(0)" onclick="previewDocument('<?= APP_URL ?>/${msg.file_path}', '${msg.file_name}')">
            <img src="<?= APP_URL ?>/${msg.file_path}" class="img-fluid rounded border hover-shadow shadow-sm" style="max-height: 150px; object-fit: cover;">
          </a>
          <small class="text-muted d-block mt-1 font-monospace" style="font-size: 0.7rem; word-break: break-all;">${msg.file_name}</small>
        </div>
      `;
    } else {
      attachmentHtml = `
        <div class="mt-2 p-2 bg-light rounded border d-flex align-items-center gap-2" style="max-width: 280px;">
          <i class="fas fa-file-invoice text-primary fs-5"></i>
          <div class="text-truncate" style="flex: 1;">
            <a href="javascript:void(0)" onclick="previewDocument('<?= APP_URL ?>/${msg.file_path}', '${msg.file_name}')" class="text-decoration-none text-dark fw-bold small text-truncate d-block">${msg.file_name}</a>
            <small class="text-muted font-monospace" style="font-size: 0.65rem;">.${ext.toUpperCase()}</small>
          </div>
          <button onclick="previewDocument('<?= APP_URL ?>/${msg.file_path}', '${msg.file_name}')" class="btn btn-xs btn-outline-primary border-0"><i class="fas fa-eye"></i></button>
        </div>
      `;
    }
  }

  bubbleWrapper.innerHTML = `
    ${!isMe ? `<div class="avatar-circle bg-secondary text-white" style="width: 38px; height: 38px; border: none; flex-shrink: 0;">${initial}</div>` : ''}
    <div style="max-width: 65%;">
      <div class="d-flex align-items-center gap-2 mb-1 ${isMe ? 'justify-content-end' : ''}">
        <span class="fw-bold small text-dark">${msg.sender_name}</span>
        <span class="badge bg-light text-muted border px-2" style="font-size: 0.65rem;">${msg.role_name}</span>
        <small class="text-muted" style="font-size: 0.65rem;">${formatTime(msg.created_at)}</small>
      </div>
      <div class="p-3 shadow-sm" style="border-radius: 12px; background-color: ${isMe ? '#3b82f6' : 'var(--bg-card)'}; color: ${isMe ? '#ffffff' : 'var(--text-main)'};">
        <p class="mb-0" style="font-size: 0.9rem; word-break: break-word;">${msg.message}</p>
        ${attachmentHtml}
      </div>
    </div>
    ${isMe ? `<div class="avatar-circle bg-primary text-white" style="width: 38px; height: 38px; border: none; flex-shrink: 0;">${initial}</div>` : ''}
  `;

  viewport.appendChild(bubbleWrapper);
}

// Update active directory side panel
function updateOnlineDirectory(users) {
  const container = document.getElementById('onlineUsersList');
  const mobileContainer = document.getElementById('mobileOnlineUsersList');
  const countBadge = document.getElementById('chatActiveUserCount');
  const mobileCountBadge = document.getElementById('mobileOnlineCountBadge');
  
  if (countBadge) {
    countBadge.innerHTML = `<i class="fas fa-circle text-success me-1"></i> ${users.length} Online`;
  }
  if (mobileCountBadge) {
    mobileCountBadge.textContent = users.length;
  }

  if (users.length === 0) {
    if (container) container.innerHTML = '<p class="text-muted small text-center my-4">No other employees online.</p>';
    if (mobileContainer) mobileContainer.innerHTML = '<p class="text-muted small text-center my-4">No other employees online.</p>';
    return;
  }

  if (container) container.innerHTML = '';
  if (mobileContainer) mobileContainer.innerHTML = '';

  users.forEach(u => {
    const initial = u.name.charAt(0).toUpperCase();
    const isMe = u.id === currentUserId;

    const item = document.createElement('div');
    item.className = 'list-group-item d-flex align-items-center justify-content-between px-0 py-2 border-0 bg-transparent';
    item.innerHTML = `
      <div class="d-flex align-items-center gap-2.5">
        <div class="position-relative">
          <div class="avatar-circle bg-primary-soft text-primary" style="width: 34px; height: 34px; border: none;">${initial}</div>
          <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-white rounded-circle" style="transform: translate(25%, 25%);"></span>
        </div>
        <div>
          <div class="fw-bold small text-dark">${u.name} ${isMe ? '<small class="text-muted">(You)</small>' : ''}</div>
          <small class="text-muted d-block" style="font-size: 0.7rem;">${u.role_name}</small>
        </div>
      </div>
    `;
    
    if (container) container.appendChild(item.cloneNode(true));
    if (mobileContainer) mobileContainer.appendChild(item);
  });
}

function formatTime(timestamp) {
  const d = new Date(timestamp);
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Document Preview Logic
function previewDocument(filePath, docName) {
  const modal = new bootstrap.Modal(document.getElementById('documentPreviewModal'));
  const container = document.getElementById('previewContentContainer');
  const printBtn = document.getElementById('printPreviewBtn');
  document.getElementById('previewModalLabel').textContent = docName;

  const ext = filePath.split('.').pop().toLowerCase();
  
  if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
    container.innerHTML = `<img src="${filePath}" id="printableDocImage" class="img-fluid rounded border shadow-sm" style="max-height: 500px; object-fit: contain; width: 100%;">`;
    printBtn.style.display = 'block';
    printBtn.onclick = () => {
      printImage(filePath);
    };
  } else if (ext === 'pdf') {
    container.innerHTML = `<iframe src="${filePath}" id="printableDocFrame" style="width:100%; height:520px; border:none; border-radius: 8px;"></iframe>`;
    printBtn.style.display = 'block';
    printBtn.onclick = () => {
      const frame = document.getElementById('printableDocFrame');
      if (frame) {
        frame.contentWindow.focus();
        frame.contentWindow.print();
      }
    };
  } else {
    container.innerHTML = `
      <div class="text-center py-5">
        <i class="fas fa-file-invoice fa-3x mb-3 text-secondary"></i>
        <h6>In-App Preview not supported for this file format (.${ext}).</h6>
        <p class="small text-muted mb-3">You can download the attachment directly to view it.</p>
        <a href="${filePath}" download class="btn btn-primary btn-sm px-4 fw-bold"><i class="fas fa-download me-1"></i> Download File</a>
      </div>
    `;
    printBtn.style.display = 'none';
    printBtn.onclick = null;
  }

  modal.show();
}

function printImage(imageSrc) {
  const win = window.open('', '_blank');
  win.document.write(`
    <html>
      <head>
        <title>Print Image Attachment</title>
        <style>
          body { margin: 0; display: flex; justify-content: center; align-items: center; height: 100vh; background: #fff; }
          img { max-width: 100%; max-height: 100%; object-fit: contain; }
          @media print {
            body { margin: 0; }
            img { max-width: 100%; max-height: 100%; page-break-inside: avoid; }
          }
        </style>
      </head>
      <body onload="window.print(); window.close();">
        <img src="${imageSrc}">
      </body>
    </html>
  `);
  win.document.close();
}
</script>

<!-- Modal: Mobile Online Directory -->
<div class="modal fade" id="mobileOnlineUsersModal" tabindex="-1" aria-labelledby="mobileOnlineUsersLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-lg);">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title fw-bold" id="mobileOnlineUsersLabel"><i class="fas fa-users text-primary me-2"></i> Online Directory</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 bg-light" style="max-height: 400px; overflow-y: auto;">
        <div class="list-group list-group-flush gap-2" id="mobileOnlineUsersList">
          <p class="text-muted small text-center my-4">Fetching online users...</p>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
