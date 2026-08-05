/**
 * SortableJS Kanban Board Controller
 */

document.addEventListener('DOMContentLoaded', () => {
  const columns = document.querySelectorAll('.kanban-cards-wrapper');
  
  columns.forEach(col => {
    new Sortable(col, {
      group: 'kanban-board',
      animation: 200,
      ghostClass: 'bg-light',
      dragClass: 'shadow-lg',
      onEnd: function (evt) {
        const fileCard = evt.item;
        const fileId = fileCard.getAttribute('data-file-id');
        const targetStageId = evt.to.getAttribute('data-stage-id');

        if (fileId && targetStageId && evt.from !== evt.to) {
          updateKanbanStage(fileId, targetStageId);
        }
      }
    });
  });
});

function updateKanbanStage(fileId, targetStageId) {
  fetch(APP_URL + '/api/kanban-api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      file_id: fileId,
      target_stage_id: targetStageId
    })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showToast('File stage updated successfully', 'success');
    } else {
      showToast(data.message || 'Failed to update stage', 'danger');
      window.location.reload();
    }
  })
  .catch(err => {
    console.error(err);
    showToast('Error updating Kanban stage', 'danger');
  });
}

// Non-blocking AJAX Poll for Real-Time Kanban Auto-Sync
let lastUpdateHash = '';
let isDragging = false;

document.addEventListener('dragstart', () => { isDragging = true; });
document.addEventListener('dragend', () => { isDragging = false; });

function checkKanbanUpdates() {
  if (isDragging || document.hidden) return;
  
  fetch(APP_URL + '/api/kanban-updates.php')
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        if (lastUpdateHash && lastUpdateHash !== data.hash) {
          console.log('AJAX Sync: Database state changed, refreshing board...');
          window.location.reload();
        }
        lastUpdateHash = data.hash;
      }
    })
    .catch(err => console.error('Kanban update check failed:', err));
}

// Initial hash load
checkKanbanUpdates();
// Polling interval
setInterval(checkKanbanUpdates, 4000);
