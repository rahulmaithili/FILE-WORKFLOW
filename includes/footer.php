    </main> <!-- End Main -->

    <footer class="mt-auto p-3 text-center text-muted border-top bg-white" style="font-size: 0.85rem;">
      &copy; <?= date('Y') ?> <strong>Mr.Rahul Scripts</strong> &bull; Workflow Driven File CRM
    </footer>

  </div> <!-- End App Content -->
</div> <!-- End App Wrapper -->

<!-- Floating Messenger Drawer Button -->
<?php if (isFeatureEnabled('in_app_chat')): ?>
<button type="button" class="floating-chat-trigger" onclick="toggleFloatingChatDrawer()" id="floatingChatTriggerBtn" title="Open Team Messenger">
  <i class="fas fa-comments"></i>
</button>

<!-- Floating Messenger Drawer Container -->
<div class="chat-drawer-container" id="chatDrawerContainer">
  <div class="chat-drawer-header">
    <div class="d-flex align-items-center gap-2">
      <i class="fas fa-comments"></i>
      <h6 class="fw-bold mb-0">Team Messenger</h6>
    </div>
    <button type="button" class="btn-close btn-close-white" onclick="toggleFloatingChatDrawer()" id="closeChatDrawerBtn"></button>
  </div>
  <div class="chat-drawer-body" id="chatDrawerBody">
    <!-- Messages loaded dynamically -->
  </div>
  <div class="chat-drawer-footer">
    <form id="chatDrawerForm" class="d-flex gap-2">
      <input type="text" name="message" id="chatDrawerInput" class="form-control form-control-sm" placeholder="Type a message..." autocomplete="off">
      <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-paper-plane"></i></button>
    </form>
    <!-- Link current file if on view.php -->
    <?php if (isset($file) && isset($file['file_code'])): ?>
      <div class="mt-2 text-end">
        <button type="button" class="btn btn-xs btn-outline-secondary py-0.5 px-2" onclick="linkFileToChat('<?= $file['file_code'] ?>', '<?= APP_URL ?>/modules/file/view.php?id=<?= $file['id'] ?>')">
          <i class="fas fa-paperclip me-1"></i> Link Current Case File
        </button>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
let lastMsgId = 0;
let pollInterval = null;

function toggleFloatingChatDrawer() {
  const drawer = document.getElementById('chatDrawerContainer');
  if (!drawer) return;
  
  drawer.classList.toggle('open');
  if (drawer.classList.contains('open')) {
    loadDrawerMessages();
    if (!pollInterval) {
      pollInterval = setInterval(loadDrawerMessages, 4000);
    }
  } else {
    if (pollInterval) {
      clearInterval(pollInterval);
      pollInterval = null;
    }
  }
}

function loadDrawerMessages() {
  if (document.hidden) return;
  const chatBody = document.getElementById('chatDrawerBody');
  if (!chatBody) return;

  fetch('<?= APP_URL ?>/api/chat-api.php?action=fetch&last_id=' + lastMsgId)
  .then(r => r.json())
  .then(data => {
    if (data.success && data.messages.length > 0) {
      let shouldScroll = chatBody.scrollHeight - chatBody.scrollTop - chatBody.clientHeight < 80;
      
      data.messages.forEach(m => {
        lastMsgId = Math.max(lastMsgId, m.id);
        const isMe = parseInt(m.user_id) === <?= intval($_SESSION['user_id'] ?? 0) ?>;
        const bubble = document.createElement('div');
        bubble.className = `chat-bubble-mini ${isMe ? 'me' : 'other'} mb-2 d-flex flex-column`;
        bubble.innerHTML = `<small class="fw-bold opacity-75 text-truncate" style="font-size: 0.65rem; color: ${isMe ? '#e0f2fe' : 'var(--text-muted)'};">${m.sender_name}</small><span>${m.message}</span>`;
        chatBody.appendChild(bubble);
      });

      if (shouldScroll) {
        chatBody.scrollTop = chatBody.scrollHeight;
      }
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  const chatForm = document.getElementById('chatDrawerForm');
  const chatInput = document.getElementById('chatDrawerInput');
  
  if (chatForm && chatInput) {
    chatForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const msg = chatInput.value.trim();
      if (!msg) return;

      chatInput.value = '';
      const formData = new FormData();
      formData.append('message', msg);

      fetch('<?= APP_URL ?>/api/chat-api.php?action=send', {
        method: 'POST',
        body: formData
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          loadDrawerMessages();
        }
      });
    });
  }
});

function linkFileToChat(code, url) {
  const input = document.getElementById('chatDrawerInput');
  if (input) {
    input.value = `Refer to case file: ${code} - ${url}`;
    input.focus();
  }
}
</script>
<?php endif; ?>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- ApexCharts for Analytics -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<!-- SortableJS for Drag and Drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<!-- Custom JS -->
<script src="<?= APP_URL ?>/assets/js/main.js"></script>

</body>
</html>
