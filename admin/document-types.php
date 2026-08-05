<?php
$pageTitle = 'Document Categories Manager';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

// Handle Add / Edit Document Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_doc_type'])) {
    $id = intval($_POST['doc_type_id'] ?? 0);
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $isMandatory = isset($_POST['is_mandatory']) ? 1 : 0;

    if (empty($name)) {
        setFlashMessage('danger', 'Document category name is required.');
    } else {
        if ($id > 0) {
            // Edit Mode
            $stmtUpdate = $db->prepare("UPDATE document_types SET name = :name, description = :desc, is_mandatory = :mandatory WHERE id = :id");
            $stmtUpdate->execute(['name' => $name, 'desc' => $description, 'mandatory' => $isMandatory, 'id' => $id]);
            setFlashMessage('success', 'Document category updated successfully.');
        } else {
            // Add Mode
            try {
                $stmtInsert = $db->prepare("INSERT INTO document_types (name, description, is_mandatory) VALUES (:name, :desc, :mandatory)");
                $stmtInsert->execute(['name' => $name, 'desc' => $description, 'mandatory' => $isMandatory]);
                setFlashMessage('success', 'Document category created successfully.');
            } catch (Exception $e) {
                setFlashMessage('danger', 'Failed to create document category (it might already exist).');
            }
        }
        header("Location: document-types.php");
        exit;
    }
}

// Handle Delete Category
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);
    $stmtDelete = $db->prepare("DELETE FROM document_types WHERE id = :id");
    $stmtDelete->execute(['id' => $deleteId]);
    setFlashMessage('success', 'Document category deleted successfully.');
    header("Location: document-types.php");
    exit;
}

// Fetch all document categories
$docTypes = $db->query("SELECT * FROM document_types ORDER BY name ASC")->fetchAll();
?>

<div class="row g-4">
  <!-- Left Side: Add / Edit Form -->
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm p-4" style="border-radius: var(--radius-lg);">
      <h5 class="fw-bold text-dark border-bottom pb-2 mb-3" id="formTitle">
        <i class="fas fa-plus-circle text-primary me-2"></i> Add New Category
      </h5>

      <form action="document-types.php" method="POST" id="docTypeForm">
        <input type="hidden" name="save_doc_type" value="1">
        <input type="hidden" name="doc_type_id" id="doc_type_id" value="0">

        <div class="mb-3">
          <label class="form-label fw-bold small">Category Name <span class="text-danger">*</span></label>
          <input type="text" name="name" id="doc_name" class="form-control" placeholder="e.g. Aadhaar Card" required>
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold small">Description</label>
          <textarea name="description" id="doc_desc" class="form-control" rows="3" placeholder="Explain the purpose of this document..."></textarea>
        </div>

        <div class="form-check form-switch mb-4">
          <input class="form-check-input" type="checkbox" name="is_mandatory" id="doc_mandatory" value="1" checked>
          <label class="form-check-input-label fw-semibold small" for="doc_mandatory">Mark as Mandatory (Required by default)</label>
        </div>

        <div class="d-flex gap-2">
          <button type="button" id="resetBtn" class="btn btn-light border flex-fill d-none" onclick="resetForm()">Cancel</button>
          <button type="submit" class="btn btn-primary flex-fill fw-bold shadow-sm">
            <i class="fas fa-check-circle me-1"></i> Save Category
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Right Side: Document Categories Listing -->
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm p-4" style="border-radius: var(--radius-lg);">
      <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
        <div>
          <h4 class="fw-bold mb-1 text-dark"><i class="fas fa-folder-open text-primary me-2"></i> Document Categories</h4>
          <p class="text-muted small mb-0">Define what documents can be mapped and checklist-verified per connection type</p>
        </div>
        <span class="badge bg-primary-soft text-primary border px-3 py-2">
          <i class="fas fa-tags me-1"></i> <?= count($docTypes) ?> Categories
        </span>
      </div>

      <?php if (empty($docTypes)): ?>
        <div class="text-center py-5">
          <div class="text-muted mb-3"><i class="fas fa-file-invoice fa-3x"></i></div>
          <p class="text-muted mb-0">No document categories defined yet.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Name</th>
                <th>Description</th>
                <th>Requirement</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($docTypes as $d): ?>
                <tr>
                  <td>
                    <strong class="text-dark d-block"><?= htmlspecialchars($d['name']) ?></strong>
                  </td>
                  <td class="small text-muted" style="max-width: 250px; text-truncate: ellipsis;"><?= htmlspecialchars($d['description'] ?: 'No description.') ?></td>
                  <td>
                    <?php if ($d['is_mandatory'] == 1): ?>
                      <span class="badge bg-danger-soft text-danger fw-bold"><i class="fas fa-exclamation-circle me-1"></i> Mandatory</span>
                    <?php else: ?>
                      <span class="badge bg-light text-muted border fw-semibold">Optional</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="editDocType(<?= $d['id'] ?>, '<?= htmlspecialchars(addslashes($d['name'])) ?>', '<?= htmlspecialchars(addslashes($d['description'])) ?>', <?= $d['is_mandatory'] ?>)">
                      <i class="fas fa-edit"></i> Edit
                    </button>
                    <a href="document-types.php?delete_id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this category?')">
                      <i class="fas fa-trash-alt"></i> Delete
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function editDocType(id, name, desc, isMandatory) {
  document.getElementById('formTitle').innerHTML = "<i class='fas fa-edit text-primary me-2'></i> Edit Category";
  document.getElementById('doc_type_id').value = id;
  document.getElementById('doc_name').value = name;
  document.getElementById('doc_desc').value = desc;
  document.getElementById('doc_mandatory').checked = (isMandatory == 1);
  document.getElementById('resetBtn').classList.remove('d-none');
}

function resetForm() {
  document.getElementById('formTitle').innerHTML = "<i class='fas fa-plus-circle text-primary me-2'></i> Add New Category";
  document.getElementById('doc_type_id').value = '0';
  document.getElementById('doc_name').value = '';
  document.getElementById('doc_desc').value = '';
  document.getElementById('doc_mandatory').checked = true;
  document.getElementById('resetBtn').classList.add('d-none');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
