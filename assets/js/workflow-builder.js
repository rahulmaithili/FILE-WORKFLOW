/**
 * Drag & Drop Workflow Builder JS Controller
 */

document.addEventListener('DOMContentLoaded', () => {
  const stageListEl = document.getElementById('workflowStageList');
  if (stageListEl) {
    new Sortable(stageListEl, {
      animation: 200,
      handle: '.drag-handle',
      ghostClass: 'bg-light',
      onEnd: function () {
        saveStageOrder();
      }
    });
  }
});

function saveStageOrder() {
  const stageItems = document.querySelectorAll('#workflowStageList .workflow-stage-item');
  const stageIds = Array.from(stageItems).map(item => item.getAttribute('data-stage-id'));

  fetch(APP_URL + '/api/workflow-api.php?action=reorder_stages', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ stage_ids: stageIds })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showToast('Workflow stage order updated', 'success');
      updateStageNumbers();
    } else {
      showToast(data.message || 'Failed to reorder stages', 'danger');
    }
  })
  .catch(err => {
    console.error(err);
    showToast('Error saving stage order', 'danger');
  });
}

function updateStageNumbers() {
  const stageItems = document.querySelectorAll('#workflowStageList .workflow-stage-item');
  stageItems.forEach((item, index) => {
    const badge = item.querySelector('.stage-number-badge');
    if (badge) {
      badge.textContent = 'Step ' + (index + 1);
    }
  });
}

function deleteStage(stageId) {
  Swal.fire({
    title: 'Delete Workflow Stage?',
    text: 'Are you sure you want to delete this workflow stage step?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Yes, delete stage'
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('stage_id', stageId);

      fetch(APP_URL + '/api/workflow-api.php?action=delete_stage', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast('Stage deleted', 'success');
          const item = document.querySelector(`.workflow-stage-item[data-stage-id="${stageId}"]`);
          if (item) item.remove();
          updateStageNumbers();
        } else {
          showToast(data.message || 'Failed to delete stage', 'danger');
        }
      });
    }
  });
}
